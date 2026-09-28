<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Refund_exception extends RuntimeException {}

/**
 * Refund setelah pembayaran (PRD 2.4.4 skenario 2 & 3).
 *
 * Nilai refund per item = (jumlah baris - diskon promo) / qty x qty refund,
 * ditambah bagian service charge & PPN secara proporsional terhadap
 * transaksi. Refund terakhir yang menghabiskan semua item mendapat sisa
 * nilai supaya total refund tidak pernah melebihi total dibayar.
 *
 * Matriks approval (batas di Pengaturan):
 *   < refund_auto_limit dan <= refund_auto_minutes sejak bayar -> kasir langsung
 *   < refund_auto_limit tapi lewat batas menit                 -> manajer
 *   refund_auto_limit .. refund_owner_limit                   -> manajer
 *   > refund_owner_limit                                      -> owner
 *
 * Status: pending_approval -> approved -> completed (uang dikembalikan),
 * atau rejected. Refund level kasir langsung completed.
 */
class Refund_service {

	public static $reasons = array(
		'quality'         => 'Rasa / kualitas tidak sesuai',
		'wrong_order'     => 'Pesanan salah',
		'late'            => 'Terlalu lama',
		'customer_cancel' => 'Pelanggan membatalkan',
		'other'           => 'Lainnya',
	);

	public static $status = array(
		'pending_approval' => array('Menunggu approval', 'warning'),
		'approved'         => array('Disetujui, uang belum dikembalikan', 'info'),
		'completed'        => array('Selesai', 'success'),
		'rejected'         => array('Ditolak', 'danger'),
	);

	public static $levels = array('cashier' => 'Kasir', 'manager' => 'Manajer', 'owner' => 'Owner');

