<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pembayaran supplier & verifikasi (PRD 2.2.4).
 */
class Payments extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->require_permission('purchase.view');
		$this->load->model('Purchase_model');
		$this->load->library('purchase_service', NULL, 'purchase');
	}

	public function index()
	{
		$status = (string) $this->input->get('status');
		if ($this->input->get('status') === NULL)
		{
			$status = 'pending';
		}
		$f = array('status' => isset(Purchase_service::$payment_status[$status]) ? $status : '');
		$this->render('purchase/payments/index', array(
			'title'   => 'Pembayaran Supplier',
			'rows'    => $this->Purchase_model->payments($f, 300),
			'filters' => $f,
			'aging'   => $this->Purchase_model->ap_aging(),
			// Invoice disetujui yang belum lunas (urut jatuh tempo) + invoice yang masih menunggu review.
			'to_pay'  => can('purchase.payment') ? $this->_by_due($this->Purchase_model->invoices(array('payment' => 'outstanding'), 200)) : array(),
			'to_review' => count($this->Purchase_model->invoices(array('status' => 'pending'), 500)),
		));
	}

	private function _by_due(array $rows)
	{
		usort($rows, function ($a, $b) { return strcmp((string) $a['due_date'], (string) $b['due_date']); });
		return $rows;
	}

	public function create($invoice_id = NULL)
	{
		$this->require_permission('purchase.payment');
		$inv = $this->Purchase_model->find_invoice($invoice_id);
		if ( ! $inv)
		{
			show_404();
		}
		if ($inv['status'] !== 'approved')
		{
			flash('warning', 'Invoice harus disetujui dulu sebelum dibayar.');
			redirect('purchase/invoices/show/' . $inv['id']);
		}
		$payable = $this->purchase->payable($inv);
		if ($payable <= 0)
		{
			flash('info', 'Tidak ada sisa tagihan yang bisa diajukan untuk invoice ini.');
			redirect('purchase/invoices/show/' . $inv['id']);
		}

		$errors = array();
		$input = array('payment_date' => date('Y-m-d'), 'amount' => $payable, 'method' => 'transfer', 'bank_name' => '', 'account_no' => '',
			'reference_no' => '', 'card_last4' => '', 'auth_code' => '', 'check_no' => '', 'check_date' => '', 'notes' => '');

		if ($this->input->method() === 'post')
		{
			foreach (array_keys($input) as $k)
			{
				$input[$k] = trim((string) $this->input->post($k));
			}
			$input['amount'] = num_in($input['amount']);
			$valid_date = function ($v) {
				$d = DateTime::createFromFormat('Y-m-d', $v);
				return $d && $d->format('Y-m-d') === $v;
			};

			if ( ! $valid_date($input['payment_date']) OR $input['payment_date'] > date('Y-m-d'))
			{
				$errors[] = 'Tanggal bayar tidak valid atau di masa depan.';
			}
			if (is_nan($input['amount']) OR $input['amount'] <= 0)
			{
				$errors[] = 'Jumlah bayar harus lebih dari 0.';
				$input['amount'] = 0;
			}
			if ( ! isset(Purchase_service::$methods[$input['method']]))
			{
				$errors[] = 'Pilih metode pembayaran.';
			}
			switch ($input['method'])
			{
				case 'transfer':
					if ($input['bank_name'] === '' OR $input['reference_no'] === '')
					{
						$errors[] = 'Transfer: isi nama bank dan nomor referensi.';
					}
					break;
				case 'credit_card':
					// Hanya 4 digit terakhir yang disimpan (tidak menyimpan nomor kartu penuh).
					if ( ! preg_match('/^\d{4}$/', $input['card_last4']) OR $input['auth_code'] === '')
					{
						$errors[] = 'Kartu kredit: isi 4 digit terakhir kartu dan kode otorisasi.';
					}
					break;
				case 'check':
					if ($input['check_no'] === '' OR ! $valid_date($input['check_date']))
					{
						$errors[] = 'Cek/giro: isi nomor dan tanggal cek.';
					}
					break;
			}
			foreach (array('bank_name' => 50, 'account_no' => 50, 'reference_no' => 100, 'auth_code' => 20, 'check_no' => 50, 'notes' => 255) as $k => $max)
			{
				if (mb_strlen($input[$k]) > $max)
				{
					$errors[] = "Isian terlalu panjang (maks. $max karakter).";
				}
			}

			$this->load->library('private_upload');
			$proof = empty($errors) ? $this->private_upload->save('proof', 'payments', $errors) : NULL;
			if (empty($errors) && ! $proof && $input['method'] !== 'cash')
			{
				$errors[] = 'Upload bukti pembayaran (screenshot transfer / slip).';
			}

			if (empty($errors))
			{
				try
				{
					$data = $input;
					$data['amount'] = round($data['amount'], 2);
					$data['proof_path'] = $proof;
					// Simpan hanya field yang relevan dengan metode.
					$keep = array('transfer' => array('bank_name', 'account_no', 'reference_no'), 'credit_card' => array('card_last4', 'auth_code'),
						'check' => array('check_no', 'check_date', 'bank_name'), 'cash' => array());
					foreach (array('bank_name', 'account_no', 'reference_no', 'card_last4', 'auth_code', 'check_no', 'check_date') as $k)
					{
						if ( ! in_array($k, $keep[$data['method']], TRUE))
						{
							$data[$k] = '';
						}
					}
					list($pid, $number) = $this->purchase->run(function ($svc) use ($inv, $data) {
						return $svc->record_payment($inv['id'], $data);
					});
					$this->audit->log('payment_create', array('table_name' => 'supplier_payments', 'record_id' => $number,
						'detail' => array('invoice' => $inv['invoice_number'], 'amount' => $data['amount'], 'method' => $data['method'])));
					flash('success', "Pembayaran $number dicatat, menunggu verifikasi.");
					redirect('purchase/invoices/show/' . $inv['id']);
				}
				catch (Purchase_exception $e)
				{
					$this->private_upload->delete($proof);
					$errors[] = $e->getMessage();
				}
			}
		}

		$this->render('purchase/payments/form', array(
			'title'   => 'Bayar Invoice ' . $inv['invoice_number'],
			'inv'     => $inv,
			'payable' => $payable,
			'input'   => $input,
			'errors'  => $errors,
		));
	}

	/** verify | reject */
	public function process($id = NULL)
	{
		$this->require_permission('purchase.payment');
		if ($this->input->method() !== 'post')
		{
			show_error('Method not allowed', 405);
		}
		$p = $this->Purchase_model->find_payment($id);
		if ( ! $p)
		{
			show_404();
		}
		$action = (string) $this->input->post('action');
		$reason = trim((string) $this->input->post('reason'));
		try
		{
			$this->purchase->run(function ($svc) use ($p, $action, $reason) {
				if ($action === 'verify')
				{
					$svc->verify_payment($p['id']);
				}
				elseif ($action === 'reject')
				{
					$svc->reject_payment($p['id'], $reason);
				}
				else
				{
					throw new Purchase_exception('Aksi tidak valid.');
				}
			});
			$this->audit->log('payment_' . $action, array('table_name' => 'supplier_payments', 'record_id' => $p['payment_number'],
				'detail' => array('amount' => $p['amount'], 'reason' => $reason)));
			flash('success', $action === 'verify' ? "Pembayaran {$p['payment_number']} terverifikasi." : "Pembayaran {$p['payment_number']} ditolak.");
		}
		catch (Purchase_exception $e)
		{
			flash('danger', $e->getMessage());
		}
		redirect($this->input->post('back') === 'list' ? 'purchase/payments' : 'purchase/invoices/show/' . $p['invoice_id']);
	}
}
