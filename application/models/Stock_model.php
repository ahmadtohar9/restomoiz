<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Query baca untuk stok: riwayat pergerakan, batch, peringatan, laporan.
 * Perubahan stok TIDAK dilakukan di sini, tapi di library Stock_service.
 */
class Stock_model extends CI_Model {

	/**
	 * Riwayat pergerakan. Filter: from, to, ingredient_id, type, reason, q (no. dokumen / bahan)
	 */
	public function movements(array $f, $limit = 100, $offset = 0)
	{
		$this->_movement_query($f);
		return $this->db->select('m.*, i.code AS ingredient_code, i.name AS ingredient_name, i.unit, s.name AS supplier_name, u.name AS user_name')
			->order_by('m.id', 'DESC')
			->limit($limit, $offset)
			->get()->result_array();
	}

	public function count_movements(array $f)
	{
		$this->_movement_query($f);
		return $this->db->count_all_results();
	}

	protected function _movement_query(array $f)
	{
		$this->db->from('stock_movements m')
			->join('ingredients i', 'i.id = m.ingredient_id')
			->join('suppliers s', 's.id = m.supplier_id', 'left')
			->join('users u', 'u.id = m.created_by', 'left');
		if ( ! empty($f['from']))
		{
			$this->db->where('m.created_at >=', $f['from'] . ' 00:00:00');
		}
		if ( ! empty($f['to']))
		{
			$this->db->where('m.created_at <=', $f['to'] . ' 23:59:59');
		}
		if ( ! empty($f['ingredient_id']))
		{
			$this->db->where('m.ingredient_id', (int) $f['ingredient_id']);
		}
		if ( ! empty($f['type']))
		{
			$this->db->where('m.type', $f['type']);
		}
		if ( ! empty($f['reason']))
		{
			$this->db->where('m.reason', $f['reason']);
		}
		if ( ! empty($f['q']))
		{
			$this->db->group_start()->like('m.movement_no', $f['q'])->or_like('i.name', $f['q'])->or_like('i.code', $f['q'])->group_end();
		}
	}

	/** Batch yang masih ada sisa, urut FIFO. */
	public function open_batches($ingredient_id)
	{
		return $this->db->select('b.*, s.name AS supplier_name')
			->from('ingredient_batches b')
			->join('suppliers s', 's.id = b.supplier_id', 'left')
			->where('b.ingredient_id', (int) $ingredient_id)
			->where('b.qty_remaining >', 0)
			->order_by('b.received_at', 'ASC')->order_by('b.id', 'ASC')
			->get()->result_array();
	}

	/**
	 * Semua peringatan stok (PRD 2.1.2 Alerts & Notifications).
	 * @return array [jenis => rows]
	 */
	public function alerts()
	{
		$expiry_days = (int) setting('expiry_alert_days', 7);
		$dead_days = (int) setting('dead_stock_days', 60);
		$active = "i.status = 'active'";

		$base = 'SELECT i.id, i.code, i.name, i.unit, i.qty_on_hand, i.min_stock, i.max_stock, i.reorder_point, i.reorder_qty,
					i.stock_value, i.last_out_at, i.last_in_at, s.name AS supplier_name
				 FROM ingredients i LEFT JOIN suppliers s ON s.id = i.default_supplier_id WHERE ' . $active;

		return array(
			'low' => $this->db->query("$base AND i.min_stock > 0 AND i.qty_on_hand < i.min_stock ORDER BY (i.qty_on_hand / i.min_stock), i.name")->result_array(),
			'reorder' => $this->db->query("$base AND i.reorder_point > 0 AND i.qty_on_hand <= i.reorder_point AND NOT (i.min_stock > 0 AND i.qty_on_hand < i.min_stock) ORDER BY i.name")->result_array(),
			'over' => $this->db->query("$base AND i.max_stock > 0 AND i.qty_on_hand > i.max_stock ORDER BY i.name")->result_array(),
			'dead' => $this->db->query("$base AND i.qty_on_hand > 0 AND COALESCE(i.last_out_at, i.last_in_at, i.created_at) < ? ORDER BY COALESCE(i.last_out_at, i.last_in_at, i.created_at)",
				array(date('Y-m-d H:i:s', strtotime("-$dead_days days"))))->result_array(),
			'expiring' => $this->db->query(
				"SELECT b.id AS batch_id, b.expiry_date, b.qty_remaining, b.unit_cost, i.id, i.code, i.name, i.unit
				 FROM ingredient_batches b JOIN ingredients i ON i.id = b.ingredient_id
				 WHERE b.qty_remaining > 0 AND b.expiry_date IS NOT NULL AND b.expiry_date <= ?
				 ORDER BY b.expiry_date, i.name",
				array(date('Y-m-d', strtotime("+$expiry_days days"))))->result_array(),
		);
	}

