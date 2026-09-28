<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Kategori bahan baku bertingkat (PRD 2.1.1 Kategori Bahan).
 */
class Categories extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->require_permission('inventory.view');
		$this->load->model('Category_model');
	}

	public function index()
	{
		$this->render('inventory/categories/index', array(
			'title' => 'Kategori Bahan',
			'rows'  => $this->Category_model->tree(),
		));
	}

	public function create()
	{
		$this->require_permission('inventory.create');
		$this->_form(NULL);
	}

	public function edit($id = NULL)
	{
		$this->require_permission('inventory.edit');
		$cat = $this->Category_model->find($id);
		if ( ! $cat)
		{
			show_404();
		}
		$this->_form($cat);
	}

	public function delete($id = NULL)
	{
		$this->require_permission('inventory.delete');
		if ($this->input->method() !== 'post')
		{
			show_error('Method not allowed', 405);
		}
		$cat = $this->Category_model->find($id);
		if ( ! $cat)
		{
			show_404();
		}
		$blocker = $this->Category_model->delete_blocker($cat['id']);
		if ($blocker)
		{
			flash('warning', $blocker . ' Nonaktifkan saja kalau tidak dipakai lagi.');
		}
		else
		{
			$this->Category_model->delete($cat['id']);
			$this->audit->log('category_delete', array('table_name' => 'ingredient_categories', 'record_id' => $cat['id'], 'detail' => array('name' => $cat['name'])));
			flash('success', "Kategori {$cat['name']} dihapus.");
		}
		redirect('inventory/categories');
	}

	protected function _form($cat)
	{
		$is_edit = (bool) $cat;
		$errors = array();
		$input = $is_edit ? $cat : array('name' => '', 'parent_id' => (int) $this->input->get('parent_id'), 'description' => '', 'is_active' => 1);

		// Kategori & turunannya tidak boleh jadi induk dirinya sendiri.
		$exclude = $is_edit ? $this->Category_model->descendant_ids($cat['id']) : array();
		$parents = array_diff_key($this->Category_model->options(FALSE), array_flip($exclude));

		if ($this->input->method() === 'post')
		{
			$input['name'] = trim((string) $this->input->post('name'));
			$input['parent_id'] = (int) $this->input->post('parent_id');
			$input['description'] = trim((string) $this->input->post('description'));
			$input['is_active'] = $this->input->post('is_active') ? 1 : 0;

			if ($input['name'] === '' OR mb_strlen($input['name']) > 100)
			{
				$errors[] = 'Nama kategori wajib diisi (maks. 100 karakter).';
			}
			if ($input['parent_id'] && ! isset($parents[$input['parent_id']]))
			{
				$errors[] = 'Induk kategori tidak valid.';
			}
			if (empty($errors) && $this->Category_model->name_exists($input['name'], $input['parent_id'], $is_edit ? $cat['id'] : NULL))
			{
				$errors[] = 'Nama kategori sudah ada di induk yang sama.';
			}

			if (empty($errors))
			{
				$id = $this->Category_model->save($input, $is_edit ? $cat['id'] : NULL);
				$this->audit->log($is_edit ? 'category_update' : 'category_create', array(
					'table_name' => 'ingredient_categories', 'record_id' => $id, 'detail' => array('name' => $input['name']),
				));
				flash('success', "Kategori {$input['name']} disimpan.");
				redirect('inventory/categories');
			}
		}

		$this->render('inventory/categories/form', array(
			'title'   => $is_edit ? 'Edit Kategori' : 'Tambah Kategori',
			'cat'     => $cat,
			'input'   => $input,
			'parents' => $parents,
			'errors'  => $errors,
		));
	}
}
