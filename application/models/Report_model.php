<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Laporan penjualan & keuangan (PRD 2.5).
 *
 * Definisi yang dipakai konsisten di semua laporan:
 *  - Penjualan bersih = subtotal - diskon promo (tanpa PPN & service), dicatat
 *    pada tanggal bayar. PPN adalah titipan pajak, bukan pendapatan.
 *  - Refund dicatat pada tanggal selesai (uang keluar) dan mengurangi
 *    penjualan bersih sebesar nilai itemnya (base_amount) + service-nya.
 *  - COGS = COGS pesanan lunas (sudah dikurangi reverse COGS refund).
 */
class Report_model extends CI_Model {

	public static $expense_categories = array(
		'payroll'      => 'Gaji & upah',
		'rent_utility' => 'Sewa & utilitas (listrik, air, gas, internet)',
		'marketing'    => 'Marketing & promosi',
		'maintenance'  => 'Perawatan & perbaikan',
		'supplies'     => 'Perlengkapan (non-bahan baku)',
		'depreciation' => 'Penyusutan',
		'other'        => 'Beban operasional lain',
		'interest'     => 'Bunga & beban non-operasional',
	);

	protected function _range($from, $to)
	{
		return array($from . ' 00:00:00', $to . ' 23:59:59');
	}

	/** KPI utama periode. */
	public function summary($from, $to)
	{
		list($f, $t) = $this->_range($from, $to);
		$o = $this->db->query(
			"SELECT COUNT(*) AS tx, COALESCE(SUM(total), 0) AS gross, COALESCE(SUM(subtotal - discount_total), 0) AS net_sales,
				COALESCE(SUM(discount_total), 0) AS discount, COALESCE(SUM(tax), 0) AS tax, COALESCE(SUM(service_charge), 0) AS service,
				COALESCE(SUM(cogs_total), 0) AS cogs, COALESCE(SUM(subtotal), 0) AS subtotal
			 FROM orders WHERE status = 'paid' AND paid_at BETWEEN ? AND ?",
			array($f, $t)
		)->row_array();
		$r = $this->db->query(
			"SELECT COUNT(*) AS n, COALESCE(SUM(refund_amount), 0) AS total, COALESCE(SUM(base_amount), 0) AS base,
				COALESCE(SUM(service_amount), 0) AS service, COALESCE(SUM(tax_amount), 0) AS tax
			 FROM order_refunds WHERE status = 'completed' AND completed_at BETWEEN ? AND ?",
			array($f, $t)
		)->row_array();
		$s = array_map('floatval', $o);
		$s['tx'] = (int) $o['tx'];
		// COGS: nilai item terjual pada periode ini dikurangi bahan yang kembali ke stok dari refund
		// yang selesai pada periode ini (sama dengan definisi di laporan laba rugi).
		$items_cogs = (float) $this->db->query(
			"SELECT COALESCE(SUM(oi.cogs), 0) AS c FROM order_items oi JOIN orders o ON o.id = oi.order_id
			 WHERE o.status = 'paid' AND o.paid_at BETWEEN ? AND ? AND oi.kitchen_status != 'void'", array($f, $t)
		)->row()->c;
		$reversed = (float) $this->db->query(
			"SELECT COALESCE(SUM(ri.cogs_reversed), 0) AS c FROM order_refund_items ri JOIN order_refunds r ON r.id = ri.refund_id
			 WHERE r.status = 'completed' AND r.completed_at BETWEEN ? AND ?", array($f, $t)
		)->row()->c;
		$s['cogs'] = round($items_cogs - $reversed, 2);
		$s['refund_count'] = (int) $r['n'];
		$s['refunds'] = (float) $r['total'];
		$s['revenue'] = round($s['net_sales'] + $s['service'] - (float) $r['base'] - (float) $r['service'], 2);
		$s['gross_profit'] = round($s['revenue'] - $s['cogs'], 2);
		$s['margin'] = $s['revenue'] > 0 ? $s['gross_profit'] / $s['revenue'] * 100 : NULL;
		$s['avg_bill'] = $s['tx'] ? $s['gross'] / $s['tx'] : 0;
		$s['refund_rate'] = $s['tx'] ? $s['refund_count'] / $s['tx'] * 100 : 0;
		return $s;
	}