	/** Ringkasan jumlah peringatan untuk dashboard. */
	public function alert_counts()
	{
		$counts = array();
		foreach ($this->alerts() as $key => $rows)
		{
			$counts[$key] = count($rows);
		}
		return $counts;
	}

	// ---- Laporan (PRD 2.1.3 Reports) ----

	/** Nilai stok per kategori induk teratas. */
	public function value_by_category()
	{
		$this->load->model('Category_model');
		$tree = $this->Category_model->tree();
		$root_of = array();
		$names = array();
		foreach ($tree as $c)
		{
			$names[$c['id']] = $c['name'];
			$root_of[$c['id']] = $c['parent_id'] ? $root_of[$c['parent_id']] : (int) $c['id'];
		}

		$rows = $this->db->select('category_id, COUNT(*) AS items, SUM(qty_on_hand > 0) AS in_stock, SUM(stock_value) AS value')
			->where('status !=', 'discontinued')->group_by('category_id')->get('ingredients')->result_array();

		$out = array();
		foreach ($rows as $r)
		{
			$root = $r['category_id'] && isset($root_of[$r['category_id']]) ? $root_of[$r['category_id']] : 0;
			if ( ! isset($out[$root]))
			{
				$out[$root] = array('name' => $root ? $names[$root] : 'Tanpa kategori', 'items' => 0, 'in_stock' => 0, 'value' => 0.0);
			}
			$out[$root]['items'] += (int) $r['items'];
			$out[$root]['in_stock'] += (int) $r['in_stock'];
			$out[$root]['value'] += (float) $r['value'];
		}
		uasort($out, function ($a, $b) { return $b['value'] <=> $a['value']; });
		return $out;
	}

	/** Aging stok dari umur batch yang tersisa: 0-30, 31-60, 61-90, > 90 hari. */
	public function aging()
	{
		return $this->db->query(
			"SELECT i.id, i.code, i.name, i.unit,
				SUM(b.qty_remaining) AS qty,
				SUM(CASE WHEN DATEDIFF(CURDATE(), b.received_at) <= 30 THEN b.qty_remaining * b.unit_cost ELSE 0 END) AS v0_30,
				SUM(CASE WHEN DATEDIFF(CURDATE(), b.received_at) BETWEEN 31 AND 60 THEN b.qty_remaining * b.unit_cost ELSE 0 END) AS v31_60,
				SUM(CASE WHEN DATEDIFF(CURDATE(), b.received_at) BETWEEN 61 AND 90 THEN b.qty_remaining * b.unit_cost ELSE 0 END) AS v61_90,
				SUM(CASE WHEN DATEDIFF(CURDATE(), b.received_at) > 90 THEN b.qty_remaining * b.unit_cost ELSE 0 END) AS v90,
				SUM(b.qty_remaining * b.unit_cost) AS total,
				MIN(b.received_at) AS oldest
			 FROM ingredient_batches b JOIN ingredients i ON i.id = b.ingredient_id
			 WHERE b.qty_remaining > 0
			 GROUP BY i.id ORDER BY total DESC"
		)->result_array();
	}

	/**
	 * Bahan yang tidak keluar selama >= $days hari (slow-moving / dead stock),
	 * beserta total pemakaian 90 hari terakhir.
	 */
	public function not_moving($days)
	{
		return $this->db->query(
			"SELECT i.id, i.code, i.name, i.unit, i.qty_on_hand, i.stock_value, i.last_out_at, i.last_in_at,
				DATEDIFF(CURDATE(), COALESCE(i.last_out_at, i.last_in_at, i.created_at)) AS idle_days,
				COALESCE((SELECT -SUM(m.qty) FROM stock_movements m
					WHERE m.ingredient_id = i.id AND m.qty < 0 AND m.reason NOT IN ('opname','adjustment_out')
					AND m.created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)), 0) AS out_90d
			 FROM ingredients i
			 WHERE i.status = 'active' AND i.qty_on_hand > 0
			   AND COALESCE(i.last_out_at, i.last_in_at, i.created_at) < DATE_SUB(NOW(), INTERVAL ? DAY)
			 ORDER BY idle_days DESC",
			array((int) $days)
		)->result_array();
	}

	public function totals()
	{
		return $this->db->query(
			"SELECT COUNT(*) AS items, COALESCE(SUM(stock_value), 0) AS value, SUM(qty_on_hand <= 0) AS empty
			 FROM ingredients WHERE status = 'active'"
		)->row_array();
	}
}
