<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Semua perubahan stok bahan baku WAJIB lewat library ini (PRD 2.1.2 & 2.1.3).
 *
 *  - receive(): stok masuk -> movement IN/ADJ + batch FIFO baru
 *  - issue():   stok keluar -> ambil dari batch tertua (FIFO), catat per batch
 *  - count():   hasil stock opname -> penyesuaian ke jumlah fisik
 *
 * Setiap method mengunci baris bahan (SELECT ... FOR UPDATE) dan harus
 * dipanggil di dalam transaksi. Pakai run() untuk membungkusnya:
 *
 *   $this->stock->run(function ($stock) use ($lines) {
 *       foreach ($lines as $l) $stock->receive($l['id'], $l['qty'], $l['cost'], [...]);
 *   });
 *
 * Kesalahan bisnis (stok kurang, input salah) dilempar sebagai Stock_exception
 * dan seluruh transaksi dibatalkan.
 */
class Stock_exception extends RuntimeException {}

class Stock_service {

	/** Alasan pergerakan => [label, tipe] */
	public static $reasons = array(
		'opening_balance'  => array('Saldo awal', 'IN'),
		'manual_receipt'   => array('Penerimaan manual', 'IN'),
		'purchase_receipt' => array('Penerimaan dari PO', 'IN'),
		'adjustment_in'    => array('Penyesuaian (+)', 'ADJ'),
		'usage'            => array('Pemakaian dapur', 'OUT'),
		'waste'            => array('Terbuang / waste', 'OUT'),
		'expired'          => array('Kedaluwarsa', 'OUT'),
		'damaged'          => array('Rusak', 'OUT'),
		'supplier_return'  => array('Retur ke supplier', 'OUT'),
		'adjustment_out'   => array('Penyesuaian (-)', 'ADJ'),
		'opname'           => array('Stock opname', 'ADJ'),
		'sales'            => array('Terpakai penjualan', 'OUT'),
	);

	/** Alasan yang memperbarui harga beli terakhir bahan. */
	protected $price_reasons = array('manual_receipt', 'purchase_receipt', 'opening_balance');

	/** Alasan keluar yang TIDAK dihitung sebagai "barang bergerak" untuk dead stock. */
	protected $non_movement_out = array('opname', 'adjustment_out');

