<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Purchase_exception extends RuntimeException {}

/**
 * Aturan bisnis pembelian (PRD 2.2). Semua perubahan status PO, penerimaan
 * barang, invoice, dan pembayaran lewat library ini supaya aturan approval,
 * pemisahan tugas, dan pencatatan stok konsisten.
 *
 * Approval PO (nilai total, batas di Pengaturan):
 *   < po_auto_limit                 -> otomatis disetujui saat submit
 *   po_auto_limit .. po_owner_limit -> 1 level  (purchase.approve)
 *   > po_owner_limit                -> 2 level  (purchase.approve lalu purchase.approve_owner)
 *
 * Pemisahan tugas (kecuali Admin): pembuat PO tidak boleh meng-approve PO-nya
 * sendiri, approver level 2 harus orang yang berbeda dari level 1, dan
 * pembayaran diverifikasi oleh orang lain dari yang menginput.
 */
class Purchase_service {

	public static $po_status = array(
		'draft'     => array('Draft', 'secondary'),
		'submitted' => array('Menunggu approval', 'warning'),
		'approved'  => array('Disetujui', 'primary'),
		'partial'   => array('Diterima sebagian', 'info'),
		'received'  => array('Diterima lengkap', 'success'),
		'closed'    => array('Ditutup', 'dark'),
		'cancelled' => array('Dibatalkan', 'light'),
	);

	public static $invoice_status = array(
		'pending'  => array('Menunggu review', 'warning'),
		'approved' => array('Disetujui', 'success'),
		'on_hold'  => array('Ditahan', 'secondary'),
		'rejected' => array('Ditolak', 'danger'),
	);

	public static $payment_status = array(
		'pending'  => array('Menunggu verifikasi', 'warning'),
		'verified' => array('Terverifikasi', 'success'),
		'rejected' => array('Ditolak', 'danger'),
	);

	public static $methods = array(
		'transfer'    => 'Transfer bank',
		'cash'        => 'Tunai',
		'credit_card' => 'Kartu kredit',
		'check'       => 'Cek / giro',
	);

