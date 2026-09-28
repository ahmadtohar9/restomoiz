<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Settlement harian (PRD 2.4.5 "Post-Shift (Accounting - next day)"):
 * cocokkan penjualan per metode bayar (dikurangi refund) dengan setoran
 * tunai ke bank, settlement EDC, dan settlement e-wallet.
 */
class Settlements extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->require_permission('payment.reconcile');
		$this->load->model('Settlement_model');
	}

	public function index()
	{
		$period = preg_match('/^\d{4}-\d{2}$/', (string) $this->input->get('period')) ? $this->input->get('period') : date('Y-m');
		$this->render('finance/settlements/index', array(
			'title'  => 'Settlement Harian',
			'period' => $period,
			'days'   => $this->Settlement_model->month_days($period),
		));
	}

	public function day($date = NULL)
	{
		$d = DateTime::createFromFormat('Y-m-d', (string) $date);
		if ( ! $d OR $d->format('Y-m-d') !== $date OR $date > date('Y-m-d'))
		{
			show_404();
		}
		$row = $this->db->where('settle_date', $date)->get('daily_settlements')->row_array();
		$exp = $this->Settlement_model->expected($date, $date);
		$errors = array();

		if ($this->input->method() === 'post')
		{
			if ($row && $row['status'] === 'verified')
			{
				flash('warning', 'Settlement ini sudah diverifikasi.');
				redirect('finance/settlements/day/' . $date);
			}
			$vals = array();
			foreach (array('actual_cash_deposit', 'actual_card', 'actual_ewallet', 'fees') as $k)
			{
				$v = num_in($this->input->post($k), $k === 'fees' ? 0.0 : NULL);
				if ($v !== NULL && (is_nan($v) OR $v < 0))
				{
					$errors[] = 'Nilai harus angka ≥ 0.';
					$v = NULL;
				}
				$vals[$k] = $v;
			}
			$notes = trim((string) $this->input->post('notes'));
			$verify = $this->input->post('action') === 'verify';
			if ($verify && ($vals['actual_cash_deposit'] === NULL OR $vals['actual_card'] === NULL OR $vals['actual_ewallet'] === NULL))
			{
				$errors[] = 'Isi semua nilai aktual (0 jika tidak ada) sebelum verifikasi.';
			}
			if ($verify && $exp['open_shifts'] > 0)
			{
				$errors[] = 'Masih ada shift yang belum ditutup/diapprove sampai tanggal ini.';
			}
			$variance = NULL;
			if ($vals['actual_cash_deposit'] !== NULL && $vals['actual_card'] !== NULL && $vals['actual_ewallet'] !== NULL)
			{
				$variance = round($vals['actual_cash_deposit'] + $vals['actual_card'] + $vals['actual_ewallet'] + $vals['fees'] - $exp['expected_total'], 2);
			}
			if ($verify && $variance !== NULL && abs($variance) >= 1 && $notes === '')
			{
				$errors[] = 'Ada selisih ' . rupiah($variance) . '. Isi catatan penjelasan sebelum verifikasi.';
			}

			if (empty($errors))
			{
				$data = array_merge($vals, array(
					'expected_cash' => $exp['expected_cash'], 'expected_card' => $exp['expected_card'], 'expected_ewallet' => $exp['expected_ewallet'],
					'shift_variance' => $exp['shift_variance'], 'variance' => $variance, 'notes' => $notes !== '' ? mb_substr($notes, 0, 255) : NULL,
				));
				if ($verify)
				{
					$data['status'] = 'verified';
					$data['verified_by'] = $this->current_user['id'];
					$data['verified_at'] = date('Y-m-d H:i:s');
				}
				if ($row)
				{
					$this->db->where('id', $row['id'])->update('daily_settlements', $data);
				}
				else
				{
					$data['settle_date'] = $date;
					$data['created_by'] = $this->current_user['id'];
					$this->db->insert('daily_settlements', $data);
				}
				$this->audit->log($verify ? 'settlement_verify' : 'settlement_save', array('table_name' => 'daily_settlements', 'record_id' => $date,
					'detail' => array('variance' => $variance, 'expected' => $exp['expected_total'])));
				flash('success', $verify ? "Settlement $date diverifikasi." : "Settlement $date disimpan sebagai draft.");
				redirect('finance/settlements/day/' . $date);
			}
			$row = array_merge($row ?: array(), $vals, array('notes' => $notes, 'status' => $row ? $row['status'] : 'draft'));
		}

		$this->render('finance/settlements/day', array(
			'title'  => 'Settlement ' . tgl($date, FALSE),
			'date'   => $date,
			'row'    => $row,
			'exp'    => $exp,
			'shifts' => $this->Settlement_model->shifts_on($date),
			'errors' => $errors,
		));
	}
}