	/** Periode sebelumnya dengan panjang yang sama. */
	public static function previous_range($from, $to)
	{
		$days = (int) round((strtotime($to) - strtotime($from)) / 86400) + 1;
		return array(date('Y-m-d', strtotime($from . " -$days days")), date('Y-m-d', strtotime($from . ' -1 day')));
	}

	/** Pendapatan bersih & transaksi per hari (hari tanpa transaksi = 0). */
	public function daily($from, $to)
	{
		list($f, $t) = $this->_range($from, $to);
		$rows = array_column($this->db->query(
			"SELECT DATE(paid_at) AS d, COUNT(*) AS tx, SUM(subtotal - discount_total + service_charge) AS revenue, SUM(cogs_total) AS cogs
			 FROM orders WHERE status = 'paid' AND paid_at BETWEEN ? AND ? GROUP BY DATE(paid_at)",
			array($f, $t)
		)->result_array(), NULL, 'd');
		$out = array();
		for ($d = $from; $d <= $to; $d = date('Y-m-d', strtotime($d . ' +1 day')))
		{
			$out[] = array('date' => $d, 'tx' => isset($rows[$d]) ? (int) $rows[$d]['tx'] : 0,
				'revenue' => isset($rows[$d]) ? round((float) $rows[$d]['revenue'], 2) : 0.0, 'cogs' => isset($rows[$d]) ? (float) $rows[$d]['cogs'] : 0.0);
		}
		return $out;
	}

	/** Per menu: qty, penjualan bersih, COGS, laba, margin, qty refund. */
	public function by_item($from, $to, $limit = NULL)
	{
		list($f, $t) = $this->_range($from, $to);
		$sql = "SELECT oi.name, SUM(oi.qty) AS qty, SUM(oi.line_total - oi.discount) AS revenue, SUM(oi.cogs) AS cogs,
				COALESCE(SUM((SELECT SUM(ri.qty) FROM order_refund_items ri JOIN order_refunds r ON r.id = ri.refund_id
					WHERE ri.order_item_id = oi.id AND r.status = 'completed')), 0) AS refunded_qty
			 FROM order_items oi JOIN orders o ON o.id = oi.order_id
			 WHERE o.status = 'paid' AND o.paid_at BETWEEN ? AND ? AND oi.kitchen_status != 'void'
			 GROUP BY oi.name ORDER BY revenue DESC";
		if ($limit)
		{
			$sql .= ' LIMIT ' . (int) $limit;
		}
		$rows = $this->db->query($sql, array($f, $t))->result_array();
		foreach ($rows as &$r)
		{
			$r['profit'] = (float) $r['revenue'] - (float) $r['cogs'];
			$r['margin'] = (float) $r['revenue'] > 0 ? $r['profit'] / (float) $r['revenue'] * 100 : NULL;
			$r['refund_rate'] = (int) $r['qty'] ? (int) $r['refunded_qty'] / (int) $r['qty'] * 100 : 0;
		}
		return $rows;
	}

