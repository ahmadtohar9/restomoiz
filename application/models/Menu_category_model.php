<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/Category_model.php';

/**
 * Kategori menu bertingkat (PRD 2.3.1): urutan tampil, gambar, dan
 * kategori tersembunyi (item internal yang tidak tampil ke pelanggan).
 */
class Menu_category_model extends Category_model {

	protected $table = 'menu_categories';
	protected $item_table = 'menus';
	protected $order = 'c.sort_order ASC, c.name ASC';

	public function save(array $data, $id = NULL)
	{
		$row = array(
			'name'        => $data['name'],
			'parent_id'   => $data['parent_id'] ?: NULL,
			'description' => $data['description'] !== '' ? $data['description'] : NULL,
			'sort_order'  => (int) $data['sort_order'],
			'is_hidden'   => $data['is_hidden'] ? 1 : 0,
			'is_active'   => $data['is_active'] ? 1 : 0,
		);
		if (array_key_exists('image_path', $data))
		{
			$row['image_path'] = $data['image_path'];
		}
		if ($id)
		{
			$this->db->where('id', (int) $id)->update($this->table, $row);
			return (int) $id;
		}
		$this->db->insert($this->table, $row);
		return (int) $this->db->insert_id();
	}

	/** id kategori yang tersembunyi (termasuk turunannya) untuk menu publik. */
	public function hidden_ids()
	{
		$ids = array();
		foreach ($this->db->select('id')->where('is_hidden', 1)->or_where('is_active', 0)->get($this->table)->result_array() as $r)
		{
			$ids = array_merge($ids, $this->descendant_ids($r['id']));
		}
		return array_unique($ids);
	}
}