	protected $CI;
	protected $db;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->db = $this->CI->db;
		$this->CI->load->library('sequence');
		$this->CI->load->library('pos_service', NULL, 'pos');
	}

	public function run(callable $fn)
	{
		$this->db->trans_begin();
		try
		{
			$result = $fn($this);
			if ($this->db->trans_status() === FALSE)
			{
				throw new Refund_exception('Gagal menyimpan ke database.');
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

	/**
	 * Item yang masih bisa direfund: qty terjual dikurangi qty yang sudah
	 * direfund / sedang diajukan (refund ditolak tidak dihitung).
	 */
	public function refundable_items($order_id)
	{
		return $this->db->query(
			"SELECT i.*, i.qty - COALESCE((SELECT SUM(ri.qty) FROM order_refund_items ri JOIN order_refunds r ON r.id = ri.refund_id
				WHERE ri.order_item_id = i.id AND r.status != 'rejected'), 0) AS refundable_qty
			 FROM order_items i WHERE i.order_id = ? AND i.kitchen_status != 'void' ORDER BY i.id",
			array((int) $order_id)
		)->result_array();
	}

	/**
	 * Hitung nilai refund.
	 * @param array $qtys [order_item_id => qty]
	 * @return array base, service, tax, total, lines [item_id => [qty, amount]], is_full
	 */
	public function calculate(array $order, array $qtys)
	{
		$items = array_column($this->refundable_items($order['id']), NULL, 'id');
		$lines = array();
		$base = 0.0;
		foreach ($qtys as $item_id => $q)
		{
			$q = (int) $q;
			if ($q <= 0)
			{
				continue;
			}
			if ( ! isset($items[$item_id]))
			{
				throw new Refund_exception('Item tidak ditemukan di transaksi ini.');
			}
			$it = $items[$item_id];
			if ($q > (int) $it['refundable_qty'])
			{
				throw new Refund_exception("{$it['name']}: maksimal {$it['refundable_qty']} yang masih bisa direfund.");
			}
			$net = ((float) $it['line_total'] - (float) $it['discount']) / (int) $it['qty'];
			$amount = round($net * $q, 2);
			$lines[$item_id] = array('qty' => $q, 'amount' => $amount);
			$base += $amount;
		}
		if ( ! $lines)
		{
			throw new Refund_exception('Pilih minimal satu item dan jumlah yang direfund.');
		}

		// Apakah refund ini menghabiskan semua item yang tersisa?
		$is_full = TRUE;
		foreach ($items as $id => $it)
		{
			$left = (int) $it['refundable_qty'] - (isset($lines[$id]) ? $lines[$id]['qty'] : 0);
			if ($left > 0)
			{
				$is_full = FALSE;
			}
		}

		$pending = (float) $this->db->query("SELECT COALESCE(SUM(refund_amount), 0) AS v FROM order_refunds WHERE order_id = ? AND status IN ('pending_approval','approved')", array($order['id']))->row()->v;
		$already = (float) $order['refunded_amount'] + $pending;
		$net_sales = (float) $order['subtotal'] - (float) $order['discount_total'];
		$ratio = $net_sales > 0 ? $base / $net_sales : 0;
		$service = round((float) $order['service_charge'] * $ratio);
		$tax = round((float) $order['tax'] * $ratio);
		$total = round($base + $service + $tax);
		if ($is_full OR $total > (float) $order['total'] - $already)
		{
			// Sisa persis supaya total refund = total dibayar.
			$total = round((float) $order['total'] - $already, 2);
			$tax = min($tax, $total);
			$service = min($service, $total - $tax);
			$base = $total - $tax - $service;
		}
		return array('base' => round($base, 2), 'service' => $service, 'tax' => $tax, 'total' => $total, 'lines' => $lines, 'is_full' => $is_full);
	}

	public function required_level($amount, $paid_at)
	{
		$auto_limit = (float) setting('refund_auto_limit', 500000);
		$owner_limit = (float) setting('refund_owner_limit', 2000000);
		$minutes = (time() - strtotime($paid_at)) / 60;
		if ($amount > $owner_limit)
		{
			return 'owner';
		}
		if ($amount < $auto_limit && $minutes <= (float) setting('refund_auto_minutes', 5))
		{
			return 'cashier';
		}
		return 'manager';
	}

	protected function _can_approve_level($level)
	{
		$rbac = $this->CI->rbac;
		switch ($level)
		{
			case 'cashier': return $rbac->has_any(array('sales.refund', 'sales.refund_approve', 'sales.refund_owner'));
			case 'manager': return $rbac->has_any(array('sales.refund_approve', 'sales.refund_owner'));
			case 'owner':   return $rbac->has_permission('sales.refund_owner');
		}
		return FALSE;
	}

	/**
	 * Ajukan refund. Level kasir langsung diproses (uang keluar).
	 * @param array $d qtys[item_id=>qty], reason_code, reason, method (cash|original), restock, payment_ref
	 * @return array id, number, status
	 */
	public function request($order_id, array $d)
	{
		$order = $this->db->query('SELECT * FROM orders WHERE id = ? FOR UPDATE', array((int) $order_id))->row_array();
		if ( ! $order OR $order['status'] !== 'paid')
		{
			throw new Refund_exception('Refund hanya untuk transaksi yang sudah dibayar.');
		}
		if ( ! isset(self::$reasons[$d['reason_code']]))
		{
			throw new Refund_exception('Pilih alasan refund.');
		}
		if (trim($d['reason']) === '')
		{
			throw new Refund_exception('Jelaskan alasan refund.');
		}
		if ( ! in_array($d['method'], array('cash', 'original'), TRUE))
		{
			throw new Refund_exception('Pilih cara pengembalian dana.');
		}
		if ($d['method'] === 'original' && $order['payment_method'] === 'cash')
		{
			$d['method'] = 'cash';
		}

		$calc = $this->calculate($order, $d['qtys']);
		if ($calc['total'] <= 0)
		{
			throw new Refund_exception('Tidak ada nilai yang bisa direfund.');
		}
		$level = $this->required_level($calc['total'], $order['paid_at']);
		$uid = $this->_uid();
		$now = date('Y-m-d H:i:s');

		$this->db->insert('order_refunds', array(
			'refund_number'  => $this->CI->sequence->next('RF', date('ymd'), 4),
			'order_id'       => $order['id'],
			'refund_method'  => $d['method'],
			'refund_amount'  => $calc['total'],
			'base_amount'    => $calc['base'],
			'service_amount' => $calc['service'],
			'tax_amount'     => $calc['tax'],
			'reason_code'    => $d['reason_code'],
			'reason'         => mb_substr(trim($d['reason']), 0, 255),
			'restock'        => $d['restock'] ? 1 : 0,
			'required_level' => $level,
			'status'         => 'pending_approval',
			'is_full'        => $calc['is_full'] ? 1 : 0,
			'requested_by'   => $uid,
			'requested_at'   => $now,
		));
		$id = (int) $this->db->insert_id();
		foreach ($calc['lines'] as $item_id => $l)
		{
			$this->db->insert('order_refund_items', array('refund_id' => $id, 'order_item_id' => $item_id, 'qty' => $l['qty'], 'amount' => $l['amount']));
		}

		$status = 'pending_approval';
		if ($level === 'cashier' && $this->_can_approve_level('cashier'))
		{
			$this->db->where('id', $id)->update('order_refunds', array('status' => 'approved', 'approved_by' => $uid, 'approved_at' => $now,
				'approval_note' => 'Otomatis (di bawah batas kasir)'));
			$this->complete($id, isset($d['payment_ref']) ? $d['payment_ref'] : '');
			$status = 'completed';
		}
		$number = $this->db->select('refund_number')->where('id', $id)->get('order_refunds')->row()->refund_number;
		return array('id' => $id, 'number' => $number, 'status' => $status, 'level' => $level, 'amount' => $calc['total']);
	}

	public function approve($refund_id, $note)
	{
		$r = $this->_lock($refund_id);
		if ($r['status'] !== 'pending_approval')
		{
			throw new Refund_exception('Refund ini tidak sedang menunggu approval.');
		}
		if ( ! $this->_can_approve_level($r['required_level']))
		{
			throw new Refund_exception('Refund ini butuh approval ' . self::$levels[$r['required_level']] . '.');
		}
		if ((int) $r['requested_by'] === $this->_uid() && ! $this->CI->rbac->is_super())
		{
			throw new Refund_exception('Refund harus disetujui oleh orang lain dari yang mengajukan.');
		}
		$this->db->where('id', $r['id'])->update('order_refunds', array('status' => 'approved', 'approved_by' => $this->_uid(),
			'approved_at' => date('Y-m-d H:i:s'), 'approval_note' => trim($note) !== '' ? mb_substr(trim($note), 0, 255) : NULL));
	}

	public function reject($refund_id, $note)
	{
		$r = $this->_lock($refund_id);
		if ( ! in_array($r['status'], array('pending_approval', 'approved'), TRUE))
		{
			throw new Refund_exception('Refund ini sudah diproses.');
		}
		if ( ! $this->_can_approve_level($r['required_level']) OR ! $this->CI->rbac->has_any(array('sales.refund_approve', 'sales.refund_owner')))
		{
			throw new Refund_exception('Anda tidak berwenang menolak refund ini.');
		}
		if (trim($note) === '')
		{
			throw new Refund_exception('Alasan penolakan wajib diisi.');
		}
		$this->db->where('id', $r['id'])->update('order_refunds', array('status' => 'rejected', 'approved_by' => $this->_uid(),
			'approved_at' => date('Y-m-d H:i:s'), 'approval_note' => mb_substr(trim($note), 0, 255)));
	}

	/**
	 * Uang dikembalikan ke pelanggan. Tunai: butuh shift kasir terbuka dan
	 * mengurangi kas seharusnya di shift itu. Stok bahan dikembalikan jika restock.
	 */
	public function complete($refund_id, $payment_ref = '')
	{
		$r = $this->_lock($refund_id);
		if ($r['status'] !== 'approved')
		{
			throw new Refund_exception('Refund belum disetujui atau sudah selesai.');
		}
		$order = $this->db->query('SELECT * FROM orders WHERE id = ? FOR UPDATE', array($r['order_id']))->row_array();
		$shift_id = NULL;
		if ($r['refund_method'] === 'cash')
		{
			$shift = $this->CI->pos->current_shift($this->_uid());
			if ( ! $shift)
			{
				throw new Refund_exception('Refund tunai butuh shift kasir yang terbuka (uang keluar dari laci).');
			}
			$shift_id = $shift['id'];
		}
		elseif (trim($payment_ref) === '')
		{
			throw new Refund_exception('Isi nomor referensi reversal kartu / e-wallet.');
		}

		$cogs_reversed = 0.0;
		if ($r['restock'])
		{
			$items = $this->db->select('ri.id, ri.order_item_id, ri.qty, i.qty AS sold_qty, i.name')
				->from('order_refund_items ri')->join('order_items i', 'i.id = ri.order_item_id')
				->where('ri.refund_id', $r['id'])->get()->result_array();
			foreach ($items as $ri)
			{
				$ratio = (int) $ri['qty'] / (int) $ri['sold_qty'];
				$moves = $this->db->select('id, qty')->where('ref_type', 'order_item')->where('ref_id', (string) $ri['order_item_id'])
					->where('qty <', 0)->get('stock_movements')->result_array();
				$value = 0.0;
				foreach ($moves as $m)
				{
					$back = round(-(float) $m['qty'] * $ratio, 3);
					if ($back <= 0)
					{
						continue;
					}
					$mid = $this->CI->stock->reverse($m['id'], array('reason' => 'sales_return', 'notes' => 'Refund ' . $r['refund_number'] . ' ' . $ri['name']), $back);
					if ($mid)
					{
						$value += (float) $this->db->select('total_cost')->where('id', $mid)->get('stock_movements')->row()->total_cost;
					}
				}
				$this->db->where('id', $ri['id'])->update('order_refund_items', array('cogs_reversed' => round($value, 2)));
				$cogs_reversed += $value;
			}
		}

		$refunded = round((float) $order['refunded_amount'] + (float) $r['refund_amount'], 2);
		// Status "full" hanya kalau seluruh qty terjual sudah selesai direfund.
		$done_qty = (int) $this->db->query("SELECT COALESCE(SUM(ri.qty),0) AS q FROM order_refund_items ri JOIN order_refunds r ON r.id = ri.refund_id
			WHERE r.order_id = ? AND (r.status = 'completed' OR r.id = ?)", array($order['id'], $r['id']))->row()->q;
		$sold_qty = (int) $this->db->query("SELECT COALESCE(SUM(qty),0) AS q FROM order_items WHERE order_id = ? AND kitchen_status != 'void'", array($order['id']))->row()->q;

		$this->db->where('id', $order['id'])->update('orders', array(
			'refunded_amount' => $refunded,
			'refund_status'   => $done_qty >= $sold_qty ? 'full' : 'partial',
			'cogs_total'      => round((float) $order['cogs_total'] - $cogs_reversed, 2),
		));
		$this->db->where('id', $r['id'])->update('order_refunds', array(
			'status' => 'completed', 'completed_by' => $this->_uid(), 'completed_at' => date('Y-m-d H:i:s'),
			'shift_id' => $shift_id, 'payment_ref' => trim($payment_ref) !== '' ? mb_substr(trim($payment_ref), 0, 100) : NULL,
			'cogs_reversed' => round($cogs_reversed, 2),
		));
	}

	protected function _lock($refund_id)
	{
		$r = $this->db->query('SELECT * FROM order_refunds WHERE id = ? FOR UPDATE', array((int) $refund_id))->row_array();
		if ( ! $r)
		{
			throw new Refund_exception('Refund tidak ditemukan.');
		}
		return $r;
	}

	protected function _uid()
	{
		return (int) $this->CI->session->userdata('user_id');
	}
}