	/** Per kategori menu teratas (sub-kategori digabung ke induknya). */
	public function by_category($from, $to)
	{
		list($f, $t) = $this->_range($from, $to);
		$this->load->model('Menu_category_model');
		$root_of = array();
		$names = array();
		foreach ($this->Menu_category_model->tree() as $c)
		{
			$names[$c['id']] = $c['name'];
			$root_of[$c['id']] = $c['parent_id'] ? $root_of[$c['parent_id']] : (int) $c['id'];
		}
		$rows = $this->db->query(
			"SELECT m.category_id, SUM(oi.qty) AS qty, SUM(oi.line_total - oi.discount) AS revenue, SUM(oi.cogs) AS cogs
			 FROM order_items oi JOIN orders o ON o.id = oi.order_id JOIN menus m ON m.id = oi.menu_id
			 WHERE o.status = 'paid' AND o.paid_at BETWEEN ? AND ? AND oi.kitchen_status != 'void'
			 GROUP BY m.category_id",
			array($f, $t)
		)->result_array();
		$out = array();
		foreach ($rows as $r)
		{
			$root = $r['category_id'] && isset($root_of[$r['category_id']]) ? $root_of[$r['category_id']] : 0;
			if ( ! isset($out[$root]))
			{
				$out[$root] = array('name' => $root ? $names[$root] : 'Tanpa kategori', 'qty' => 0, 'revenue' => 0.0, 'cogs' => 0.0);
			}
			$out[$root]['qty'] += (int) $r['qty'];
			$out[$root]['revenue'] += (float) $r['revenue'];
			$out[$root]['cogs'] += (float) $r['cogs'];
		}
		uasort($out, function ($a, $b) { return $b['revenue'] <=> $a['revenue']; });
		return $out;
	}

	public function by_payment($from, $to)
	{
		list($f, $t) = $this->_range($from, $to);
		return $this->db->query(
			"SELECT payment_method AS k, COUNT(*) AS tx, SUM(total) AS total FROM orders
			 WHERE status = 'paid' AND paid_at BETWEEN ? AND ? GROUP BY payment_method ORDER BY total DESC",
			array($f, $t)
		)->result_array();
	}

	public function by_order_type($from, $to)
	{
		list($f, $t) = $this->_range($from, $to);
		return $this->db->query(
			"SELECT order_type AS k, COUNT(*) AS tx, SUM(total) AS total, AVG(total) AS avg_bill FROM orders
			 WHERE status = 'paid' AND paid_at BETWEEN ? AND ? GROUP BY order_type ORDER BY total DESC",
			array($f, $t)
		)->result_array();
	}

	/** Jam sibuk: [hari 1-7][jam 0-23] => transaksi & pendapatan. */
	public function heatmap($from, $to)
	{
		list($f, $t) = $this->_range($from, $to);
		$rows = $this->db->query(
			"SELECT WEEKDAY(paid_at) + 1 AS dow, HOUR(paid_at) AS h, COUNT(*) AS tx, SUM(total) AS total FROM orders
			 WHERE status = 'paid' AND paid_at BETWEEN ? AND ? GROUP BY dow, h",
			array($f, $t)
		)->result_array();
		$grid = array();
		foreach ($rows as $r)
		{
			$grid[(int) $r['dow']][(int) $r['h']] = array('tx' => (int) $r['tx'], 'total' => (float) $r['total']);
		}
		return $grid;
	}

	public function by_daypart($from, $to)
	{
		list($f, $t) = $this->_range($from, $to);
		return $this->db->query(
			"SELECT CASE WHEN HOUR(paid_at) < 11 THEN 'Pagi (sebelum 11:00)' WHEN HOUR(paid_at) < 15 THEN 'Siang (11:00–14:59)'
				WHEN HOUR(paid_at) < 18 THEN 'Sore (15:00–17:59)' ELSE 'Malam (18:00 ke atas)' END AS k,
				MIN(HOUR(paid_at)) AS ord, COUNT(*) AS tx, SUM(total) AS total
			 FROM orders WHERE status = 'paid' AND paid_at BETWEEN ? AND ? GROUP BY k ORDER BY ord",
			array($f, $t)
		)->result_array();
	}

	public function promo_usage($from, $to)
	{
		list($f, $t) = $this->_range($from, $to);
		return $this->db->query(
			"SELECT op.name, COUNT(*) AS used, SUM(op.discount) AS discount, SUM(o.total) AS order_total, AVG(o.total) AS avg_bill
			 FROM order_promos op JOIN orders o ON o.id = op.order_id
			 WHERE o.status = 'paid' AND o.paid_at BETWEEN ? AND ? GROUP BY op.promo_id, op.name ORDER BY used DESC",
			array($f, $t)
		)->result_array();
	}

