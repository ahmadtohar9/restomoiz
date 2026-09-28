<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Supplier_model extends CI_Model {

	public static $payment_terms = array(
		'COD'    => 'COD (bayar saat terima)',
		'CBD'    => 'CBD (bayar sebelum kirim)',
		'NET7'   => 'NET 7 hari',
		'NET14'  => 'NET 14 hari',
		'NET30'  => 'NET 30 hari',
		'NET45'  => 'NET 45 hari',
		'NET60'  => 'NET 60 hari',
	);

	public function all(array $f = array())
	{
		$this->db->select('s.*, COUNT(DISTINCT i.id) AS ingredient_count')
			->from('suppliers s')
			->join('ingredients i', 'i.default_supplier_id = s.id', 'left')
			->group_by('s.id')
			->order_by('s.name', 'ASC');
		if ( ! empty($f['q']))
		{
			$this->db->group_start()
				->like('s.name', $f['q'])->or_like('s.code', $f['q'])
				->or_like('s.city', $f['q'])->or_like('s.contact_person', $f['q'])
				->group_end();
		}
		if (isset($f['active']) && $f['active'] !== '')
		{
			$this->db->where('s.is_active', (int) $f['active']);
		}
		return $this->db->get()->result_array();
	}

	/** [id => "Nama (Kota)"] supplier aktif untuk dropdown. */
	public function options()
	{
		$out = array();
		foreach ($this->db->where('is_active', 1)->order_by('name')->get('suppliers')->result_array() as $s)
		{
			$out[$s['id']] = $s['name'] . ($s['city'] ? ' (' . $s['city'] . ')' : '');
		}
		return $out;
	}

	public function find($id)
	{
		return $this->db->where('id', (int) $id)->get('suppliers')->row_array();
	}

	public function save(array $d, $id = NULL)
	{
		$row = array(
			'name'             => $d['name'],
			'contact_person'   => $d['contact_person'] ?: NULL,
			'phone'            => $d['phone'] ?: NULL,
			'email'            => $d['email'] ?: NULL,
			'address'          => $d['address'] ?: NULL,
			'city'             => $d['city'] ?: NULL,
			'payment_terms'    => $d['payment_terms'],
			'min_order_amount' => $d['min_order_amount'],
			'lead_time_days'   => $d['lead_time_days'],
			'quality_score'    => $d['quality_score'] ?: NULL,
			'notes'            => $d['notes'] ?: NULL,
			'is_active'        => $d['is_active'] ? 1 : 0,
		);
		if ($id)
		{
			$this->db->where('id', (int) $id)->update('suppliers', $row);
			return (int) $id;
		}
		$this->load->library('sequence');
		$row['code'] = $this->sequence->next('SUP', '', 4);
		$this->db->insert('suppliers', $row);
		return (int) $this->db->insert_id();
	}

	/** Harga terakhir per bahan dari supplier ini. */
	public function latest_prices($supplier_id)
	{
		return $this->db->query(
			'SELECT i.id, i.code, i.name, i.unit, h.price, h.recorded_at
			 FROM supplier_price_history h
			 JOIN (SELECT ingredient_id, MAX(id) AS max_id FROM supplier_price_history WHERE supplier_id = ? GROUP BY ingredient_id) last
			   ON last.max_id = h.id
			 JOIN ingredients i ON i.id = h.ingredient_id
			 ORDER BY i.name',
			array((int) $supplier_id)
		)->result_array();
	}

	/** Statistik penerimaan (untuk evaluasi supplier; on-time % dihitung dari PO di fase 3). */
	public function receipt_stats($supplier_id)
	{
		return $this->db->query(
			"SELECT COUNT(DISTINCT movement_no) AS receipts, COALESCE(SUM(total_cost), 0) AS total, MAX(created_at) AS last_receipt
			 FROM stock_movements WHERE supplier_id = ? AND type = 'IN'",
			array((int) $supplier_id)
		)->row_array();
	}

	public function is_used($id)
	{
		return $this->db->where('supplier_id', (int) $id)->count_all_results('stock_movements') > 0
			OR $this->db->where('supplier_id', (int) $id)->count_all_results('supplier_price_history') > 0;
	}

	public function delete($id)
	{
		return $this->db->where('id', (int) $id)->delete('suppliers');
	}
}