	protected $CI;
	protected $db;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->db = $this->CI->db;
		$this->CI->load->library('sequence');
		$this->CI->load->library('stock_service', NULL, 'stock');
	}

	/** Jalankan dalam transaksi; rollback kalau ada exception. */
	public function run(callable $fn)
	{
		$this->db->trans_begin();
		try
		{
			$result = $fn($this);
			if ($this->db->trans_status() === FALSE)
			{
				throw new Purchase_exception('Gagal menyimpan ke database.');
			}
			$this->db->trans_commit();
			return $result;
		}
		catch (Exception $e)
		{
			$this->db->trans_rollback();
			throw $e;
		}
	}

	public function required_level($total)
	{
		if ($total < (float) setting('po_auto_limit', 5000000))
		{
			return 0;
		}
		return $total > (float) setting('po_owner_limit', 20000000) ? 2 : 1;
	}

	// ------------------------------------------------------------------
	// Purchase Order
	// ------------------------------------------------------------------

	/**
	 * Simpan PO draft (baru atau ubah).
	 * @param array $h     supplier_id, po_date, expected_delivery, delivery_address, payment_terms, notes, discount_amount, tax_amount
	 * @param array $lines [ingredient_id, qty, unit, factor, unit_price, notes]
	 */
	public function save_po(array $h, array $lines, $po_id = NULL)
	{
		if (empty($lines))
		{
			throw new Purchase_exception('PO minimal berisi satu bahan.');
		}
		$subtotal = 0.0;
		foreach ($lines as &$l)
		{
			$l['subtotal'] = round($l['qty'] * $l['unit_price'], 2);
			$l['qty_std'] = round($l['qty'] * $l['factor'], 3);
			$subtotal += $l['subtotal'];
		}
		unset($l);
		$subtotal = round($subtotal, 2);
		if ($h['discount_amount'] > $subtotal)
		{
			throw new Purchase_exception('Diskon tidak boleh melebihi subtotal.');
		}
		$total = round($subtotal - $h['discount_amount'] + $h['tax_amount'], 2);

		$row = array(
			'supplier_id'       => (int) $h['supplier_id'],
			'po_date'           => $h['po_date'],
			'expected_delivery' => $h['expected_delivery'] ?: NULL,
			'delivery_address'  => $h['delivery_address'] ?: NULL,
			'payment_terms'     => $h['payment_terms'],
			'notes'             => $h['notes'] ?: NULL,
			'subtotal'          => $subtotal,
			'discount_amount'   => $h['discount_amount'],
			'tax_amount'        => $h['tax_amount'],
			'total_amount'      => $total,
		);

		if ($po_id)
		{
			$po = $this->_lock_po($po_id);
			if ($po['status'] !== 'draft')
			{
				throw new Purchase_exception('Hanya PO berstatus draft yang bisa diubah.');
			}
			$this->db->where('id', $po['id'])->update('purchase_orders', $row);
			$this->db->where('po_id', $po['id'])->delete('purchase_order_items');
		}
		else
		{
			$row['po_number'] = $this->CI->sequence->next('PO', date('ym', strtotime($h['po_date'])), 4);
			$row['created_by'] = $this->_uid();
			$this->db->insert('purchase_orders', $row);
			$po_id = (int) $this->db->insert_id();
			$this->_history($po_id, 'created');
		}

		foreach ($lines as $l)
		{
			$this->db->insert('purchase_order_items', array(
				'po_id'         => $po_id,
				'ingredient_id' => (int) $l['ingredient_id'],
				'qty'           => $l['qty'],
				'unit'          => $l['unit'],
				'factor'        => $l['factor'],
				'qty_std'       => $l['qty_std'],
				'unit_price'    => $l['unit_price'],
				'subtotal'      => $l['subtotal'],
				'notes'         => $l['notes'] ?: NULL,
			));
		}
		return (int) $po_id;
	}

	/** Submit PO draft. Mengembalikan status baru ('approved' kalau auto-approve). */
	public function submit($po_id)
	{
		$po = $this->_lock_po($po_id);
		if ($po['status'] !== 'draft')
		{
			throw new Purchase_exception('PO ini sudah disubmit.');
		}
		if ((int) $this->db->where('po_id', $po['id'])->count_all_results('purchase_order_items') === 0)
		{
			throw new Purchase_exception('PO belum punya item.');
		}
		$level = $this->required_level((float) $po['total_amount']);
		$now = date('Y-m-d H:i:s');
		$update = array('approval_level' => $level, 'submitted_at' => $now,
			'approved1_by' => NULL, 'approved1_at' => NULL, 'approved2_by' => NULL, 'approved2_at' => NULL);

		if ($level === 0)
		{
			$update['status'] = 'approved';
			$this->_history($po['id'], 'submitted');
			$this->_history($po['id'], 'auto_approved', 'Nilai di bawah ' . rupiah(setting('po_auto_limit', 5000000)));
		}
		else
		{
			$update['status'] = 'submitted';
			$this->_history($po['id'], 'submitted', $level === 2 ? 'Butuh approval Manajer + Owner' : 'Butuh approval Manajer');
		}
		$this->db->where('id', $po['id'])->update('purchase_orders', $update);
		return $update['status'];
	}

	/**
	 * Approve level berikutnya yang masih kosong.
	 * @return string status baru
	 */
	public function approve($po_id, $note = '')
	{
		$po = $this->_lock_po($po_id);
		if ($po['status'] !== 'submitted')
		{
			throw new Purchase_exception('PO ini tidak sedang menunggu approval.');
		}
		$uid = $this->_uid();
		$super = $this->CI->rbac->is_super();
		if ( ! $super && (int) $po['created_by'] === $uid)
		{
			throw new Purchase_exception('Anda tidak bisa meng-approve PO yang Anda buat sendiri.');
		}
		$now = date('Y-m-d H:i:s');

		if ( ! $po['approved1_by'])
		{
			if ( ! $this->CI->rbac->has_permission('purchase.approve'))
			{
				throw new Purchase_exception('Approval level 1 membutuhkan permission purchase.approve.');
			}
			$update = array('approved1_by' => $uid, 'approved1_at' => $now);
			if ((int) $po['approval_level'] <= 1)
			{
				$update['status'] = 'approved';
			}
			$this->db->where('id', $po['id'])->update('purchase_orders', $update);
			$this->_history($po['id'], 'approved_1', $note);
			return isset($update['status']) ? 'approved' : 'submitted';
		}

		if ( ! $this->CI->rbac->has_permission('purchase.approve_owner'))
		{
			throw new Purchase_exception('PO di atas ' . rupiah(setting('po_owner_limit', 20000000)) . ' membutuhkan approval Owner (purchase.approve_owner).');
		}
		if ( ! $super && (int) $po['approved1_by'] === $uid)
		{
			throw new Purchase_exception('Approval level 2 harus oleh orang yang berbeda dari approver level 1.');
		}
		$this->db->where('id', $po['id'])->update('purchase_orders', array('approved2_by' => $uid, 'approved2_at' => $now, 'status' => 'approved'));
		$this->_history($po['id'], 'approved_2', $note);
		return 'approved';
	}

	/** Tolak: PO kembali ke draft (PRD 2.2.1) dengan alasan. */
	public function reject($po_id, $reason)
	{
		$po = $this->_lock_po($po_id);
		if ($po['status'] !== 'submitted')
		{
			throw new Purchase_exception('PO ini tidak sedang menunggu approval.');
		}
		if (trim($reason) === '')
		{
			throw new Purchase_exception('Alasan penolakan wajib diisi.');
		}
		$this->db->where('id', $po['id'])->update('purchase_orders', array(
			'status' => 'draft', 'approved1_by' => NULL, 'approved1_at' => NULL, 'approved2_by' => NULL, 'approved2_at' => NULL,
		));
		$this->_history($po['id'], 'rejected', $reason);
	}

	/** Batalkan PO yang belum ada penerimaan barang. */
	public function cancel($po_id, $reason)
	{
		$po = $this->_lock_po($po_id);
		if ( ! in_array($po['status'], array('draft', 'submitted', 'approved'), TRUE))
		{
			throw new Purchase_exception('PO yang sudah ada penerimaan barang tidak bisa dibatalkan. Gunakan "Tutup PO".');
		}
		if (trim($reason) === '')
		{
			throw new Purchase_exception('Alasan pembatalan wajib diisi.');
		}
		$this->db->where('id', $po['id'])->update('purchase_orders', array(
			'status' => 'cancelled', 'closed_by' => $this->_uid(), 'closed_at' => date('Y-m-d H:i:s'), 'close_reason' => $reason,
		));
		$this->_history($po['id'], 'cancelled', $reason);
	}

	/** Tutup PO: sisa yang belum diterima tidak akan dikirim / arsipkan. */
	public function close($po_id, $reason)
	{
		$po = $this->_lock_po($po_id);
		if ( ! in_array($po['status'], array('partial', 'received'), TRUE))
		{
			throw new Purchase_exception('Hanya PO yang sudah ada penerimaan barang yang bisa ditutup.');
		}
		if ($po['status'] === 'partial' && trim($reason) === '')
		{
			throw new Purchase_exception('Alasan wajib diisi untuk menutup PO yang belum diterima lengkap.');
		}
		$this->db->where('id', $po['id'])->update('purchase_orders', array(
			'status' => 'closed', 'closed_by' => $this->_uid(), 'closed_at' => date('Y-m-d H:i:s'), 'close_reason' => $reason ?: NULL,
		));
		$this->_history($po['id'], 'closed', $reason);
	}

	// ------------------------------------------------------------------
	// Goods Receipt
	// ------------------------------------------------------------------

	/**
	 * Terima barang untuk PO. Stok masuk lewat Stock_service (FIFO) dengan
	 * harga PO setelah diskon (pajak tidak masuk nilai persediaan).
	 *
	 * @param array $h     received_date, delivery_note_no, notes, attachment_path
	 * @param array $lines [po_item_id => [qty (satuan PO), variance_reason, expiry_date]]
	 * @return array [gr_id, gr_number]
	 */
	public function receive($po_id, array $h, array $lines)
	{
		$po = $this->_lock_po($po_id);
		if ( ! in_array($po['status'], array('approved', 'partial'), TRUE))
		{
			throw new Purchase_exception('Barang hanya bisa diterima untuk PO yang sudah disetujui.');
		}
		$items = $this->db->query('SELECT i.*, g.name AS ingredient_name, g.unit AS std_unit FROM purchase_order_items i
			JOIN ingredients g ON g.id = i.ingredient_id WHERE i.po_id = ? FOR UPDATE', array($po['id']))->result_array();

		// Diskon dialokasikan proporsional ke harga barang; nilai untuk
		// pencocokan invoice mengikuti total PO (termasuk pajak).
		$subtotal = (float) $po['subtotal'];
		$cost_ratio = $subtotal > 0 ? ($subtotal - (float) $po['discount_amount']) / $subtotal : 1;
		$invoice_ratio = $subtotal > 0 ? (float) $po['total_amount'] / $subtotal : 1;

		$gr_number = $this->CI->sequence->next('GR', date('ym', strtotime($h['received_date'])), 4);
		$movement_no = $this->CI->stock->new_document_no();
		$this->db->insert('goods_receipts', array(
			'gr_number'        => $gr_number,
			'po_id'            => $po['id'],
			'received_date'    => $h['received_date'],
			'delivery_note_no' => $h['delivery_note_no'] ?: NULL,
			'notes'            => $h['notes'] ?: NULL,
			'attachment_path'  => $h['attachment_path'] ?: NULL,
			'movement_no'      => $movement_no,
			'received_by'      => $this->_uid(),
		));
		$gr_id = (int) $this->db->insert_id();

		$received_any = FALSE;
		$gr_amount = 0.0;
		foreach ($items as $it)
		{
			if ( ! isset($lines[$it['id']]))
			{
				continue;
			}
			$l = $lines[$it['id']];
			$qty = round((float) $l['qty'], 3);
			if ($qty <= 0)
			{
				continue;
			}
			$remaining_std = round((float) $it['qty_std'] - (float) $it['qty_received_std'], 3);
			$qty_std = round($qty * (float) $it['factor'], 3);
			if ($qty_std > $remaining_std + 0.0005)
			{
				throw new Purchase_exception(sprintf('%s: diterima %s %s melebihi sisa PO %s %s.', $it['ingredient_name'],
					qty($qty), $it['unit'], qty($remaining_std / (float) $it['factor']), $it['unit']));
			}
			$reason = trim((string) $l['variance_reason']);
			if (abs($qty_std - $remaining_std) > 0.0005 && $reason === '')
			{
				throw new Purchase_exception("{$it['ingredient_name']}: jumlah diterima berbeda dari sisa PO, isi alasan selisih (mis. \"dikirim sebagian\").");
			}

			$unit_cost_std = (float) $it['unit_price'] / (float) $it['factor'] * $cost_ratio;
			$movement_id = $this->CI->stock->receive($it['ingredient_id'], $qty_std, $unit_cost_std, array(
				'reason'      => 'purchase_receipt',
				'movement_no' => $movement_no,
				'supplier_id' => $po['supplier_id'],
				'expiry_date' => $l['expiry_date'],
				'ref_type'    => 'gr',
				'ref_id'      => $gr_number,
				'notes'       => $po['po_number'] . ($reason !== '' ? ' - ' . $reason : ''),
				'input_qty'   => $qty,
				'input_unit'  => $it['unit'],
			));

			$amount = round($qty * (float) $it['unit_price'] * $invoice_ratio, 2);
			$gr_amount += $amount;
			$this->db->insert('goods_receipt_items', array(
				'gr_id'            => $gr_id,
				'po_item_id'       => $it['id'],
				'qty_received'     => $qty,
				'qty_received_std' => $qty_std,
				'qty_expected_std' => $remaining_std,
				'variance_reason'  => $reason !== '' ? mb_substr($reason, 0, 255) : NULL,
				'expiry_date'      => $l['expiry_date'] ?: NULL,
				'amount'           => $amount,
				'movement_id'      => $movement_id,
			));
			$this->db->query('UPDATE purchase_order_items SET qty_received_std = qty_received_std + ? WHERE id = ?', array($qty_std, $it['id']));
			$received_any = TRUE;
		}

		if ( ! $received_any)
		{
			throw new Purchase_exception('Isi jumlah diterima minimal untuk satu bahan.');
		}

		$this->db->where('id', $gr_id)->update('goods_receipts', array('amount' => round($gr_amount, 2)));
		$open = (int) $this->db->query('SELECT COUNT(*) AS n FROM purchase_order_items WHERE po_id = ? AND qty_received_std < qty_std - 0.0005', array($po['id']))->row()->n;
		$status = $open > 0 ? 'partial' : 'received';
		$this->db->where('id', $po['id'])->update('purchase_orders', array('status' => $status));
		$this->_history($po['id'], 'received', $gr_number . ($status === 'partial' ? ' (sebagian)' : ' (lengkap)'));

		return array($gr_id, $gr_number);
	}

	// ------------------------------------------------------------------
	// Invoice supplier & 3-way matching
	// ------------------------------------------------------------------

	/** Nilai barang diterima & total invoice aktif untuk PO. */
	public function po_amounts($po_id, $exclude_invoice_id = NULL)
	{
		$gr = (float) $this->db->query('SELECT COALESCE(SUM(amount), 0) AS v FROM goods_receipts WHERE po_id = ?', array((int) $po_id))->row()->v;
		$sql = "SELECT COALESCE(SUM(amount), 0) AS v FROM supplier_invoices WHERE po_id = ? AND status != 'rejected'";
		$binds = array((int) $po_id);
		if ($exclude_invoice_id)
		{
			$sql .= ' AND id != ?';
			$binds[] = (int) $exclude_invoice_id;
		}
		$invoiced = (float) $this->db->query($sql, $binds)->row()->v;
		return array('gr_amount' => round($gr, 2), 'invoiced' => round($invoiced, 2));
	}

	/**
	 * 3-way match: total invoice (termasuk yang ini) dibandingkan nilai barang
	 * yang sudah diterima, dengan toleransi di Pengaturan.
	 */
	public function match_status($gr_amount, $invoiced_total)
	{
		$tolerance = max(0.0, (float) setting('invoice_match_tolerance', 1)) / 100;
		return abs($invoiced_total - $gr_amount) <= max(1.0, $gr_amount * $tolerance) ? 'matched' : 'mismatch';
	}

	/**
	 * @param array $d invoice_number, invoice_date, amount, notes, attachment_path
	 * @return int id invoice
	 */
	public function record_invoice($po_id, array $d)
	{
		$po = $this->_lock_po($po_id);
		if ( ! in_array($po['status'], array('partial', 'received', 'closed'), TRUE))
		{
			throw new Purchase_exception('Invoice hanya bisa dicatat untuk PO yang barangnya sudah diterima.');
		}
		if ($d['amount'] <= 0)
		{
			throw new Purchase_exception('Nilai invoice harus lebih dari 0.');
		}
		$dup = $this->db->where('supplier_id', $po['supplier_id'])->where('invoice_number', $d['invoice_number'])->count_all_results('supplier_invoices');
		if ($dup)
		{
			throw new Purchase_exception("Invoice {$d['invoice_number']} dari supplier ini sudah pernah dicatat.");
		}

		$a = $this->po_amounts($po['id']);
		$this->db->insert('supplier_invoices', array(
			'invoice_number'  => $d['invoice_number'],
			'supplier_id'     => $po['supplier_id'],
			'po_id'           => $po['id'],
			'invoice_date'    => $d['invoice_date'],
			'due_date'        => date('Y-m-d', strtotime($d['invoice_date'] . ' +' . terms_days($po['payment_terms']) . ' days')),
			'amount'          => $d['amount'],
			'attachment_path' => $d['attachment_path'] ?: NULL,
			'match_status'    => $this->match_status($a['gr_amount'], $a['invoiced'] + $d['amount']),
			'po_amount'       => $po['total_amount'],
			'gr_amount'       => $a['gr_amount'],
			'invoiced_before' => $a['invoiced'],
			'notes'           => $d['notes'] ?: NULL,
			'created_by'      => $this->_uid(),
		));
		$id = (int) $this->db->insert_id();
		$this->_history($po['id'], 'invoice', $d['invoice_number'] . ' ' . rupiah($d['amount']));
		return $id;
	}

	/**
	 * Review invoice: approve | hold | reject. Invoice yang tidak cocok (mismatch)
	 * hanya bisa di-approve dengan catatan alasan.
	 */
	public function review_invoice($invoice_id, $action, $note)
	{
		$inv = $this->db->query('SELECT * FROM supplier_invoices WHERE id = ? FOR UPDATE', array((int) $invoice_id))->row_array();
		if ( ! $inv)
		{
			throw new Purchase_exception('Invoice tidak ditemukan.');
		}
		if ( ! in_array($inv['status'], array('pending', 'on_hold'), TRUE))
		{
			throw new Purchase_exception('Invoice ini sudah direview.');
		}
		$map = array('approve' => 'approved', 'hold' => 'on_hold', 'reject' => 'rejected');
		if ( ! isset($map[$action]))
		{
			throw new Purchase_exception('Aksi tidak valid.');
		}
		$note = trim((string) $note);

		// Hitung ulang kecocokan: barang bisa saja bertambah diterima sejak invoice dicatat.
		$a = $this->po_amounts($inv['po_id'], $inv['id']);
		$match = $this->match_status($a['gr_amount'], $a['invoiced'] + (float) $inv['amount']);

		if ($action === 'approve' && $match === 'mismatch' && $note === '')
		{
			throw new Purchase_exception('Invoice tidak cocok dengan barang diterima. Isi catatan alasan untuk tetap menyetujui.');
		}
		if (in_array($action, array('hold', 'reject'), TRUE) && $note === '')
		{
			throw new Purchase_exception('Alasan wajib diisi.');
		}
		$this->db->where('id', $inv['id'])->update('supplier_invoices', array(
			'status'       => $map[$action],
			'match_status' => $match,
			'gr_amount'    => $a['gr_amount'],
			'invoiced_before' => $a['invoiced'],
			'review_note'  => $note !== '' ? mb_substr($note, 0, 255) : NULL,
			'reviewed_by'  => $this->_uid(),
			'reviewed_at'  => date('Y-m-d H:i:s'),
		));
		return $map[$action];
	}

	// ------------------------------------------------------------------
	// Pembayaran supplier
	// ------------------------------------------------------------------

	/** Sisa yang masih bisa diajukan pembayarannya (dikurangi pembayaran pending). */
	public function payable($invoice)
	{
		$pending = (float) $this->db->query("SELECT COALESCE(SUM(amount), 0) AS v FROM supplier_payments WHERE invoice_id = ? AND status = 'pending'", array((int) $invoice['id']))->row()->v;
		return round((float) $invoice['amount'] - (float) $invoice['paid_amount'] - $pending, 2);
	}

	/** @param array $d payment_date, amount, method, detail metode, proof_path, notes */
	public function record_payment($invoice_id, array $d)
	{
		$inv = $this->db->query('SELECT * FROM supplier_invoices WHERE id = ? FOR UPDATE', array((int) $invoice_id))->row_array();
		if ( ! $inv OR $inv['status'] !== 'approved')
		{
			throw new Purchase_exception('Pembayaran hanya untuk invoice yang sudah disetujui.');
		}
		$payable = $this->payable($inv);
		if ($d['amount'] <= 0)
		{
			throw new Purchase_exception('Jumlah bayar harus lebih dari 0.');
		}
		if ($d['amount'] > $payable + 0.005)
		{
			throw new Purchase_exception('Jumlah bayar melebihi sisa tagihan (' . rupiah($payable) . ', sudah termasuk pembayaran yang menunggu verifikasi).');
		}
		$row = array(
			'payment_number' => $this->CI->sequence->next('PAY', date('ym', strtotime($d['payment_date'])), 4),
			'invoice_id'     => $inv['id'],
			'payment_date'   => $d['payment_date'],
			'amount'         => $d['amount'],
			'method'         => $d['method'],
			'proof_path'     => $d['proof_path'] ?: NULL,
			'notes'          => $d['notes'] ?: NULL,
			'created_by'     => $this->_uid(),
		);
		foreach (array('bank_name', 'account_no', 'reference_no', 'card_last4', 'auth_code', 'check_no', 'check_date') as $k)
		{
			$row[$k] = isset($d[$k]) && $d[$k] !== '' ? $d[$k] : NULL;
		}
		$this->db->insert('supplier_payments', $row);
		return array((int) $this->db->insert_id(), $row['payment_number']);
	}

	public function verify_payment($payment_id)
	{
		$p = $this->_lock_payment($payment_id);
		if ( ! $this->CI->rbac->is_super() && (int) $p['created_by'] === $this->_uid())
		{
			throw new Purchase_exception('Pembayaran harus diverifikasi oleh orang lain dari yang menginput.');
		}
		$inv = $this->db->query('SELECT * FROM supplier_invoices WHERE id = ? FOR UPDATE', array($p['invoice_id']))->row_array();
		$paid = round((float) $inv['paid_amount'] + (float) $p['amount'], 2);
		if ($paid > (float) $inv['amount'] + 0.005)
		{
			throw new Purchase_exception('Total pembayaran akan melebihi nilai invoice.');
		}
		$this->db->where('id', $p['id'])->update('supplier_payments', array(
			'status' => 'verified', 'verified_by' => $this->_uid(), 'verified_at' => date('Y-m-d H:i:s'),
		));
		$this->db->where('id', $inv['id'])->update('supplier_invoices', array(
			'paid_amount'    => $paid,
			'payment_status' => $paid >= (float) $inv['amount'] - 0.005 ? 'paid' : 'partial',
		));
	}

	public function reject_payment($payment_id, $reason)
	{
		$p = $this->_lock_payment($payment_id);
		if (trim($reason) === '')
		{
			throw new Purchase_exception('Alasan penolakan wajib diisi.');
		}
		$this->db->where('id', $p['id'])->update('supplier_payments', array(
			'status' => 'rejected', 'reject_reason' => mb_substr($reason, 0, 255),
			'verified_by' => $this->_uid(), 'verified_at' => date('Y-m-d H:i:s'),
		));
	}

	// ------------------------------------------------------------------

	protected function _lock_po($po_id)
	{
		$po = $this->db->query('SELECT * FROM purchase_orders WHERE id = ? FOR UPDATE', array((int) $po_id))->row_array();
		if ( ! $po)
		{
			throw new Purchase_exception('PO tidak ditemukan.');
		}
		return $po;
	}

	protected function _lock_payment($payment_id)
	{
		$p = $this->db->query('SELECT * FROM supplier_payments WHERE id = ? FOR UPDATE', array((int) $payment_id))->row_array();
		if ( ! $p)
		{
			throw new Purchase_exception('Pembayaran tidak ditemukan.');
		}
		if ($p['status'] !== 'pending')
		{
			throw new Purchase_exception('Pembayaran ini sudah diproses.');
		}
		return $p;
	}

	protected function _history($po_id, $action, $note = '')
	{
		$this->db->insert('po_history', array(
			'po_id'   => (int) $po_id,
			'action'  => $action,
			'note'    => $note !== '' ? mb_substr($note, 0, 255) : NULL,
			'user_id' => $this->_uid(),
		));
	}

	protected function _uid()
	{
		return (int) $this->CI->session->userdata('user_id');
	}
}