	/** Kinerja kasir (PRD 2.5.3 #4). */
	public function cashiers($from, $to)
	{
		list($f, $t) = $this->_range($from, $to);
		return $this->db->query(
			"SELECT u.id, u.name,
				(SELECT COUNT(*) FROM orders o WHERE o.paid_by = u.id AND o.status = 'paid' AND o.paid_at BETWEEN ? AND ?) AS tx,
				(SELECT COALESCE(SUM(total), 0) FROM orders o WHERE o.paid_by = u.id AND o.status = 'paid' AND o.paid_at BETWEEN ? AND ?) AS total,
				(SELECT COUNT(*) FROM order_refunds r WHERE r.requested_by = u.id AND r.status != 'rejected' AND r.requested_at BETWEEN ? AND ?) AS refunds,
				(SELECT COALESCE(SUM(refund_amount), 0) FROM order_refunds r WHERE r.requested_by = u.id AND r.status = 'completed' AND r.completed_at BETWEEN ? AND ?) AS refund_total,
				(SELECT COALESCE(SUM(variance), 0) FROM shifts s WHERE s.user_id = u.id AND s.closed_at BETWEEN ? AND ?) AS cash_variance,
				(SELECT COUNT(*) FROM shifts s WHERE s.user_id = u.id AND s.opened_at BETWEEN ? AND ?) AS shifts,
				(SELECT HOUR(paid_at) FROM orders o WHERE o.paid_by = u.id AND o.status = 'paid' AND o.paid_at BETWEEN ? AND ?
					GROUP BY HOUR(paid_at) ORDER BY COUNT(*) DESC LIMIT 1) AS peak_hour
			 FROM users u
			 HAVING tx > 0 OR refunds > 0 OR shifts > 0
			 ORDER BY total DESC",
			array($f, $t, $f, $t, $f, $t, $f, $t, $f, $t, $f, $t, $f, $t)
		)->result_array();
	}

	/** Analisis refund (PRD 2.5.3 #5). */
	public function refunds($from, $to)
	{
		list($f, $t) = $this->_range($from, $to);
		$by_reason = $this->db->query(
			"SELECT reason_code, COUNT(*) AS n, SUM(CASE WHEN status = 'completed' THEN refund_amount ELSE 0 END) AS total
			 FROM order_refunds WHERE requested_at BETWEEN ? AND ? GROUP BY reason_code ORDER BY n DESC",
			array($f, $t)
		)->result_array();
		$status = array_column($this->db->query(
			"SELECT status, COUNT(*) AS n FROM order_refunds WHERE requested_at BETWEEN ? AND ? GROUP BY status", array($f, $t)
		)->result_array(), 'n', 'status');
		$decided = (int) (isset($status['completed']) ? $status['completed'] : 0) + (int) (isset($status['approved']) ? $status['approved'] : 0) + (int) (isset($status['rejected']) ? $status['rejected'] : 0);
		$approved = (int) (isset($status['completed']) ? $status['completed'] : 0) + (int) (isset($status['approved']) ? $status['approved'] : 0);
		$items = array_values(array_filter($this->by_item($from, $to), function ($r) { return (int) $r['refunded_qty'] > 0; }));
		usort($items, function ($a, $b) { return $b['refund_rate'] <=> $a['refund_rate']; });
		$trend = $this->db->query(
			"SELECT DATE(completed_at) AS d, COUNT(*) AS n, SUM(refund_amount) AS total FROM order_refunds
			 WHERE status = 'completed' AND completed_at BETWEEN ? AND ? GROUP BY DATE(completed_at) ORDER BY d",
			array($f, $t)
		)->result_array();
		return array('by_reason' => $by_reason, 'status' => $status, 'approval_rate' => $decided ? $approved / $decided * 100 : NULL,
			'items' => $items, 'trend' => $trend);
	}

