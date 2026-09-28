<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Kategori menu (PRD 2.3.1 Menu Categories).
 */
class Categories extends MY_Controller {

	const IMAGE_DIR = 'uploads/menu-categories/';

	public function __construct()
	{
		parent::__construct();
		if ( ! can_any(array('menu.view', 'menu.view_recipe')))
		{
			$this->require_permission('menu.view');
		}
		$this->load->model('Menu_category_model');
	}

	public function index()
	{
		$this->render('menu/categories/index', array(
			'title' => 'Kategori Menu',
			'rows'  => $this->Menu_category_model->tree(),
		));
	}

	public function create()
	{
		$this->require_permission('menu.create');
		$this->_form(NULL);
	}

	public function edit($id = NULL)
	{
		$this->require_permission('menu.edit');
		$cat = $this->Menu_category_model->find($id);
		if ( ! $cat)
		{
			show_404();
		}
		$this->_form($cat);
	}

	public function delete($id = NULL)
	{
		$this->require_permission('menu.delete');
		if ($this->input->method() !== 'post')
		{
			show_error('Method not allowed', 405);
		}
		$cat = $this->Menu_category_model->find($id);
		if ( ! $cat)
		{
			show_404();
		}
		$blocker = $this->Menu_category_model->delete_blocker($cat['id']);
		if ($blocker)
		{
			flash('warning', $blocker . ' Nonaktifkan atau sembunyikan saja.');
		}
		else
		{
			$this->Menu_category_model->delete($cat['id']);
			if ($cat['image_path'] && strpos($cat['image_path'], self::IMAGE_DIR) === 0)
			{
				@unlink(FCPATH . $cat['image_path']);
			}
			$this->audit->log('menu_category_delete', array('table_name' => 'menu_categories', 'record_id' => $cat['id'], 'detail' => array('name' => $cat['name'])));
			flash('success', "Kategori {$cat['name']} dihapus.");
		}
		redirect('menu/categories');
	}

	protected function _form($cat)
	{
		$is_edit = (bool) $cat;
		$errors = array();
		$input = $is_edit ? $cat : array('name' => '', 'parent_id' => (int) $this->input->get('parent_id'), 'description' => '',
			'sort_order' => 0, 'is_hidden' => 0, 'is_active' => 1, 'image_path' => NULL);
		$exclude = $is_edit ? $this->Menu_category_model->descendant_ids($cat['id']) : array();
		$parents = array_diff_key($this->Menu_category_model->options(FALSE), array_flip($exclude));

		if ($this->input->method() === 'post')
		{
			$input['name'] = trim((string) $this->input->post('name'));
			$input['parent_id'] = (int) $this->input->post('parent_id');
			$input['description'] = trim((string) $this->input->post('description'));
			$input['sort_order'] = (int) $this->input->post('sort_order');
			$input['is_hidden'] = $this->input->post('is_hidden') ? 1 : 0;
			$input['is_active'] = $this->input->post('is_active') ? 1 : 0;

			if ($input['name'] === '' OR mb_strlen($input['name']) > 100)
			{
				$errors[] = 'Nama kategori wajib diisi (maks. 100 karakter).';
			}
			if ($input['parent_id'] && ! isset($parents[$input['parent_id']]))
			{
				$errors[] = 'Induk kategori tidak valid.';
			}
			if (empty($errors) && $this->Menu_category_model->name_exists($input['name'], $input['parent_id'], $is_edit ? $cat['id'] : NULL))
			{
				$errors[] = 'Nama kategori sudah ada di induk yang sama.';
			}

			$data = $input;
			unset($data['image_path']);
			if (empty($errors) && ! empty($_FILES['image']['name']))
			{
				$file = $_FILES['image'];
				$info = $file['error'] === UPLOAD_ERR_OK && $file['size'] <= 2097152 ? @getimagesize($file['tmp_name']) : FALSE;
				$types = array(IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp');
				if ( ! $info OR ! isset($types[$info[2]]))
				{
					$errors[] = 'Gambar harus JPG, PNG, atau WEBP maksimal 2 MB.';
				}
				else
				{
					$dir = FCPATH . self::IMAGE_DIR;
					is_dir($dir) OR @mkdir($dir, 0755, TRUE);
					$name = bin2hex(random_bytes(12)) . '.' . $types[$info[2]];
					if (move_uploaded_file($file['tmp_name'], $dir . $name))
					{
						$data['image_path'] = self::IMAGE_DIR . $name;
					}
					else
					{
						$errors[] = 'Gagal menyimpan gambar.';
					}
				}
			}

			if (empty($errors))
			{
				$id = $this->Menu_category_model->save($data, $is_edit ? $cat['id'] : NULL);
				if ($is_edit && isset($data['image_path']) && $cat['image_path'] && strpos($cat['image_path'], self::IMAGE_DIR) === 0)
				{
					@unlink(FCPATH . $cat['image_path']);
				}
				$this->audit->log($is_edit ? 'menu_category_update' : 'menu_category_create', array('table_name' => 'menu_categories', 'record_id' => $id, 'detail' => array('name' => $input['name'])));
				flash('success', "Kategori {$input['name']} disimpan.");
				redirect('menu/categories');
			}
		}

		$this->render('menu/categories/form', array(
			'title'   => $is_edit ? 'Edit Kategori Menu' : 'Tambah Kategori Menu',
			'cat'     => $cat,
			'input'   => $input,
			'parents' => $parents,
			'errors'  => $errors,
		));
	}
}
