<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ingredient_model extends CI_Model {

	public static $statuses = array(
		'active'       => 'Aktif',
		'inactive'     => 'Nonaktif',
		'discontinued' => 'Tidak diproduksi lagi',
	);

	/** Satuan umum untuk saran input (bebas diisi satuan lain). */
	public static $common_units = array('kg', 'gram', 'liter', 'ml', 'pcs', 'butir', 'ekor', 'ikat', 'pack', 'box', 'botol', 'kaleng', 'sachet', 'karung', 'dus', 'lembar', 'sisir');

	/**
	 * Daftar bahan dengan filter:
	 *   q, category_id (termasuk sub-kategori), status, stock (low|reorder|over|empty)
	 */
	public function all(array $f = array())
	{
		$this->db->select('i.*, c.name AS category_name, s.name AS supplier_name')
			->from('ingredients i')
			->join('ingredient_categories c', 'c.id = i.category_id', 'left')
			->join('suppliers s', 's.id = i.default_supplier_id', 'left')
			->order_by('i.name', 'ASC');

		if ( ! empty($f['q']))
		{
			$this->db->group_start()->like('i.name', $f['q'])->or_like('i.code', $f['q'])->group_end();
		}
		if ( ! empty($f['category_ids']))
		{
			$this->db->where_in('i.category_id', $f['category_ids']);
		}
		if ( ! empty($f['status']))
		{
			$this->db->where('i.status', $f['status']);
		}
		if ( ! empty($f['supplier_id']))
		{
			$this->db->where('i.default_supplier_id', (int) $f['supplier_id']);
		}
		switch (isset($f['stock']) ? $f['stock'] : '')
		{
			case 'low':
				$this->db->where('i.min_stock >', 0)->where('i.qty_on_hand < i.min_stock', NULL, FALSE);
				break;
			case 'reorder':
				$this->db->where('i.reorder_point >', 0)->where('i.qty_on_hand <= i.reorder_point', NULL, FALSE);
				break;
			case 'over':
				$this->db->where('i.max_stock >', 0)->where('i.qty_on_hand > i.max_stock', NULL, FALSE);
				break;
			case 'empty':
				$this->db->where('i.qty_on_hand <=', 0);
				break;
		}
		return $this->db->get()->result_array();
	}

	/**
	 * Bahan aktif untuk form stok, lengkap dengan satuan alternatifnya.
	 * @return array [id => row + 'units' => [[unit, factor], ...]]
	 */
	public function for_stock_form()
	{
		$rows = $this->db->select('id, code, name, unit, current_price, qty_on_hand, default_supplier_id')
			->where('status', 'active')->order_by('name')->get('ingredients')->result_array();
		$units = array();
		foreach ($this->db->order_by('factor')->get('ingredient_units')->result_array() as $u)
		{
			$units[$u['ingredient_id']][] = array('unit' => $u['unit'], 'factor' => (float) $u['factor']);
		}
		$out = array();
		foreach ($rows as $r)
		{
			$r['units'] = array_merge(array(array('unit' => $r['unit'], 'factor' => 1.0)), isset($units[$r['id']]) ? $units[$r['id']] : array());
			$out[$r['id']] = $r;
		}
		return $out;
	}

	public function find($id)
	{
		return $this->db->select('i.*, c.name AS category_name, s.name AS supplier_name')
			->from('ingredients i')
			->join('ingredient_categories c', 'c.id = i.category_id', 'left')
			->join('suppliers s', 's.id = i.default_supplier_id', 'left')
			->where('i.id', (int) $id)
			->get()->row_array();
	}

	public function units($id)
	{
		return $this->db->where('ingredient_id', (int) $id)->order_by('factor')->get('ingredient_units')->result_array();
	}

	/** Faktor konversi satuan ke satuan standar, atau NULL kalau satuan tidak dikenal. */
	public function unit_factor(array $ingredient, $unit)
	{
		if ($unit === '' OR $unit === $ingredient['unit'])
		{
			return 1.0;
		}
		$row = $this->db->where('ingredient_id', $ingredient['id'])->where('unit', $unit)->get('ingredient_units')->row_array();
		return $row ? (float) $row['factor'] : NULL;
	}

	public function code_exists($code, $except_id = NULL)
	{
		$this->db->where('code', $code);
		if ($except_id)
		{
			$this->db->where('id !=', (int) $except_id);
		}
		return $this->db->count_all_results('ingredients') > 0;
	}

	/**
	 * @param array $d     data bahan (sudah tervalidasi)
	 * @param array $units [[unit, factor], ...] satuan alternatif
	 */
	public function save(array $d, array $units, $id = NULL)
	{
		$row = array(
			'name'                => $d['name'],
			'description'         => $d['description'] ?: NULL,
			'category_id'         => $d['category_id'] ?: NULL,
			'unit'                => $d['unit'],
			'default_supplier_id' => $d['default_supplier_id'] ?: NULL,
			'min_stock'           => $d['min_stock'],
			'max_stock'           => $d['max_stock'],
			'reorder_point'       => $d['reorder_point'],
			'reorder_qty'         => $d['reorder_qty'],
			'location'            => $d['location'] ?: NULL,
			'status'              => $d['status'],
			'notes'               => $d['notes'] ?: NULL,
		);
		if (array_key_exists('image_path', $d))
		{
			$row['image_path'] = $d['image_path'];
		}
		if (isset($d['current_price']))
		{
			$row['current_price'] = $d['current_price'];
		}

		$this->db->trans_start();
		if ($id)
		{
			if ($d['code'] !== '')
			{
				$row['code'] = $d['code'];
			}
			$this->db->where('id', (int) $id)->update('ingredients', $row);
		}
		else
		{
			if ($d['code'] === '')
			{
				$this->load->library('sequence');
				do
				{
					$d['code'] = $this->sequence->next('BB', '', 5);
				}
				while ($this->code_exists($d['code']));
			}
			$row['code'] = $d['code'];
			$row['created_by'] = $this->session->userdata('user_id');
			$this->db->insert('ingredients', $row);
			$id = (int) $this->db->insert_id();
		}

		$this->db->where('ingredient_id', (int) $id)->delete('ingredient_units');
		foreach ($units as $u)
		{
			$this->db->insert('ingredient_units', array('ingredient_id' => (int) $id, 'unit' => $u[0], 'factor' => $u[1]));
		}
		$this->db->trans_complete();

		return $this->db->trans_status() ? (int) $id : FALSE;
	}

	public function has_movements($id)
	{
		return $this->db->where('ingredient_id', (int) $id)->count_all_results('stock_movements') > 0;
	}

	public function delete($id)
	{
		return $this->db->where('id', (int) $id)->delete('ingredients');
	}
}