	// ------------------------------------------------------------------
	// Laba rugi (PRD 2.5.2)
	// ------------------------------------------------------------------

	/**
	 * @return array revenue[] (per kategori + service - refund), cogs[] (per kategori),
	 *               waste (bahan terbuang & penyesuaian stok), opex[], other_expense, dan totalnya
	 */
	public function profit_loss($from, $to)
	{
		list($f, $t) = $this->_range($from, $to);
		$cats = $this->by_category($from, $to);
		$sum = $this->summary($from, $to);
		$ref = $this->db->query(
			"SELECT COALESCE(SUM(base_amount), 0) AS base, COALESCE(SUM(service_amount), 0) AS service FROM order_refunds
			 WHERE status = 'completed' AND completed_at BETWEEN ? AND ?", array($f, $t)
		)->row_array();

		// Nilai item pesanan per kategori bersih diskon; COGS item (reverse COGS refund dikurangkan per kategori).
		$rev_cogs = $this->db->query(
			"SELECT COALESCE(SUM(ri.cogs_reversed), 0) AS reversed FROM order_refund_items ri JOIN order_refunds r ON r.id = ri.refund_id
			 WHERE r.status = 'completed' AND r.completed_at BETWEEN ? AND ?", array($f, $t)
		)->row()->reversed;

		// Bahan terbuang, kedaluwarsa, rusak, selisih opname & penyesuaian (nilai FIFO).
		$waste_rows = $this->db->query(
			"SELECT reason, SUM(-total_cost) AS v FROM stock_movements
			 WHERE reason IN ('waste','expired','damaged','adjustment_out','opname','adjustment_in') AND created_at BETWEEN ? AND ?
			 GROUP BY reason",
			array($f, $t)
		)->result_array();
		$waste = array();
		$waste_total = 0.0;
		foreach ($waste_rows as $w)
		{
			$waste[$w['reason']] = (float) $w['v'];
			$waste_total += (float) $w['v'];
		}

		$exp = array_column($this->db->query(
			"SELECT category, SUM(amount) AS total FROM expenses WHERE expense_date BETWEEN ? AND ? GROUP BY category", array($from, $to)
		)->result_array(), 'total', 'category');
		$opex = array();
		$opex_total = 0.0;
		foreach (self::$expense_categories as $k => $label)
		{
			if ($k === 'interest')
			{
				continue;
			}
			$opex[$k] = isset($exp[$k]) ? (float) $exp[$k] : 0.0;
			$opex_total += $opex[$k];
		}
		$other = isset($exp['interest']) ? (float) $exp['interest'] : 0.0;

		$revenue_items = array_sum(array_column($cats, 'revenue'));
		$refund_total = (float) $ref['base'] + (float) $ref['service'];
		$revenue = $revenue_items + $sum['service'] - $refund_total;
		$cogs_items = array_sum(array_column($cats, 'cogs')) - (float) $rev_cogs;
		$cogs_total = $cogs_items + $waste_total;
		$gross = $revenue - $cogs_total;
		$ebit = $gross - $opex_total;
		$net = $ebit - $other;

		return array(
			'categories'   => $cats,
			'service'      => $sum['service'],
			'refunds'      => $refund_total,
			'revenue'      => round($revenue, 2),
			'cogs_items'   => round($cogs_items, 2),
			'cogs_reversed'=> (float) $rev_cogs,
			'waste'        => $waste,
			'waste_total'  => round($waste_total, 2),
			'cogs_total'   => round($cogs_total, 2),
			'gross_profit' => round($gross, 2),
			'opex'         => $opex,
			'opex_total'   => round($opex_total, 2),
			'ebit'         => round($ebit, 2),
			'other'        => $other,
			'net'          => round($net, 2),
			'tax_collected'=> $sum['tax'],
			'discount'     => $sum['discount'],
			'tx'           => $sum['tx'],
		);
	}
}
