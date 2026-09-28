<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Shift kasir (PRD 2.4.6): buka dengan modal awal, tutup dengan hitung kas,
 * approval selisih, ringkasan shift.
 */
class Shifts extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		if ( ! can_any(array('sales.shift', 'sales.shift_approve', 'report.operational', 'report.own_shift')))
		{
			$this->require_permission('sales.shift');
		}
		$this->load->library('pos_service', NULL, 'pos');
	}

	public function index()
	{
		$all = can_any(array('sales.shift_approve', 'report.operational'));
		$this->db->select('s.*, u.name AS user_name,
				(SELECT COUNT(*) FROM orders o WHERE o.shift_id = s.id AND o.status = \'paid\') AS tx,
				(SELECT COALESCE(SUM(total), 0) FROM orders o WHERE o.shift_id = s.id AND o.status = \'paid\') AS sales', FALSE)
			->from('shifts s')->join('users u', 'u.id = s.user_id');
		if ( ! $all)
		{
			$this->db->where('s.user_id', $this->current_user['id']);
		}
		$rows = $this->db->order_by('s.id', 'DESC')->limit(200)->get()->result_array();

		$this->render('sales/shifts/index', array(
			'title'     => 'Shift Kasir',
			'rows'      => $rows,
			'current'   => $this->pos->current_shift($this->current_user['id']),
			'templates' => $this->db->order_by('start_time')->get('shift_templates')->result_array(),
			'all'       => $all,
		));
	}

	public function open()
	{
		$this->require_permission('sales.shift');
		$this->_require_post();
		$balance = num_in($this->input->post('opening_balance'), NAN);
		try
		{
			if (is_nan($balance))
			{
				throw new Pos_exception('Isi modal awal (uang tunai di laci).');
			}
			$id = $this->pos->run(function ($pos) use ($balance) {
				return $pos->open_shift($balance, trim((string) $this->input->post('shift_name')), trim((string) $this->input->post('register_name')));
			});
			$this->audit->log('shift_open', array('table_name' => 'shifts', 'record_id' => $id, 'detail' => array('opening_balance' => $balance)));
			flash('success', 'Shift dibuka dengan modal ' . rupiah($balance) . '.');
			redirect($this->input->post('back') === 'pos' ? 'pos' : 'sales/shifts/show/' . $id);
		}
		catch (Pos_exception $e)
		{
			flash('danger', $e->getMessage());
			redirect('sales/shifts');
		}
	}

	public function show($id = NULL)
	{
		$s = $this->pos->shift_summary($id);
		if ( ! $s)
		{
			show_404();
		}
		if ((int) $s['user_id'] !== (int) $this->current_user['id'] && ! can_any(array('sales.shift_approve', 'report.operational')))
		{
			$this->require_permission('report.operational');
		}
		$this->render('sales/shifts/show', array(
			'title'     => 'Shift #' . $s['id'] . ' · ' . $s['user_name'],
			's'         => $s,
			'limit'     => (float) setting('cash_variance_limit', 10000),
			'can_close' => $s['status'] === 'open' && ((int) $s['user_id'] === (int) $this->current_user['id'] OR can('sales.shift_approve')),
			'can_approve' => $s['status'] === 'pending_approval' && can('sales.shift_approve')
				&& ((int) $s['user_id'] !== (int) $this->current_user['id'] OR $this->rbac->is_super()),
			'open_orders' => (int) $this->db->where('status', 'open')->where('created_by', $s['user_id'])->count_all_results('orders'),
		));
	}

	public function close($id = NULL)
	{
		$this->_require_post();
		$balance = num_in($this->input->post('closing_balance'), NAN);
		$note = trim((string) $this->input->post('note'));
		try
		{
			if (is_nan($balance))
			{
				throw new Pos_exception('Isi jumlah uang tunai di laci saat ini.');
			}
			$r = $this->pos->run(function ($pos) use ($id, $balance, $note) { return $pos->close_shift($id, $balance, $note); });
			$this->audit->log('shift_close', array('table_name' => 'shifts', 'record_id' => (int) $id,
				'detail' => array('closing_balance' => $balance, 'variance' => $r['variance'], 'status' => $r['status'])));
			flash($r['status'] === 'closed' ? 'success' : 'warning', $r['status'] === 'closed'
				? 'Shift ditutup. Selisih kas: ' . rupiah($r['variance']) . '.'
				: 'Selisih kas ' . rupiah($r['variance']) . ' melebihi batas: shift menunggu approval manajer.');
		}
		catch (Pos_exception $e)
		{
			flash('danger', $e->getMessage());
		}
		redirect('sales/shifts/show/' . (int) $id);
	}

	public function approve($id = NULL)
	{
		$this->require_permission('sales.shift_approve');
		$this->_require_post();
		$note = trim((string) $this->input->post('note'));
		try
		{
			$this->pos->run(function ($pos) use ($id, $note) { $pos->approve_shift($id, $note); });
			$this->audit->log('shift_approve', array('table_name' => 'shifts', 'record_id' => (int) $id, 'detail' => array('note' => $note)));
			flash('success', 'Selisih kas disetujui, shift ditutup.');
		}
		catch (Pos_exception $e)
		{
			flash('danger', $e->getMessage());
		}
		redirect('sales/shifts/show/' . (int) $id);
	}

	/** Atur template nama shift (Pagi/Siang/Malam). */
	public function templates()
	{
		$this->require_permission('sales.shift_approve');
		if ($this->input->method() === 'post')
		{
			$names = (array) $this->input->post('name');
			$starts = (array) $this->input->post('start_time');
			$ends = (array) $this->input->post('end_time');
			$rows = array();
			foreach ($names as $i => $n)
			{
				$n = trim((string) $n);
				if ($n === '')
				{
					continue;
				}
				$s = isset($starts[$i]) ? $starts[$i] : '';
				$e = isset($ends[$i]) ? $ends[$i] : '';
				if ( ! preg_match('/^\d{2}:\d{2}$/', $s) OR ! preg_match('/^\d{2}:\d{2}$/', $e))
				{
					flash('danger', "Jam shift $n tidak valid.");
					redirect('sales/shifts/templates');
				}
				$rows[] = array('name' => mb_substr($n, 0, 50), 'start_time' => $s . ':00', 'end_time' => $e . ':00');
			}
			$this->db->trans_start();
			$this->db->empty_table('shift_templates');
			foreach ($rows as $r)
			{
				$this->db->insert('shift_templates', $r);
			}
			$this->db->trans_complete();
			$this->audit->log('shift_templates_update', array('table_name' => 'shift_templates', 'detail' => array_column($rows, 'name')));
			flash('success', 'Jadwal shift disimpan.');
			redirect('sales/shifts/templates');
		}
		$this->render('sales/shifts/templates', array('title' => 'Jadwal Shift', 'rows' => $this->db->order_by('start_time')->get('shift_templates')->result_array()));
	}

	protected function _require_post()
	{
		if ($this->input->method() !== 'post')
		{
			show_error('Method not allowed', 405);
		}
	}
}
