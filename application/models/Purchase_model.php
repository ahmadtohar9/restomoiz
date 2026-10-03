<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Query baca modul pembelian. Perubahan data lewat library Purchase_service.
 */
class Purchase_model extends CI_Model {

	// ---- PO ----

	public function orders(array $f, $limit = 50, $offset = 0)
	{
		$this->_order_query($f);
		return $this->db->select('po.*, s.name AS supplier_name, u.name AS created_by_name,
				(SELECT COALESCE(SUM(g.amount), 0) FROM goods_receipts g WHERE g.po_id = po.id) AS received_amount', FALSE)
			->order_by('po.id', 'DESC')->limit($limit, $offset)->get()->result_array();
	}

	public function count_orders(array $f)
	{
		$this->_order_query($f);
		return $this->db->count_all_results();
	}

	protected function _order_query(array $f)
	{
		$this->db->from('purchase_orders po')
			->join('suppliers s', 's.id = po.supplier_id')
			->join('users u', 'u.id = po.created_by', 'left');
		if ( ! empty($f['status']))
		{
			$f['status'] === 'open'
				? $this->db->where_in('po.status', array('approved', 'partial'))
				: $this->db->where('po.status', $f['status']);
		}
		if ( ! empty($f['supplier_id']))
		{
			$this->db->where('po.supplier_id', (int) $f['supplier_id']);
		}
		if ( ! empty($f['from']))
		{
			$this->db->where('po.po_date >=', $f['from']);
		}
		if ( ! empty($f['to']))
		{
			$this->db->where('po.po_date <=', $f['to']);
		}
		if ( ! empty($f['q']))
		{
			$this->db->like('po.po_number', $f['q']);
		}
	}

	public function find_order($id)
	{
		return $this->db->select('po.*, s.name AS supplier_name, s.code AS supplier_code, s.address AS supplier_address, s.city AS supplier_city,
				s.phone AS supplier_phone, s.contact_person, u.name AS created_by_name, a1.name AS approved1_name, a2.name AS approved2_name, c.name AS closed_by_name')
			->from('purchase_orders po')
			->join('suppliers s', 's.id = po.supplier_id')
			->join('users u', 'u.id = po.created_by', 'left')
			->join('users a1', 'a1.id = po.approved1_by', 'left')
			->join('users a2', 'a2.id = po.approved2_by', 'left')
			->join('users c', 'c.id = po.closed_by', 'left')
			->where('po.id', (int) $id)
			->get()->row_array();
	}

	public function order_items($po_id)
	{
		return $this->db->select('i.*, g.code, g.name, g.unit AS std_unit')
			->from('purchase_order_items i')
			->join('ingredients g', 'g.id = i.ingredient_id')
			->where('i.po_id', (int) $po_id)
			->order_by('i.id')
			->get()->result_array();
	}

	public function history($po_id)
	{
		return $this->db->select('h.*, u.name AS user_name')
			->from('po_history h')->join('users u', 'u.id = h.user_id', 'left')
			->where('h.po_id', (int) $po_id)->order_by('h.id')
			->get()->result_array();
	}

	// ---- Goods receipt ----

	public function receipts_for_po($po_id)
	{
		return $this->db->select('g.*, u.name AS received_by_name')
			->from('goods_receipts g')->join('users u', 'u.id = g.received_by', 'left')
			->where('g.po_id', (int) $po_id)->order_by('g.id')
			->get()->result_array();
	}

	public function receipt_items($gr_id)
	{
		return $this->db->select('r.*, i.unit, i.unit_price, i.qty AS qty_ordered, g.name, g.code')
			->from('goods_receipt_items r')
			->join('purchase_order_items i', 'i.id = r.po_item_id')
			->join('ingredients g', 'g.id = i.ingredient_id')
			->where('r.gr_id', (int) $gr_id)
			->get()->result_array();
	}

	public function receipts(array $f, $limit = 50)
	{
		$this->db->select('g.*, po.po_number, s.name AS supplier_name, u.name AS received_by_name')
			->from('goods_receipts g')
			->join('purchase_orders po', 'po.id = g.po_id')
			->join('suppliers s', 's.id = po.supplier_id')
			->join('users u', 'u.id = g.received_by', 'left');
		if ( ! empty($f['from']))
		{
			$this->db->where('g.received_date >=', $f['from']);
		}
		if ( ! empty($f['to']))
		{
			$this->db->where('g.received_date <=', $f['to']);
		}
		return $this->db->order_by('g.id', 'DESC')->limit($limit)->get()->result_array();
	}

	// ---- Invoice ----

	public function invoices(array $f, $limit = 100)
	{
		$this->db->select('v.*, po.po_number, s.name AS supplier_name,
				(SELECT COALESCE(SUM(p.amount), 0) FROM supplier_payments p WHERE p.invoice_id = v.id AND p.status = \'pending\') AS pending_payment', FALSE)
			->from('supplier_invoices v')
			->join('purchase_orders po', 'po.id = v.po_id')
			->join('suppliers s', 's.id = v.supplier_id');
		if ( ! empty($f['status']))
		{
			$this->db->where('v.status', $f['status']);
		}
		if ( ! empty($f['payment']))
		{
			$f['payment'] === 'outstanding'
				? $this->db->where('v.status', 'approved')->where('v.payment_status !=', 'paid')
				: $this->db->where('v.payment_status', $f['payment']);
		}
		if ( ! empty($f['supplier_id']))
		{
			$this->db->where('v.supplier_id', (int) $f['supplier_id']);
		}
		if ( ! empty($f['q']))
		{
			$this->db->group_start()->like('v.invoice_number', $f['q'])->or_like('po.po_number', $f['q'])->group_end();
		}
		return $this->db->order_by('v.id', 'DESC')->limit($limit)->get()->result_array();
	}

	public function find_invoice($id)
	{
		return $this->db->select('v.*, po.po_number, po.total_amount AS po_total, po.payment_terms, s.name AS supplier_name,
				c.name AS created_by_name, r.name AS reviewed_by_name')
			->from('supplier_invoices v')
			->join('purchase_orders po', 'po.id = v.po_id')
			->join('suppliers s', 's.id = v.supplier_id')
			->join('users c', 'c.id = v.created_by', 'left')
			->join('users r', 'r.id = v.reviewed_by', 'left')
			->where('v.id', (int) $id)
			->get()->row_array();
	}

	public function invoices_for_po($po_id)
	{
		return $this->db->where('po_id', (int) $po_id)->order_by('id')->get('supplier_invoices')->result_array();
	}

	// ---- Payment ----

	public function payments(array $f, $limit = 100)
	{
		$this->db->select('p.*, v.invoice_number, v.po_id, s.name AS supplier_name, c.name AS created_by_name, x.name AS verified_by_name')
			->from('supplier_payments p')
			->join('supplier_invoices v', 'v.id = p.invoice_id')
			->join('suppliers s', 's.id = v.supplier_id')
			->join('users c', 'c.id = p.created_by', 'left')
			->join('users x', 'x.id = p.verified_by', 'left');
		if ( ! empty($f['status']))
		{
			$this->db->where('p.status', $f['status']);
		}
		if ( ! empty($f['invoice_id']))
		{
			$this->db->where('p.invoice_id', (int) $f['invoice_id']);
		}
		return $this->db->order_by('p.id', 'DESC')->limit($limit)->get()->result_array();
	}

	public function find_payment($id)
	{
		return $this->db->select('p.*, v.invoice_number, v.amount AS invoice_amount, v.paid_amount, s.name AS supplier_name,
				c.name AS created_by_name, x.name AS verified_by_name')
			->from('supplier_payments p')
			->join('supplier_invoices v', 'v.id = p.invoice_id')
			->join('suppliers s', 's.id = v.supplier_id')
			->join('users c', 'c.id = p.created_by', 'left')
			->join('users x', 'x.id = p.verified_by', 'left')
			->where('p.id', (int) $id)
			->get()->row_array();
	}

	// ---- Ringkasan untuk dashboard ----

	/**
	 * PO yang barangnya sudah diterima tetapi belum ditagih (belum ada invoice
	 * supplier, atau total invoice < nilai barang diterima). Hutang & jatuh tempo
	 * baru terhitung setelah invoice dicatat.
	 */
	public function uninvoiced_orders($limit = 100)
	{
		return $this->db->query("SELECT po.id, po.po_number, po.po_date, po.payment_terms, po.status, s.name AS supplier_name,
				gr.gr_amount, gr.last_receipt, COALESCE(inv.invoiced, 0) AS invoiced,
				gr.gr_amount - COALESCE(inv.invoiced, 0) AS uninvoiced
			 FROM purchase_orders po
			 JOIN suppliers s ON s.id = po.supplier_id
			 JOIN (SELECT po_id, SUM(amount) AS gr_amount, MAX(received_date) AS last_receipt FROM goods_receipts GROUP BY po_id) gr ON gr.po_id = po.id
			 LEFT JOIN (SELECT po_id, SUM(amount) AS invoiced FROM supplier_invoices WHERE status != 'rejected' GROUP BY po_id) inv ON inv.po_id = po.id
			 WHERE po.status IN ('partial', 'received', 'closed')
			   AND gr.gr_amount - COALESCE(inv.invoiced, 0) > 0.5
			 ORDER BY gr.last_receipt ASC, po.id ASC
			 LIMIT " . (int) $limit)->result_array();
	}

	public function pending_counts()
	{
		$q = function ($sql) { return (int) $this->db->query($sql)->row()->n; };
		return array(
			'approval_1'   => $q("SELECT COUNT(*) AS n FROM purchase_orders WHERE status = 'submitted' AND approved1_by IS NULL"),
			'approval_2'   => $q("SELECT COUNT(*) AS n FROM purchase_orders WHERE status = 'submitted' AND approved1_by IS NOT NULL"),
			'to_receive'   => $q("SELECT COUNT(*) AS n FROM purchase_orders WHERE status IN ('approved','partial')"),
			'late'         => $q("SELECT COUNT(*) AS n FROM purchase_orders WHERE status IN ('approved','partial') AND expected_delivery < CURDATE()"),
			'invoices'     => $q("SELECT COUNT(*) AS n FROM supplier_invoices WHERE status IN ('pending','on_hold')"),
			'uninvoiced'   => count($this->uninvoiced_orders(500)),
			'payments'     => $q("SELECT COUNT(*) AS n FROM supplier_payments WHERE status = 'pending'"),
			'overdue'      => $q("SELECT COUNT(*) AS n FROM supplier_invoices WHERE status = 'approved' AND payment_status != 'paid' AND due_date < CURDATE()"),
			'outstanding'  => (float) $this->db->query("SELECT COALESCE(SUM(amount - paid_amount), 0) AS v FROM supplier_invoices WHERE status = 'approved' AND payment_status != 'paid'")->row()->v,
		);
	}

	// ---- Laporan (PRD 2.2.5) ----

	/** Belanja (nilai barang diterima) per supplier dalam periode. */
	public function spend_by_supplier($from, $to)
	{
		return $this->db->query(
			"SELECT s.id, s.name, COUNT(DISTINCT g.po_id) AS po_count, COUNT(g.id) AS gr_count, SUM(g.amount) AS total
			 FROM goods_receipts g JOIN purchase_orders po ON po.id = g.po_id JOIN suppliers s ON s.id = po.supplier_id
			 WHERE g.received_date BETWEEN ? AND ?
			 GROUP BY s.id ORDER BY total DESC",
			array($from, $to)
		)->result_array();
	}

	/** Belanja per kategori bahan (kategori induk teratas). */
	public function spend_by_category($from, $to)
	{
		$this->load->model('Category_model');
		$root_of = array();
		$names = array();
		foreach ($this->Category_model->tree() as $c)
		{
			$names[$c['id']] = $c['name'];
			$root_of[$c['id']] = $c['parent_id'] ? $root_of[$c['parent_id']] : (int) $c['id'];
		}
		$rows = $this->db->query(
			"SELECT ing.category_id, SUM(r.amount) AS total, COUNT(DISTINCT ing.id) AS items
			 FROM goods_receipt_items r
			 JOIN goods_receipts g ON g.id = r.gr_id
			 JOIN purchase_order_items i ON i.id = r.po_item_id
			 JOIN ingredients ing ON ing.id = i.ingredient_id
			 WHERE g.received_date BETWEEN ? AND ?
			 GROUP BY ing.category_id",
			array($from, $to)
		)->result_array();
		$out = array();
		foreach ($rows as $r)
		{
			$root = $r['category_id'] && isset($root_of[$r['category_id']]) ? $root_of[$r['category_id']] : 0;
			if ( ! isset($out[$root]))
			{
				$out[$root] = array('name' => $root ? $names[$root] : 'Tanpa kategori', 'items' => 0, 'total' => 0.0);
			}
			$out[$root]['items'] += (int) $r['items'];
			$out[$root]['total'] += (float) $r['total'];
		}
		uasort($out, function ($a, $b) { return $b['total'] <=> $a['total']; });
		return $out;
	}

	/**
	 * Tren harga beli per bahan: rata-rata harga per satuan standar per bulan
	 * (6 bulan terakhir), dari penerimaan barang.
	 */
	public function price_trend($months = 6)
	{
		$start = date('Y-m-01', strtotime('-' . ($months - 1) . ' months'));
		$rows = $this->db->query(
			"SELECT ing.id, ing.code, ing.name, ing.unit, DATE_FORMAT(m.created_at, '%Y-%m') AS ym,
				SUM(m.total_cost) / SUM(m.qty) AS avg_price
			 FROM stock_movements m JOIN ingredients ing ON ing.id = m.ingredient_id
			 WHERE m.reason = 'purchase_receipt' AND m.created_at >= ?
			 GROUP BY ing.id, ym ORDER BY ing.name, ym",
			array($start)
		)->result_array();
		$out = array();
		foreach ($rows as $r)
		{
			if ( ! isset($out[$r['id']]))
			{
				$out[$r['id']] = array('code' => $r['code'], 'name' => $r['name'], 'unit' => $r['unit'], 'months' => array());
			}
			$out[$r['id']]['months'][$r['ym']] = (float) $r['avg_price'];
		}
		foreach ($out as &$item)
		{
			$vals = array_values($item['months']);
			$item['first'] = $vals[0];
			$item['last'] = end($vals);
			$item['change'] = $item['first'] > 0 ? ($item['last'] - $item['first']) / $item['first'] * 100 : 0;
		}
		unset($item);
		$labels = array();
		for ($i = $months - 1; $i >= 0; $i--)
		{
			$labels[] = date('Y-m', strtotime("-$i months", strtotime(date('Y-m-01'))));
		}
		return array('items' => $out, 'labels' => $labels);
	}

	/**
	 * Kinerja supplier: ketepatan kirim (penerimaan pertama <= tanggal kirim
	 * yang diminta), fill rate (qty diterima / qty dipesan pada PO selesai),
	 * lead time aktual rata-rata (approval -> penerimaan pertama).
	 */
	public function supplier_performance($from, $to)
	{
		return $this->db->query(
			"SELECT s.id, s.name, s.quality_score, COUNT(*) AS po_count,
				SUM(CASE WHEN po.expected_delivery IS NOT NULL THEN 1 ELSE 0 END) AS with_date,
				SUM(CASE WHEN po.expected_delivery IS NOT NULL AND fg.first_gr <= po.expected_delivery THEN 1 ELSE 0 END) AS on_time,
				AVG(DATEDIFF(fg.first_gr, DATE(COALESCE(po.approved2_at, po.approved1_at, po.submitted_at)))) AS avg_lead,
				SUM(it.ordered) AS ordered, SUM(it.received) AS received, SUM(po.total_amount) AS total
			 FROM purchase_orders po
			 JOIN suppliers s ON s.id = po.supplier_id
			 JOIN (SELECT po_id, MIN(received_date) AS first_gr FROM goods_receipts GROUP BY po_id) fg ON fg.po_id = po.id
			 JOIN (SELECT po_id, SUM(qty_std) AS ordered, SUM(LEAST(qty_received_std, qty_std)) AS received FROM purchase_order_items GROUP BY po_id) it ON it.po_id = po.id
			 WHERE po.po_date BETWEEN ? AND ?
			 GROUP BY s.id ORDER BY s.name",
			array($from, $to)
		)->result_array();
	}

	/** Aging hutang: sisa tagihan invoice disetujui per umur jatuh tempo. */
	public function ap_aging()
	{
		return $this->db->query(
			"SELECT s.id, s.name,
				SUM(v.amount - v.paid_amount) AS total,
				SUM(CASE WHEN v.due_date >= CURDATE() THEN v.amount - v.paid_amount ELSE 0 END) AS current_due,
				SUM(CASE WHEN DATEDIFF(CURDATE(), v.due_date) BETWEEN 1 AND 30 THEN v.amount - v.paid_amount ELSE 0 END) AS d1_30,
				SUM(CASE WHEN DATEDIFF(CURDATE(), v.due_date) BETWEEN 31 AND 60 THEN v.amount - v.paid_amount ELSE 0 END) AS d31_60,
				SUM(CASE WHEN DATEDIFF(CURDATE(), v.due_date) BETWEEN 61 AND 90 THEN v.amount - v.paid_amount ELSE 0 END) AS d61_90,
				SUM(CASE WHEN DATEDIFF(CURDATE(), v.due_date) > 90 THEN v.amount - v.paid_amount ELSE 0 END) AS d90,
				COUNT(*) AS invoices, MIN(v.due_date) AS oldest_due
			 FROM supplier_invoices v JOIN suppliers s ON s.id = v.supplier_id
			 WHERE v.status = 'approved' AND v.payment_status != 'paid'
			 GROUP BY s.id ORDER BY total DESC"
		)->result_array();
	}
}
