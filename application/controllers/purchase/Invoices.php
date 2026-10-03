<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Invoice supplier & 3-way matching (PRD 2.2.2 - 2.2.3).
 */
class Invoices extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->require_permission('purchase.view');
		$this->load->model(array('Purchase_model', 'Supplier_model'));
		$this->load->library('purchase_service', NULL, 'purchase');
	}

	public function index()
	{
		$status = (string) $this->input->get('status');
		$payment = (string) $this->input->get('payment');
		$f = array(
			'q'           => trim((string) $this->input->get('q')),
			'status'      => isset(Purchase_service::$invoice_status[$status]) ? $status : '',
			'payment'     => in_array($payment, array('unpaid', 'partial', 'paid', 'outstanding'), TRUE) ? $payment : '',
			'supplier_id' => (int) $this->input->get('supplier_id'),
		);
		$this->render('purchase/invoices/index', array(
			'title'     => 'Invoice Supplier',
			'rows'      => $this->Purchase_model->invoices($f, 300),
			'uninvoiced'=> can('purchase.invoice') ? $this->Purchase_model->uninvoiced_orders() : array(),
			'filters'   => $f,
			'suppliers' => $this->Supplier_model->options(),
		));
	}

	public function show($id = NULL)
	{
		$inv = $this->Purchase_model->find_invoice($id);
		if ( ! $inv)
		{
			show_404();
		}
		// Kondisi pencocokan terkini (barang bisa bertambah diterima setelah invoice dicatat).
		$a = $this->purchase->po_amounts($inv['po_id'], $inv['id']);
		$now_total = $a['invoiced'] + (float) $inv['amount'];

		$this->render('purchase/invoices/show', array(
			'title'      => 'Invoice ' . $inv['invoice_number'],
			'inv'        => $inv,
			'gr_amount'  => $a['gr_amount'],
			'other_inv'  => $a['invoiced'],
			'match_now'  => $this->purchase->match_status($a['gr_amount'], $now_total),
			'payments'   => $this->Purchase_model->payments(array('invoice_id' => $inv['id'])),
			'payable'    => $this->purchase->payable($inv),
			'tolerance'  => (float) setting('invoice_match_tolerance', 1),
		));
	}

	public function create($po_id = NULL)
	{
		$this->require_permission('purchase.invoice');
		$po = $this->Purchase_model->find_order($po_id);
		if ( ! $po)
		{
			show_404();
		}
		$a = $this->purchase->po_amounts($po['id']);
		$errors = array();
		$input = array('invoice_number' => '', 'invoice_date' => date('Y-m-d'),
			'amount' => max(0, round($a['gr_amount'] - $a['invoiced'], 2)), 'notes' => '');

		if ($this->input->method() === 'post')
		{
			$input = array(
				'invoice_number' => trim((string) $this->input->post('invoice_number')),
				'invoice_date'   => (string) $this->input->post('invoice_date'),
				'amount'         => num_in($this->input->post('amount')),
				'notes'          => trim((string) $this->input->post('notes')),
			);
			if ($input['invoice_number'] === '' OR mb_strlen($input['invoice_number']) > 50)
			{
				$errors[] = 'Nomor invoice wajib diisi (maks. 50 karakter).';
			}
			$d = DateTime::createFromFormat('Y-m-d', $input['invoice_date']);
			if ( ! $d OR $d->format('Y-m-d') !== $input['invoice_date'])
			{
				$errors[] = 'Tanggal invoice tidak valid.';
			}
			if (is_nan($input['amount']) OR $input['amount'] <= 0)
			{
				$errors[] = 'Nilai invoice harus lebih dari 0.';
				$input['amount'] = 0;
			}

			$this->load->library('private_upload');
			$attachment = empty($errors) ? $this->private_upload->save('attachment', 'invoices', $errors) : NULL;

			if (empty($errors))
			{
				try
				{
					$input['attachment_path'] = $attachment;
					$data = $input;
					$data['amount'] = round($data['amount'], 2);
					$id = $this->purchase->run(function ($svc) use ($po, $data) {
						return $svc->record_invoice($po['id'], $data);
					});
					$inv = $this->Purchase_model->find_invoice($id);
					$this->audit->log('invoice_create', array('table_name' => 'supplier_invoices', 'record_id' => $id,
						'detail' => array('invoice' => $inv['invoice_number'], 'po' => $po['po_number'], 'amount' => $inv['amount'], 'match' => $inv['match_status'])));
					flash($inv['match_status'] === 'matched' ? 'success' : 'warning', "Invoice {$inv['invoice_number']} dicatat" .
						($inv['match_status'] === 'matched' ? ' dan cocok dengan barang diterima.' : '. PERHATIAN: nilai tidak cocok dengan barang diterima.'));
					redirect('purchase/invoices/show/' . $id);
				}
				catch (Purchase_exception $e)
				{
					$this->private_upload->delete($attachment);
					$errors[] = $e->getMessage();
				}
			}
		}

		$this->render('purchase/invoices/form', array(
			'title'  => 'Catat Invoice: ' . $po['po_number'],
			'po'     => $po,
			'input'  => $input,
			'amounts'=> $a,
			'errors' => $errors,
			'tolerance' => (float) setting('invoice_match_tolerance', 1),
		));
	}

	public function review($id = NULL)
	{
		$this->require_permission('purchase.invoice');
		if ($this->input->method() !== 'post')
		{
			show_error('Method not allowed', 405);
		}
		$inv = $this->Purchase_model->find_invoice($id);
		if ( ! $inv)
		{
			show_404();
		}
		$action = (string) $this->input->post('action');
		$note = trim((string) $this->input->post('note'));
		try
		{
			$status = $this->purchase->run(function ($svc) use ($inv, $action, $note) {
				return $svc->review_invoice($inv['id'], $action, $note);
			});
			$this->audit->log('invoice_' . $action, array('table_name' => 'supplier_invoices', 'record_id' => $inv['id'],
				'detail' => array('invoice' => $inv['invoice_number'], 'status' => $status, 'note' => $note)));
			flash('success', 'Invoice ' . Purchase_service::$invoice_status[$status][0] . '.');
		}
		catch (Purchase_exception $e)
		{
			flash('danger', $e->getMessage());
		}
		redirect('purchase/invoices/show/' . $inv['id']);
	}
}
