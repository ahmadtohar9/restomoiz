<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Stock opname / physical count (PRD 2.1.2: "Stock variance saat physical count").
 *
 * Alur: draft (lembar hitung dibuat, jumlah fisik diisi bertahap)
 *       -> posted (selisih dibukukan sebagai movement ADJ lewat Stock_service)
 *       atau cancelled.
 * Stok sistem dicatat pada SAAT POSTING, jadi transaksi yang terjadi selama
 * penghitungan tetap diperhitungkan.
 */
class Opname_model extends CI_Model {

	public function all()
	{
		return $this->db->select('o.*, c.name AS category_name, u.name AS created_by_name,
				(SELECT COUNT(*) FROM stock_opname_items x WHERE x.opname_id = o.id) AS item_count,
				(SELECT COUNT(*) FROM stock_opname_items x WHERE x.opname_id = o.id AND x.counted_qty IS NOT NULL) AS counted_count,
				(SELECT COALESCE(SUM(x.variance_value), 0) FROM stock_opname_items x WHERE x.opname_id = o.id) AS variance_value', FALSE)
			->from('stock_opnames o')
			->join('ingredient_categories c', 'c.id = o.category_id', 'left')
			->join('users u', 'u.id = o.created_by', 'left')
			->order_by('o.id', 'DESC')
			->limit(200)
			->get()->result_array();
	}

	public function find($id)
	{
		return $this->db->select('o.*, c.name AS category_name, u.name AS created_by_name, p.name AS posted_by_name')
			->from('stock_opnames o')
			->join('ingredient_categories c', 'c.id = o.category_id', 'left')
			->join('users u', 'u.id = o.created_by', 'left')
			->join('users p', 'p.id = o.posted_by', 'left')
			->where('o.id', (int) $id)
			->get()->row_array();
	}

	public function items($opname_id)
	{
		return $this->db->select('x.*, i.code, i.name, i.unit, i.qty_on_hand, i.current_price, i.location')
			->from('stock_opname_items x')
			->join('ingredients i', 'i.id = x.ingredient_id')
			->where('x.opname_id', (int) $opname_id)
			->order_by('i.location', 'ASC')->order_by('i.name', 'ASC')
			->get()->result_array();
	}

	/**
	 * Buat lembar opname untuk semua bahan aktif (atau satu kategori + turunannya).
	 * @return int|FALSE id opname
	 */
	public function create($date, $category_id, $notes, array $category_ids = array())
	{
		$this->load->library('sequence');
		$this->db->trans_start();
		$this->db->insert('stock_opnames', array(
			'opname_no'   => $this->sequence->next('SO', date('Ym', strtotime($date)), 3),
			'opname_date' => $date,
			'category_id' => $category_id ?: NULL,
			'notes'       => $notes ?: NULL,
			'created_by'  => $this->session->userdata('user_id'),
		));
		$id = (int) $this->db->insert_id();

		$sql = "INSERT INTO stock_opname_items (opname_id, ingredient_id, system_qty)
				SELECT ?, id, qty_on_hand FROM ingredients WHERE status = 'active'";
		$binds = array($id);
		if ($category_ids)
		{
			$sql .= ' AND category_id IN ?';
			$binds[] = $category_ids;
		}
		$this->db->query($sql, $binds);
		$this->db->trans_complete();

		return $this->db->trans_status() ? $id : FALSE;
	}

	/** Simpan jumlah hitung (draft). $counts = [item_id => ['qty' => ..., 'notes' => ...]] */
	public function save_counts($opname_id, array $counts)
	{
		foreach ($counts as $item_id => $c)
		{
			$this->db->where('id', (int) $item_id)->where('opname_id', (int) $opname_id)->update('stock_opname_items', array(
				'counted_qty' => $c['qty'],
				'notes'       => $c['notes'] !== '' ? mb_substr($c['notes'], 0, 255) : NULL,
			));
		}
	}

	public function set_status($id, $status, array $extra = array())
	{
		return $this->db->where('id', (int) $id)->update('stock_opnames', array_merge(array('status' => $status), $extra));
	}
}
