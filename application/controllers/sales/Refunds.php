<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Refund setelah bayar (PRD 2.4.4) dengan matriks approval.
 */
class Refunds extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		if ( ! can_any(array('sales.refund', 'sales.refund_approve', 'sales.refund_owner', 'report.operational', 'report.financial')))
		{
			$this->require_permission('sales.refund');
		}
		$this->load->library('refund_service', NULL, 'refund');
	}

	public function index()
	{
		$date = function ($v, $d) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? $v : $d; };
		$status = (string) $this->input->get('status');
		$f = array(
			'status' => isset(Refund_service::$status[$status]) ? $status : '',
			'from'   => $date($this->input->get('from'), date('Y-m-d', strtotime('-30 days'))),
			'to'     => $date($this->input->get('to'), date('Y-m-d')),
		);
		$this->db->select('r.*, o.order_number, o.payment_method, u.name AS requested_by_name, a.name AS approved_by_name')
			->from('order_refunds r')->join('orders o', 'o.id = r.order_id')
			->join('users u', 'u.id = r.requested_by')->join('users a', 'a.id = r.approved_by', 'left');
		if (in_array($f['status'], array('pending_approval', 'approved'), TRUE))
		{
			$this->db->where('r.status', $f['status']);
		}
		else
		{
			$this->db->where('r.requested_at >=', $f['from'] . ' 00:00:00')->where('r.requested_at <=', $f['to'] . ' 23:59:59');
			if ($f['status'])
			{
				$this->db->where('r.status', $f['status']);
			}
		}
		$this->render('sales/refunds/index', array(
			'title'   => 'Refund',
			'rows'    => $this->db->order_by('r.id', 'DESC')->limit(300)->get()->result_array(),
			'filters' => $f,
			'pending' => $this->db->where('status', 'pending_approval')->count_all_results('order_refunds'),
			'waiting' => $this->db->where('status', 'approved')->count_all_results('order_refunds'),
		));
	}

	/** Form refund untuk satu transaksi lunas. */
	public function create($order_id = NULL)
	{
		if ( ! can_any(array('sales.refund', 'sales.refund_approve', 'sales.refund_owner')))
		{
			$this->require_permission('sales.refund');
		}
		$order = $this->db->where('id', (int) $order_id)->get('orders')->row_array();
		if ( ! $order)
		{
			show_404();
		}
		if ($order['status'] !== 'paid')
		{
			flash('warning', 'Refund hanya untuk transaksi yang sudah dibayar. Pesanan yang belum dibayar cukup dibatalkan.');
			redirect('sales/orders/show/' . $order['id']);
		}
		$items = $this->refund->refundable_items($order['id']);
		$errors = array();
		$input = array('qtys' => array(), 'reason_code' => 'quality', 'reason' => '', 'method' => $order['payment_method'] === 'cash' ? 'cash' : 'original',
			'restock' => 1, 'payment_ref' => '');

		if ($this->input->method() === 'post')
		{
			foreach ((array) $this->input->post('qty') as $id => $q)
			{
				$input['qtys'][(int) $id] = max(0, (int) $q);
			}
			$input['reason_code'] = (string) $this->input->post('reason_code');
			$input['reason'] = trim((string) $this->input->post('reason'));
			$input['method'] = (string) $this->input->post('method');
			$input['restock'] = $this->input->post('restock') ? 1 : 0;
			$input['payment_ref'] = trim((string) $this->input->post('payment_ref'));

			if ($this->input->post('preview'))
			{
				try
				{
					$calc = $this->refund->calculate($order, $input['qtys']);
					$calc['level'] = $this->refund->required_level($calc['total'], $order['paid_at']);
					return $this->_json(array('ok' => TRUE, 'total' => $calc['total'], 'tax' => $calc['tax'], 'service' => $calc['service'],
						'level' => Refund_service::$levels[$calc['level']], 'is_full' => $calc['is_full']));
				}
				catch (Refund_exception $e)
				{
					return $this->_json(array('ok' => FALSE, 'message' => $e->getMessage()));
				}
			}

			try
			{
				$r = $this->refund->run(function ($svc) use ($order, $input) { return $svc->request($order['id'], $input); });
				$this->audit->log('refund_request', array('table_name' => 'order_refunds', 'record_id' => $r['number'],
					'detail' => array('order' => $order['order_number'], 'amount' => $r['amount'], 'level' => $r['level'], 'status' => $r['status'], 'reason' => $input['reason'])));
				flash($r['status'] === 'completed' ? 'success' : 'warning', $r['status'] === 'completed'
					? "Refund {$r['number']} selesai: kembalikan " . rupiah($r['amount']) . ' ke pelanggan.'
					: "Refund {$r['number']} sebesar " . rupiah($r['amount']) . ' menunggu approval ' . Refund_service::$levels[$r['level']] . '. Tahan uangnya sampai disetujui.');
				redirect('sales/refunds/show/' . $r['id']);
			}
			catch (Exception $e)
			{
				if ( ! ($e instanceof Refund_exception) && ! ($e instanceof Stock_exception))
				{
					throw $e;
				}
				$errors[] = $e->getMessage();
			}
		}

		$this->render('sales/refunds/create', array(
			'title'  => 'Refund ' . $order['order_number'],
			'order'  => $order,
			'items'  => $items,
			'input'  => $input,
			'errors' => $errors,
			'limits' => array('auto' => (float) setting('refund_auto_limit', 500000), 'minutes' => (int) setting('refund_auto_minutes', 5),
				'owner' => (float) setting('refund_owner_limit', 2000000)),
		));
	}

	public function show($id = NULL)
	{
		$r = $this->_refund($id);
		$uid = (int) $this->current_user['id'];
		$can_level = array(
			'cashier' => can_any(array('sales.refund', 'sales.refund_approve', 'sales.refund_owner')),
			'manager' => can_any(array('sales.refund_approve', 'sales.refund_owner')),
			'owner'   => can('sales.refund_owner'),
		);
		$this->render('sales/refunds/show', array(
			'title'       => 'Refund ' . $r['refund_number'],
			'r'           => $r,
			'items'       => $this->_items($r['id']),
			'can_approve' => $r['status'] === 'pending_approval' && $can_level[$r['required_level']] && ((int) $r['requested_by'] !== $uid OR $this->rbac->is_super()),
			'can_reject'  => in_array($r['status'], array('pending_approval', 'approved'), TRUE) && $can_level[$r['required_level']] && can_any(array('sales.refund_approve', 'sales.refund_owner')),
			'can_complete'=> $r['status'] === 'approved' && can_any(array('sales.refund', 'sales.refund_approve', 'sales.refund_owner')),
		));
	}

	public function action($id = NULL, $action = '')
	{
		if ($this->input->method() !== 'post')
		{
			show_error('Method not allowed', 405);
		}
		$r = $this->_refund($id);
		$note = trim((string) $this->input->post('note'));
		try
		{
			$this->refund->run(function ($svc) use ($r, $action, $note) {
				switch ($action)
				{
					case 'approve':  $svc->approve($r['id'], $note); break;
					case 'reject':   $svc->reject($r['id'], $note); break;
					case 'complete':
						if ( ! can_any(array('sales.refund', 'sales.refund_approve', 'sales.refund_owner')))
						{
							throw new Refund_exception('Anda tidak berwenang memproses refund.');
						}
						$svc->complete($r['id'], (string) $this->input->post('payment_ref'));
						break;
					default: throw new Refund_exception('Aksi tidak valid.');
				}
			});
			$this->audit->log('refund_' . $action, array('table_name' => 'order_refunds', 'record_id' => $r['refund_number'], 'detail' => array('note' => $note, 'amount' => $r['refund_amount'])));
			$msg = array('approve' => 'Refund disetujui. Kasir bisa mengembalikan uangnya.', 'reject' => 'Refund ditolak.',
				'complete' => 'Refund selesai: ' . rupiah($r['refund_amount']) . ' dikembalikan ke pelanggan.');
			flash('success', $msg[$action]);
		}
		catch (Exception $e)
		{
			if ( ! ($e instanceof Refund_exception) && ! ($e instanceof Stock_exception))
			{
				throw $e;
			}
			flash('danger', $e->getMessage());
		}
		redirect('sales/refunds/show/' . $r['id']);
	}

	/** Bukti refund (thermal). */
	public function receipt($id = NULL)
	{
		$r = $this->_refund($id);
		$this->load->view('sales/refunds/receipt', array('r' => $r, 'items' => $this->_items($r['id']), 'paper' => setting('receipt_paper', '80'),
			'resto' => setting('resto_name', 'Resto Moiz')));
	}

	protected function _refund($id)
	{
		$r = $this->db->select('r.*, o.order_number, o.payment_method, o.total AS order_total, o.paid_at, o.customer_name,
				u.name AS requested_by_name, a.name AS approved_by_name, c.name AS completed_by_name')
			->from('order_refunds r')->join('orders o', 'o.id = r.order_id')
			->join('users u', 'u.id = r.requested_by')->join('users a', 'a.id = r.approved_by', 'left')->join('users c', 'c.id = r.completed_by', 'left')
			->where('r.id', (int) $id)->get()->row_array();
		if ( ! $r)
		{
			show_404();
		}
		return $r;
	}

	protected function _items($refund_id)
	{
		return $this->db->select('ri.*, i.name, i.unit_price, i.qty AS sold_qty')->from('order_refund_items ri')
			->join('order_items i', 'i.id = ri.order_item_id')->where('ri.refund_id', (int) $refund_id)->get()->result_array();
	}
}
