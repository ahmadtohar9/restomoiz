<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Kategori bahan baku bertingkat (Makanan > Daging, Sayur, ...).
 * Diturunkan oleh Menu_category_model untuk kategori menu.
 */
class Category_model extends CI_Model {

	protected $table = 'ingredient_categories';
	protected $item_table = 'ingredients';
	protected $order = 'c.name ASC';

	/**
	 * Semua kategori dalam urutan pohon, dengan 'depth', 'path' (Induk › Anak),
	 * dan jumlah bahan langsung di kategori itu.
	 */
	public function tree($only_active = FALSE)
	{
		$this->db->select('c.*, COUNT(i.id) AS ingredient_count')
			->from($this->table . ' c')
			->join($this->item_table . ' i', 'i.category_id = c.id', 'left')
			->group_by('c.id')
			->order_by($this->order);
		if ($only_active)
		{
			$this->db->where('c.is_active', 1);
		}
		$rows = $this->db->get()->result_array();

		$children = array();
		foreach ($rows as $row)
		{
			$children[(int) $row['parent_id']][] = $row;
		}

		$out = array();
		$walk = function ($parent_id, $depth, $path) use (&$walk, &$out, $children) {
			if (empty($children[$parent_id]))
			{
				return;
			}
			foreach ($children[$parent_id] as $row)
			{
				$row['depth'] = $depth;
				$row['path'] = $path === '' ? $row['name'] : $path . ' › ' . $row['name'];
				$out[] = $row;
				$walk((int) $row['id'], $depth + 1, $row['path']);
			}
		};
		$walk(0, 0, '');
		return $out;
	}

	/** [id => path] untuk dropdown. */
	public function options($only_active = TRUE)
	{
		$out = array();
		foreach ($this->tree($only_active) as $row)
		{
			$out[$row['id']] = $row['path'];
		}
		return $out;
	}

	public function find($id)
	{
		return $this->db->where('id', (int) $id)->get($this->table)->row_array();
	}

	/** id kategori beserta seluruh turunannya (untuk filter & laporan). */
	public function descendant_ids($id)
	{
		$ids = array((int) $id);
		$queue = array((int) $id);
		while ($queue)
		{
			$rows = $this->db->select('id')->where_in('parent_id', $queue)->get($this->table)->result_array();
			$queue = array_map('intval', array_column($rows, 'id'));
			$ids = array_merge($ids, $queue);
		}
		return $ids;
	}

	public function name_exists($name, $parent_id, $except_id = NULL)
	{
		$this->db->where('name', $name);
		$parent_id ? $this->db->where('parent_id', (int) $parent_id) : $this->db->where('parent_id IS NULL', NULL, FALSE);
		if ($except_id)
		{
			$this->db->where('id !=', (int) $except_id);
		}
		return $this->db->count_all_results($this->table) > 0;
	}

	public function save(array $data, $id = NULL)
	{
		$row = array(
			'name'        => $data['name'],
			'parent_id'   => $data['parent_id'] ?: NULL,
			'description' => $data['description'] !== '' ? $data['description'] : NULL,
			'is_active'   => $data['is_active'] ? 1 : 0,
		);
		if ($id)
		{
			$this->db->where('id', (int) $id)->update($this->table, $row);
			return (int) $id;
		}
		$this->db->insert($this->table, $row);
		return (int) $this->db->insert_id();
	}

	/** Alasan kategori tidak bisa dihapus, atau NULL kalau boleh. */
	public function delete_blocker($id)
	{
		if ($this->db->where('parent_id', (int) $id)->count_all_results($this->table) > 0)
		{
			return 'Kategori masih punya sub-kategori.';
		}
		if ($this->db->where('category_id', (int) $id)->count_all_results($this->item_table) > 0)
		{
			return $this->item_table === 'ingredients' ? 'Kategori masih dipakai bahan baku.' : 'Kategori masih dipakai menu.';
		}
		return NULL;
	}

	public function delete($id)
	{
		return $this->db->where('id', (int) $id)->delete($this->table);
	}
}
