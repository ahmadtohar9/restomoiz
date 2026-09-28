<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Rekonsiliasi bank bulanan (PRD 2.4.5 "Monthly Bank Reconciliation").
 *
 * Seharusnya masuk bank (POS) = penjualan - refund bulan itu.
 * Selisih = mutasi masuk rekening + dana dalam perjalanan + biaya bank/MDR
 *           + penyesuaian lain - seharusnya masuk.
 */
class Reconciliations extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->require_permission('payment.reconcile');
		$this->load->model('Settlement_model');
	}

	public function index()
	{
		$this->render('finance/reconciliations/index', array(
			'title' => 'Rekonsiliasi Bank',
			'rows'  => $this->db->select('b.*, u.name AS reconciled_by_name')->from('bank_reconciliations b')->join('users u', 'u.id = b.reconciled_by', 'left')
				->order_by('b.period', 'DESC')->get()->result_array(),
		));
	}

	public function period($period = NULL)
	{
		if ( ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $period) OR $period > date('Y-m'))
		{
			show_404();
		}
		$start = $period . '-01';
		$end = min(date('Y-m-t', strtotime($start)), date('Y-m-d'));
		$exp = $this->Settlement_model->expected($start, $end);
		$row = $this->db->where('period', $period)->get('bank_reconciliations')->row_array();
		$daily = $this->db->select('COUNT(*) AS days, SUM(status = \'verified\') AS verified, COALESCE(SUM(variance), 0) AS variance', FALSE)
			->where('settle_date >=', $start)->where('settle_date <=', $end)->get('daily_settlements')->row_array();
		$errors = array();

		if ($this->input->method() === 'post')
		{
			if ($row && $row['status'] === 'reconciled')
			{
				flash('warning', 'Periode ini sudah direkonsiliasi.');
				redirect('finance/reconciliations/period/' . $period);
			}
			$vals = array();
			foreach (array('bank_credits' => NULL, 'in_transit' => 0.0, 'fees' => 0.0, 'other_adjustment' => 0.0) as $k => $empty)
			{
				$v = num_in($this->input->post($k), $empty);
				if ($v !== NULL && (is_nan($v) OR ($k !== 'other_adjustment' && $v < 0)))
				{
					$errors[] = 'Nilai tidak valid.';
					$v = $empty;
				}
				$vals[$k] = $v;
			}
			$notes = trim((string) $this->input->post('notes'));
			$bank = trim((string) $this->input->post('bank_name'));
			$final = $this->input->post('action') === 'reconcile';
			$variance = $vals['bank_credits'] === NULL ? NULL
				: round($vals['bank_credits'] + $vals['in_transit'] + $vals['fees'] + $vals['other_adjustment'] - $exp['expected_total'], 2);
			if ($final && $variance === NULL)
			{
				$errors[] = 'Isi total mutasi masuk dari rekening koran.';
			}
			if ($final && $variance !== NULL && abs($variance) >= 1 && $notes === '')
			{
				$errors[] = 'Masih ada selisih ' . rupiah($variance) . '. Jelaskan di catatan (atau buat penyesuaian) sebelum menutup rekonsiliasi.';
			}

			$this->load->library('private_upload');
			$file = empty($errors) ? $this->private_upload->save('attachment', 'settlements', $errors) : NULL;

			if (empty($errors))
			{
				$data = array_merge($vals, array('bank_name' => $bank !== '' ? mb_substr($bank, 0, 50) : NULL, 'expected_deposit' => $exp['expected_total'],
					'variance' => $variance, 'notes' => $notes !== '' ? $notes : NULL));
				if ($file)
				{
					$data['attachment_path'] = $file;
				}
				if ($final)
				{
					$data['status'] = 'reconciled';
					$data['reconciled_by'] = $this->current_user['id'];
					$data['reconciled_at'] = date('Y-m-d H:i:s');
				}
				if ($row)
				{
					$this->db->where('id', $row['id'])->update('bank_reconciliations', $data);
				}
				else
				{
					$data['period'] = $period;
					$data['created_by'] = $this->current_user['id'];
					$this->db->insert('bank_reconciliations', $data);
				}
				$this->audit->log($final ? 'bank_reconcile' : 'bank_recon_save', array('table_name' => 'bank_reconciliations', 'record_id' => $period,
					'detail' => array('variance' => $variance, 'expected' => $exp['expected_total'])));
				flash('success', $final ? "Rekonsiliasi $period ditutup." : "Rekonsiliasi $period disimpan.");
				redirect('finance/reconciliations/period/' . $period);
			}
			$row = array_merge($row ?: array('status' => 'draft', 'attachment_path' => NULL), $vals, array('notes' => $notes, 'bank_name' => $bank));
		}

		$this->render('finance/reconciliations/period', array(
			'title'  => 'Rekonsiliasi Bank ' . $period,
			'period' => $period,
			'exp'    => $exp,
			'row'    => $row,
			'daily'  => $daily,
			'errors' => $errors,
		));
	}
}
