<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Tambahan / topping berbayar yang bisa dipilih di POS
 * (PRD 2.4.2 Step 2: "Topping Telur +3K"). Bila dihubungkan ke bahan baku,
 * stok bahan ikut berkurang saat terjual.
 */
class Modifiers extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->require_permission('menu.view');
	}

	public function index()
	{
		$this->load->model('Ingredient_model');
		$rows = $this->db->select('m.*, g.name AS ingredient_name, g.unit')->from('modifiers m')->join('ingredients g', 'g.id = m.ingredient_id', 'left')
			->order_by('m.sort_order')->order_by('m.name')->get()->result_array();
		$this->render('menu/modifiers/index', array(
			'title'       => 'Tambahan & Topping',
			'rows'        => $rows,
			'ingredients' => $this->Ingredient_model->for_stock_form(),
		));
	}

	public function save()
	{
		$this->require_permission('menu.edit');
		if ($this->input->method() !== 'post')
		{
			show_error('Method not allowed', 405);
		}
		$id = (int) $this->input->post('id');
		$price = num_in($this->input->post('price'), NAN);
		$qty = num_in($this->input->post('qty'), NULL);
		$ing = (int) $this->input->post('ingredient_id');
		$unit = trim((string) $this->input->post('unit'));
		$name = trim((string) $this->input->post('name'));

		$error = NULL;
		if ($name === '' OR mb_strlen($name) > 100)
		{
			$error = 'Nama tambahan wajib diisi (maks. 100 karakter).';
		}
		elseif (is_nan($price) OR $price < 0)
		{
			$error = 'Harga tambahan harus angka ≥ 0.';
		}
		elseif ( ! $id && $price > 0 && ! can('menu.edit_price'))
		{
			$error = 'Anda tidak punya izin mengatur harga (menu.edit_price).';
		}
		$qty_std = NULL;
		if ( ! $error && $ing)
		{
			$this->load->model('Ingredient_model');
			$ingredient = $this->Ingredient_model->find($ing);
			$factor = $ingredient ? $this->Ingredient_model->unit_factor($ingredient, $unit) : NULL;
			if ( ! $ingredient OR $factor === NULL)
			{
				$error = 'Bahan atau satuan tidak valid.';
			}
			elseif ($qty === NULL OR is_nan($qty) OR $qty <= 0)
			{
				$error = 'Isi jumlah bahan per tambahan.';
			}
			else
			{
				$qty_std = round($qty * $factor, 4);
			}
		}
		if ($error)
		{
			flash('danger', $error);
			redirect('menu/modifiers');
		}
		$row = array('name' => $name, 'ingredient_id' => $ing ?: NULL, 'qty_std' => $qty_std,
			'sort_order' => (int) $this->input->post('sort_order'), 'is_active' => $this->input->post('is_active') ? 1 : 0);
		if (can('menu.edit_price'))
		{
			$row['price'] = round($price, 2);
		}
		if ($id)
		{
			$this->db->where('id', $id)->update('modifiers', $row);
		}
		else
		{
			$row['price'] = isset($row['price']) ? $row['price'] : 0;
			$this->db->insert('modifiers', $row);
			$id = (int) $this->db->insert_id();
		}
		$this->audit->log('modifier_save', array('table_name' => 'modifiers', 'record_id' => $id, 'detail' => $row));
		flash('success', "Tambahan $name disimpan.");
		redirect('menu/modifiers');
	}
}
