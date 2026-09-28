<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Purchase Order (PRD 2.2.1).
 */
class Orders extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->require_permission('purchase.view');
		$this->load->model(array('Purchase_model', 'Supplier_model', 'Ingredient_model'));
		$this->load->library('purchase_service', NULL, 'purchase');
	}

	public function index()
	{
		$date = function ($v) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? $v : ''; };
		$status = (string) $this->input->get('status');
		$f = array(
			'q'           => trim((string) $this->input->get('q')),
			'status'      => ($status === 'open' OR isset(Purchase_service::$po_status[$status])) ? $status : '',
			'supplier_id' => (int) $this->input->get('supplier_id'),
			'from'        => $date($this->input->get('from')),
			'to'          => $date($this->input->get('to')),
		);
		$page = max(1, (int) $this->input->get('page'));
		$total = $this->Purchase_model->count_orders($f);

		$this->render('purchase/orders/index', array(
			'title'     => 'Purchase Order',
			'rows'      => $this->Purchase_model->orders($f, 50, ($page - 1) * 50),
			'filters'   => $f,
			'suppliers' => $this->Supplier_model->options(),
			'page'      => $page,
			'pages'     => max(1, (int) ceil($total / 50)),
			'total'     => $total,
		));
	}

	public function show($id = NULL)
	{
		$po = $this->_po($id);
		$items = $this->Purchase_model->order_items($po['id']);
		$receipts = $this->Purchase_model->receipts_for_po($po['id']);
		foreach ($receipts as &$gr)
		{
			$gr['items'] = $this->Purchase_model->receipt_items($gr['id']);
		}
		unset($gr);

		// PO vs aktual (PRD: tandai kalau selisih > po_variance_flag %)
		$received_amount = array_sum(array_column($receipts, 'amount'));
		$variance_pct = (float) $po['total_amount'] > 0 && $received_amount > 0 && in_array($po['status'], array('received', 'closed'), TRUE)
			? ($received_amount - (float) $po['total_amount']) / (float) $po['total_amount'] * 100 : NULL;

		$this->render('purchase/orders/show', array(
			'title'           => 'PO ' . $po['po_number'],
			'po'              => $po,
			'items'           => $items,
			'receipts'        => $receipts,
			'invoices'        => $this->Purchase_model->invoices_for_po($po['id']),
			'history'         => $this->Purchase_model->history($po['id']),
			'received_amount' => $received_amount,
			'variance_pct'    => $variance_pct,
			'variance_flag'   => (float) setting('po_variance_flag', 5),
			'actions'         => $this->_actions($po),
		));
	}

	/** Versi cetak untuk dikirim ke supplier. */
	public function print_po($id = NULL)
	{
		$po = $this->_po($id);
		if (in_array($po['status'], array('draft', 'submitted', 'cancelled'), TRUE))
		{
			flash('warning', 'PO hanya bisa dicetak setelah disetujui.');
			redirect('purchase/orders/show/' . $po['id']);
		}
		$this->load->view('purchase/orders/print', array(
			'po'    => $po,
			'items' => $this->Purchase_model->order_items($po['id']),
			'resto' => setting('resto_name', 'Resto Moiz'),
		));
	}

	public function create()
	{
		$this->require_permission('purchase.create');
		$this->_form(NULL);
	}

	public function edit($id = NULL)
	{
		$this->require_permission('purchase.create');
		$po = $this->_po($id);
		if ($po['status'] !== 'draft')
		{
			flash('warning', 'Hanya PO draft yang bisa diubah.');
			redirect('purchase/orders/show/' . $po['id']);
		}
		$this->_form($po);
	}

	public function delete($id = NULL)
	{
		$this->require_permission('purchase.create');
		$this->_require_post();
		$po = $this->_po($id);
		if ($po['status'] !== 'draft' OR $po['submitted_at'])
		{
			flash('warning', 'Hanya PO draft yang belum pernah disubmit yang bisa dihapus. Gunakan "Batalkan".');
			redirect('purchase/orders/show/' . $po['id']);
		}
		$this->db->where('id', $po['id'])->delete('purchase_orders');
		$this->audit->log('po_delete', array('table_name' => 'purchase_orders', 'record_id' => $po['po_number']));
		flash('success', "PO {$po['po_number']} dihapus.");
		redirect('purchase/orders');
	}

	/** Aksi status: submit | approve | reject | cancel | close */
	public function action($id = NULL, $action = '')
	{
		$this->_require_post();
		$po = $this->_po($id);
		$allowed = $this->_actions($po);
		if ( ! in_array($action, array('submit', 'approve', 'reject', 'cancel', 'close'), TRUE) OR empty($allowed[$action]))
		{
			$this->audit->log('access_denied', array('result' => 'denied', 'detail' => "po_$action @ {$po['po_number']} (status {$po['status']})"));
			flash('danger', 'Aksi ini tidak tersedia untuk PO dengan status sekarang atau Anda tidak memiliki akses.');
			redirect('purchase/orders/show/' . $po['id']);
		}
		$note = trim((string) $this->input->post('note'));

		try
		{
			$result = $this->purchase->run(function ($svc) use ($po, $action, $note) {
				switch ($action)
				{
					case 'submit':  return $svc->submit($po['id']);
					case 'approve': return $svc->approve($po['id'], $note);
					case 'reject':  $svc->reject($po['id'], $note); return 'draft';
					case 'cancel':  $svc->cancel($po['id'], $note); return 'cancelled';
					case 'close':   $svc->close($po['id'], $note); return 'closed';
				}
			});
			$this->audit->log('po_' . $action, array('table_name' => 'purchase_orders', 'record_id' => $po['po_number'], 'detail' => array('status' => $result, 'note' => $note)));

			$messages = array(
				'submit'  => $result === 'approved' ? 'PO disubmit dan otomatis disetujui (di bawah batas approval).' : 'PO disubmit, menunggu approval.',
				'approve' => $result === 'approved' ? 'PO disetujui.' : 'Approval level 1 tercatat, menunggu approval Owner.',
				'reject'  => 'PO ditolak dan dikembalikan ke draft.',
				'cancel'  => 'PO dibatalkan.',
				'close'   => 'PO ditutup.',
			);
			flash('success', $messages[$action]);
		}
		catch (Purchase_exception $e)
		{
			flash('danger', $e->getMessage());
		}
		redirect('purchase/orders/show/' . $po['id']);
	}

	/**
	 * Aksi yang boleh dilakukan user saat ini terhadap PO (untuk tombol & validasi).
	 * Aturan detail (level, pemisahan tugas) tetap dicek di Purchase_service.
	 */
	protected function _actions(array $po)
	{
		$s = $po['status'];
		$has_gr = in_array($s, array('partial', 'received', 'closed'), TRUE);
		return array(
			'edit'    => $s === 'draft' && can('purchase.create'),
			'submit'  => $s === 'draft' && can('purchase.create'),
			'approve' => $s === 'submitted' && (
				( ! $po['approved1_by'] && can('purchase.approve')) OR ($po['approved1_by'] && can('purchase.approve_owner'))),
			'reject'  => $s === 'submitted' && can_any(array('purchase.approve', 'purchase.approve_owner')),
			'cancel'  => in_array($s, array('draft', 'submitted', 'approved'), TRUE) && can_any(array('purchase.create', 'purchase.approve')),
			'close'   => in_array($s, array('partial', 'received'), TRUE) && can_any(array('purchase.receive', 'purchase.create')),
			'receive' => in_array($s, array('approved', 'partial'), TRUE) && can('purchase.receive'),
			'invoice' => $has_gr && can('purchase.invoice'),
			'print'   => ! in_array($s, array('draft', 'submitted', 'cancelled'), TRUE),
			'delete'  => $s === 'draft' && ! $po['submitted_at'] && can('purchase.create'),
		);
	}

	protected function _form($po)
	{
		$is_edit = (bool) $po;
		$ingredients = $this->Ingredient_model->for_stock_form();
		$suppliers = $this->Supplier_model->options();
		$errors = array();

		if ($is_edit)
		{
			$header = $po;
			$lines = array_map(function ($i) {
				return array('ingredient_id' => $i['ingredient_id'], 'qty' => (float) $i['qty'], 'unit' => $i['unit'], 'cost' => (float) $i['unit_price'], 'notes' => $i['notes']);
			}, $this->Purchase_model->order_items($po['id']));
		}
		else
		{
			$supplier_id = (int) $this->input->get('supplier_id');
			$supplier = $supplier_id ? $this->Supplier_model->find($supplier_id) : NULL;
			$header = array(
				'supplier_id' => $supplier ? $supplier['id'] : '',
				'po_date' => date('Y-m-d'),
				'expected_delivery' => $supplier ? date('Y-m-d', strtotime('+' . max(1, (int) $supplier['lead_time_days']) . ' days')) : '',
				'delivery_address' => '', 'payment_terms' => $supplier ? $supplier['payment_terms'] : 'COD',
				'notes' => '', 'discount_amount' => 0, 'tax_amount' => 0,
			);
			$lines = $this->input->get('reorder') ? $this->_reorder_lines($ingredients, $supplier_id) : array();
		}

		if ($this->input->method() === 'post')
		{
			$date = function ($v) {
				$d = DateTime::createFromFormat('Y-m-d', (string) $v);
				return $d && $d->format('Y-m-d') === $v;
			};
			$header = array(
				'supplier_id'       => (int) $this->input->post('supplier_id'),
				'po_date'           => (string) $this->input->post('po_date'),
				'expected_delivery' => (string) $this->input->post('expected_delivery'),
				'delivery_address'  => trim((string) $this->input->post('delivery_address')),
				'payment_terms'     => (string) $this->input->post('payment_terms'),
				'notes'             => trim((string) $this->input->post('notes')),
				'discount_amount'   => num_in($this->input->post('discount_amount')),
				'tax_amount'        => num_in($this->input->post('tax_amount')),
			);
			if ( ! isset($suppliers[$header['supplier_id']]))
			{
				$errors[] = 'Pilih supplier (aktif).';
			}
			if ( ! $date($header['po_date']))
			{
				$errors[] = 'Tanggal PO tidak valid.';
			}
			if ($header['expected_delivery'] !== '' && ( ! $date($header['expected_delivery']) OR $header['expected_delivery'] < $header['po_date']))
			{
				$errors[] = 'Tanggal kirim tidak valid atau sebelum tanggal PO.';
			}
			if ( ! array_key_exists($header['payment_terms'], Supplier_model::$payment_terms))
			{
				$errors[] = 'Termin pembayaran tidak valid.';
			}
			foreach (array('discount_amount' => 'Diskon', 'tax_amount' => 'Pajak') as $k => $label)
			{
				if (is_nan($header[$k]) OR $header[$k] < 0)
				{
					$errors[] = "$label harus angka ≥ 0.";
					$header[$k] = 0;
				}
			}

			$lines = array();
			$prepared = array();
			foreach ((array) $this->input->post('lines') as $l)
			{
				$line = array(
					'ingredient_id' => (int) (isset($l['ingredient_id']) ? $l['ingredient_id'] : 0),
					'qty'   => isset($l['qty']) ? trim((string) $l['qty']) : '',
					'unit'  => isset($l['unit']) ? trim((string) $l['unit']) : '',
					'cost'  => isset($l['cost']) ? trim((string) $l['cost']) : '',
					'notes' => isset($l['notes']) ? trim((string) $l['notes']) : '',
				);
				if ( ! $line['ingredient_id'] && $line['qty'] === '')
				{
					continue;
				}
				$lines[] = $line;
				$n = count($lines);
				if ( ! isset($ingredients[$line['ingredient_id']]))
				{
					$errors[] = "Baris $n: pilih bahan baku.";
					continue;
				}
				$ing = $ingredients[$line['ingredient_id']];
				$qty = num_in($line['qty']);
				$price = num_in($line['cost'], NAN);
				$factor = NULL;
				foreach ($ing['units'] as $u)
				{
					if ($u['unit'] === $line['unit'])
					{
						$factor = $u['factor'];
					}
				}
				if (is_nan($qty) OR $qty <= 0)
				{
					$errors[] = "Baris $n ({$ing['name']}): jumlah harus lebih dari 0.";
				}
				elseif ($factor === NULL)
				{
					$errors[] = "Baris $n ({$ing['name']}): satuan tidak valid.";
				}
				elseif (is_nan($price) OR $price < 0)
				{
					$errors[] = "Baris $n ({$ing['name']}): harga per {$line['unit']} wajib diisi.";
				}
				else
				{
					$prepared[] = array('ingredient_id' => $ing['id'], 'qty' => round($qty, 3), 'unit' => $line['unit'], 'factor' => $factor,
						'unit_price' => round($price, 2), 'notes' => mb_substr($line['notes'], 0, 255));
				}
			}
			if (empty($lines))
			{
				$errors[] = 'Isi minimal satu bahan.';
			}

			if (empty($errors))
			{
				try
				{
					$submit = $this->input->post('action') === 'submit';
					$result = $this->purchase->run(function ($svc) use ($header, $prepared, $po, $submit) {
						$id = $svc->save_po($header, $prepared, $po ? $po['id'] : NULL);
						return array($id, $submit ? $svc->submit($id) : 'draft');
					});
					list($id, $status) = $result;
					$saved = $this->Purchase_model->find_order($id);
					$this->audit->log($is_edit ? 'po_update' : 'po_create', array('table_name' => 'purchase_orders', 'record_id' => $saved['po_number'],
						'detail' => array('total' => $saved['total_amount'], 'status' => $status)));
					flash('success', "PO {$saved['po_number']} disimpan" . ($submit ? ($status === 'approved' ? ' dan otomatis disetujui.' : ' dan disubmit untuk approval.') : ' sebagai draft.'));
					redirect('purchase/orders/show/' . $id);
				}
				catch (Purchase_exception $e)
				{
					$errors[] = $e->getMessage();
				}
			}
		}

		if (empty($lines))
		{
			$lines[] = array('ingredient_id' => '', 'qty' => '', 'unit' => '', 'cost' => '', 'notes' => '');
		}

		$this->render('purchase/orders/form', array(
			'title'       => $is_edit ? 'Edit PO ' . $po['po_number'] : 'Buat Purchase Order',
			'po'          => $po,
			'header'      => $header,
			'lines'       => $lines,
			'ingredients' => $ingredients,
			'suppliers'   => $suppliers,
			'terms'       => Supplier_model::$payment_terms,
			'errors'      => $errors,
			'auto_limit'  => (float) setting('po_auto_limit', 5000000),
			'owner_limit' => (float) setting('po_owner_limit', 20000000),
		));
	}

	/**
	 * Baris PO dari bahan yang sudah mencapai reorder point / di bawah minimum
	 * (opsional hanya yang supplier utamanya = $supplier_id), dengan harga terakhir.
	 */
	protected function _reorder_lines(array $ingredients, $supplier_id)
	{
		$this->load->model('Stock_model');
		$alerts = $this->Stock_model->alerts();
		$lines = array();
		foreach (array_merge($alerts['low'], $alerts['reorder']) as $r)
		{
			if ( ! isset($ingredients[$r['id']]) OR ($supplier_id && (int) $ingredients[$r['id']]['default_supplier_id'] !== $supplier_id))
			{
				continue;
			}
			$qty = $r['reorder_qty'] > 0 ? (float) $r['reorder_qty'] : ($r['max_stock'] > 0 ? (float) $r['max_stock'] - (float) $r['qty_on_hand'] : 0);
			if ($qty <= 0)
			{
				continue;
			}
			$lines[$r['id']] = array('ingredient_id' => $r['id'], 'qty' => round($qty, 3), 'unit' => $ingredients[$r['id']]['unit'],
				'cost' => round((float) $ingredients[$r['id']]['current_price'], 2) ?: '', 'notes' => '');
		}
		return array_values($lines);
	}

	protected function _po($id)
	{
		$po = $this->Purchase_model->find_order($id);
		if ( ! $po)
		{
			show_404();
		}
		return $po;
	}

	protected function _require_post()
	{
		if ($this->input->method() !== 'post')
		{
			show_error('Method not allowed', 405);
		}
	}
}
