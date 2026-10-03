<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Penerimaan barang / Goods Receipt (PRD 2.2.2).
 */
class Receipts extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->require_permission('purchase.view');
		$this->load->model('Purchase_model');
		$this->load->library('purchase_service', NULL, 'purchase'); // label status PO & aturan penerimaan
	}

	public function index()
	{
		$date = function ($v, $d) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? $v : $d; };
		$f = array(
			'from' => $date($this->input->get('from'), date('Y-m-d', strtotime('-30 days'))),
			'to'   => $date($this->input->get('to'), date('Y-m-d')),
		);
		$this->render('purchase/receipts/index', array(
			'title'   => 'Penerimaan Barang',
			'rows'    => $this->Purchase_model->receipts($f, 200),
			'waiting' => $this->Purchase_model->orders(array('status' => 'open'), 100),
			'filters' => $f,
		));
	}

	/** Form terima barang untuk satu PO. */
	public function create($po_id = NULL)
	{
		$this->require_permission('purchase.receive');
		$po = $this->Purchase_model->find_order($po_id);
		if ( ! $po)
		{
			show_404();
		}
		if ( ! in_array($po['status'], array('approved', 'partial'), TRUE))
		{
			flash('warning', 'Barang hanya bisa diterima untuk PO yang sudah disetujui dan belum lengkap.');
			redirect('purchase/orders/show/' . $po['id']);
		}
		$items = $this->Purchase_model->order_items($po['id']);
		$errors = array();
		$header = array('received_date' => date('Y-m-d'), 'delivery_note_no' => '', 'notes' => '');
		$input = array();

		if ($this->input->method() === 'post')
		{
			$header = array(
				'received_date'    => (string) $this->input->post('received_date'),
				'delivery_note_no' => trim((string) $this->input->post('delivery_note_no')),
				'notes'            => trim((string) $this->input->post('notes')),
			);
			$d = DateTime::createFromFormat('Y-m-d', $header['received_date']);
			if ( ! $d OR $d->format('Y-m-d') !== $header['received_date'] OR $header['received_date'] > date('Y-m-d'))
			{
				$errors[] = 'Tanggal terima tidak valid atau di masa depan.';
			}

			$posted = (array) $this->input->post('lines');
			$lines = array();
			foreach ($items as $it)
			{
				$l = isset($posted[$it['id']]) ? (array) $posted[$it['id']] : array();
				$input[$it['id']] = array(
					'qty'             => isset($l['qty']) ? trim((string) $l['qty']) : '',
					'variance_reason' => isset($l['variance_reason']) ? trim((string) $l['variance_reason']) : '',
					'expiry_date'     => isset($l['expiry_date']) ? trim((string) $l['expiry_date']) : '',
				);
				$qty = num_in($input[$it['id']]['qty']);
				if (is_nan($qty) OR $qty < 0)
				{
					$errors[] = "{$it['name']}: jumlah diterima harus angka ≥ 0.";
					continue;
				}
				$exp = $input[$it['id']]['expiry_date'];
				if ($exp !== '')
				{
					$e = DateTime::createFromFormat('Y-m-d', $exp);
					if ( ! $e OR $e->format('Y-m-d') !== $exp)
					{
						$errors[] = "{$it['name']}: tanggal kedaluwarsa tidak valid.";
						continue;
					}
				}
				if ($qty > 0)
				{
					$lines[$it['id']] = array('qty' => $qty, 'variance_reason' => $input[$it['id']]['variance_reason'], 'expiry_date' => $exp);
				}
			}

			$this->load->library('private_upload');
			$attachment = NULL;
			if (empty($errors))
			{
				$attachment = $this->private_upload->save('attachment', 'receipts', $errors);
			}

			if (empty($errors))
			{
				try
				{
					$header['attachment_path'] = $attachment;
					list($gr_id, $gr_number) = $this->purchase->run(function ($svc) use ($po, $header, $lines) {
						return $svc->receive($po['id'], $header, $lines);
					});
					$this->audit->log('gr_create', array('table_name' => 'goods_receipts', 'record_id' => $gr_number, 'detail' => array('po' => $po['po_number'], 'lines' => count($lines))));
					flash('success', "Penerimaan $gr_number tersimpan, stok sudah bertambah. Langkah berikutnya: catat invoice supplier (menu Invoice Supplier) agar hutang & jatuh tempo terhitung.");
					redirect('purchase/orders/show/' . $po['id'] . '#gr-' . $gr_id);
				}
				catch (Exception $e)
				{
					if ( ! ($e instanceof Purchase_exception) && ! ($e instanceof Stock_exception))
					{
						throw $e;
					}
					$this->private_upload->delete($attachment);
					$errors[] = $e->getMessage();
				}
			}
		}

		$this->render('purchase/receipts/form', array(
			'title'  => 'Terima Barang: ' . $po['po_number'],
			'po'     => $po,
			'items'  => $items,
			'header' => $header,
			'input'  => $input,
			'errors' => $errors,
		));
	}
}