	protected $CI;
	protected $db;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->db = $this->CI->db;
		$this->CI->load->library('sequence');
	}

	public static function reason_label($reason)
	{
		return isset(self::$reasons[$reason]) ? self::$reasons[$reason][0] : $reason;
	}

	/** Jalankan $fn dalam satu transaksi. Rollback kalau ada exception. */
	public function run(callable $fn)
	{
		$this->db->trans_begin();
		try
		{
			$result = $fn($this);
			if ($this->db->trans_status() === FALSE)
			{
				throw new Stock_exception('Gagal menyimpan ke database.');
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

	/** Nomor dokumen pergerakan baru (satu dokumen boleh berisi banyak baris). */
	public function new_document_no()
	{
		return $this->CI->sequence->next('MV', date('ymd'), 4);
	}

	/**
	 * Stok masuk.
	 *
	 * @param float $qty        jumlah dalam satuan standar (> 0)
	 * @param float $unit_cost  harga per satuan standar (>= 0)
	 * @param array $o  reason (wajib), movement_no, supplier_id, expiry_date,
	 *                  ref_type, ref_id, notes, input_qty, input_unit
	 * @return int id movement
	 */
	public function receive($ingredient_id, $qty, $unit_cost, array $o)
	{
		$qty = round((float) $qty, 3);
		$unit_cost = round((float) $unit_cost, 4);
		$reason = $o['reason'];
		$this->_check_reason($reason, array('IN', 'ADJ'));
		if ($qty <= 0)
		{
			throw new Stock_exception('Jumlah masuk harus lebih dari 0.');
		}
		if ($unit_cost < 0)
		{
			throw new Stock_exception('Harga tidak boleh negatif.');
		}

		$ing = $this->_lock($ingredient_id);
		$now = date('Y-m-d H:i:s');
		$after = round($ing['qty_on_hand'] + $qty, 3);
		$total = round($qty * $unit_cost, 2);

		$movement_id = $this->_movement($ing, self::$reasons[$reason][1], $reason, $qty, $unit_cost, $total, $after, $o, $now);

		$this->_insert('ingredient_batches', array(
			'ingredient_id' => $ing['id'],
			'movement_id'   => $movement_id,
			'received_at'   => $now,
			'qty_in'        => $qty,
			'qty_remaining' => $qty,
			'unit_cost'     => $unit_cost,
			'expiry_date'   => ! empty($o['expiry_date']) ? $o['expiry_date'] : NULL,
			'supplier_id'   => ! empty($o['supplier_id']) ? (int) $o['supplier_id'] : NULL,
		));

		$update = array('last_in_at' => $now);
		if (in_array($reason, $this->price_reasons, TRUE) && $unit_cost > 0)
		{
			$update['current_price'] = $unit_cost;
			if ( ! empty($o['supplier_id']))
			{
				$this->_insert('supplier_price_history', array(
					'supplier_id'   => (int) $o['supplier_id'],
					'ingredient_id' => $ing['id'],
					'price'         => $unit_cost,
					'recorded_at'   => $now,
					'ref_type'      => isset($o['ref_type']) ? $o['ref_type'] : 'movement',
					'ref_id'        => isset($o['ref_id']) ? $o['ref_id'] : $movement_id,
				));
			}
		}
		$this->_refresh_totals($ing['id'], $update);

		return $movement_id;
	}

	/**
	 * Stok keluar dengan FIFO.
	 *
	 * @param float $qty  jumlah dalam satuan standar (> 0)
	 * @param array $o    reason (wajib), movement_no, batch_id (ambil dari batch ini dulu,
	 *                    mis. buang yang kedaluwarsa), supplier_id, ref_type, ref_id, notes,
	 *                    input_qty, input_unit
	 * @return int id movement
	 */
	public function issue($ingredient_id, $qty, array $o)
	{
		$qty = round((float) $qty, 3);
		$reason = $o['reason'];
		$this->_check_reason($reason, array('OUT', 'ADJ'));
		if ($qty <= 0)
		{
			throw new Stock_exception('Jumlah keluar harus lebih dari 0.');
		}

		$ing = $this->_lock($ingredient_id);
		if ($qty > (float) $ing['qty_on_hand'] + 0.0005)
		{
			throw new Stock_exception(sprintf('Stok %s tidak cukup: tersedia %s %s, diminta %s %s.',
				$ing['name'], qty($ing['qty_on_hand']), $ing['unit'], qty($qty), $ing['unit']));
		}

		$batches = $this->db->query(
			'SELECT id, qty_remaining, unit_cost FROM ingredient_batches
			 WHERE ingredient_id = ? AND qty_remaining > 0
			 ORDER BY (id = ?) DESC, received_at ASC, id ASC
			 FOR UPDATE',
			array($ing['id'], isset($o['batch_id']) ? (int) $o['batch_id'] : 0)
		)->result_array();

		$remaining = $qty;
		$taken = array();
		$total = 0.0;
		foreach ($batches as $b)
		{
			if ($remaining <= 0)
			{
				break;
			}
			$take = min($remaining, (float) $b['qty_remaining']);
			$taken[] = array($b, $take);
			$total += $take * (float) $b['unit_cost'];
			$remaining = round($remaining - $take, 3);
		}
		if ($remaining > 0.0005)
		{
			// qty_on_hand tidak sinkron dengan batch: jangan lanjut diam-diam.
			throw new Stock_exception("Data batch {$ing['name']} tidak sinkron dengan saldo stok. Hubungi admin.");
		}

		$now = date('Y-m-d H:i:s');
		$total = round($total, 2);
		$avg_cost = round($total / $qty, 4);
		$after = round($ing['qty_on_hand'] - $qty, 3);
		$movement_id = $this->_movement($ing, self::$reasons[$reason][1], $reason, -$qty, $avg_cost, -$total, $after, $o, $now);

		foreach ($taken as $t)
		{
			list($b, $take) = $t;
			$this->db->query('UPDATE ingredient_batches SET qty_remaining = qty_remaining - ? WHERE id = ?', array($take, $b['id']));
			$this->_insert('stock_movement_batches', array(
				'movement_id' => $movement_id,
				'batch_id'    => $b['id'],
				'qty'         => $take,
				'unit_cost'   => $b['unit_cost'],
			));
		}

		$update = array();
		if ( ! in_array($reason, $this->non_movement_out, TRUE))
		{
			$update['last_out_at'] = $now;
		}
		$this->_refresh_totals($ing['id'], $update);

		return $movement_id;
	}

	/**
	 * Samakan stok sistem dengan hasil hitung fisik (stock opname).
	 * Selisih positif masuk sebagai batch baru dengan harga beli terakhir.
	 *
	 * @return array system_qty, variance, variance_value, movement_id (NULL kalau tidak ada selisih)
	 */
	public function count($ingredient_id, $counted_qty, array $o)
	{
		$counted_qty = round((float) $counted_qty, 3);
		if ($counted_qty < 0)
		{
			throw new Stock_exception('Jumlah hitung fisik tidak boleh negatif.');
		}
		$ing = $this->_lock($ingredient_id);
		$system = round((float) $ing['qty_on_hand'], 3);
		$variance = round($counted_qty - $system, 3);
		$o['reason'] = 'opname';

		$movement_id = NULL;
		$value = 0.0;
		if ($variance > 0)
		{
			$movement_id = $this->receive($ing['id'], $variance, (float) $ing['current_price'], $o);
			$value = round($variance * (float) $ing['current_price'], 2);
		}
		elseif ($variance < 0)
		{
			$movement_id = $this->issue($ing['id'], -$variance, $o);
			// total_cost movement keluar sudah bernilai negatif (nilai FIFO yang hilang).
			$value = round((float) $this->db->select('total_cost')->where('id', $movement_id)->get('stock_movements')->row()->total_cost, 2);
		}

		$this->db->where('id', $ing['id'])->update('ingredients', array('last_counted_at' => date('Y-m-d H:i:s')));

		return array(
			'system_qty'     => $system,
			'variance'       => $variance,
			'variance_value' => $value,
			'movement_id'    => $movement_id,
		);
	}

	// ------------------------------------------------------------------

	protected function _lock($ingredient_id)
	{
		$ing = $this->db->query('SELECT * FROM ingredients WHERE id = ? FOR UPDATE', array((int) $ingredient_id))->row_array();
		if ( ! $ing)
		{
			throw new Stock_exception('Bahan baku tidak ditemukan.');
		}
		return $ing;
	}

	protected function _check_reason($reason, array $types)
	{
		if ( ! isset(self::$reasons[$reason]) OR ! in_array(self::$reasons[$reason][1], $types, TRUE))
		{
			throw new Stock_exception("Alasan pergerakan '$reason' tidak valid.");
		}
	}

	protected function _movement(array $ing, $type, $reason, $qty, $unit_cost, $total, $after, array $o, $now)
	{
		$this->_insert('stock_movements', array(
			'movement_no'   => ! empty($o['movement_no']) ? $o['movement_no'] : $this->new_document_no(),
			'ingredient_id' => $ing['id'],
			'type'          => $type,
			'reason'        => $reason,
			'qty'           => $qty,
			'unit_cost'     => $unit_cost,
			'total_cost'    => $total,
			'qty_before'    => $ing['qty_on_hand'],
			'qty_after'     => $after,
			'input_qty'     => isset($o['input_qty']) ? $o['input_qty'] : NULL,
			'input_unit'    => isset($o['input_unit']) ? $o['input_unit'] : NULL,
			'supplier_id'   => ! empty($o['supplier_id']) ? (int) $o['supplier_id'] : NULL,
			'ref_type'      => isset($o['ref_type']) ? $o['ref_type'] : NULL,
			'ref_id'        => isset($o['ref_id']) ? (string) $o['ref_id'] : NULL,
			'notes'         => isset($o['notes']) && $o['notes'] !== '' ? mb_substr($o['notes'], 0, 255) : NULL,
			'created_by'    => $this->CI->session->userdata('user_id') ?: NULL,
			'created_at'    => $now,
		));
		return (int) $this->db->insert_id();
	}

	/** Hitung ulang qty & nilai stok dari batch (sumber kebenaran). */
	protected function _refresh_totals($ingredient_id, array $extra = array())
	{
		$row = $this->db->query(
			'SELECT COALESCE(SUM(qty_remaining), 0) AS q, COALESCE(SUM(qty_remaining * unit_cost), 0) AS v
			 FROM ingredient_batches WHERE ingredient_id = ?',
			array($ingredient_id)
		)->row_array();
		$data = array_merge(array('qty_on_hand' => round($row['q'], 3), 'stock_value' => round($row['v'], 2)), $extra);
		if ( ! $this->db->where('id', $ingredient_id)->update('ingredients', $data))
		{
			throw new Stock_exception('Gagal memperbarui saldo stok.');
		}
	}

	protected function _insert($table, array $data)
	{
		if ( ! $this->db->insert($table, $data))
		{
			$err = $this->db->error();
			log_message('error', "Stock_service insert $table: " . json_encode($err));
			throw new Stock_exception('Gagal menyimpan data stok.');
		}
	}
}
