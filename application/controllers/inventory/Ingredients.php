<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Master bahan baku (PRD 2.1.1).
 */
class Ingredients extends MY_Controller {

	const IMAGE_DIR = 'uploads/ingredients/';
	const IMAGE_MAX_BYTES = 2097152; // 2 MB

	public function __construct()
	{
		parent::__construct();
		$this->require_permission('inventory.view');
		$this->load->model(array('Ingredient_model', 'Category_model', 'Supplier_model', 'Stock_model'));
	}

	public function index()
	{
		$f = array(
			'q'           => trim((string) $this->input->get('q')),
			'category_id' => (int) $this->input->get('category_id'),
			'status'      => array_key_exists($this->input->get('status'), Ingredient_model::$statuses) ? $this->input->get('status') : '',
			'stock'       => in_array($this->input->get('stock'), array('low', 'reorder', 'over', 'empty'), TRUE) ? $this->input->get('stock') : '',
		);
		if ($this->input->get('status') === NULL)
		{
			$f['status'] = 'active';
		}
		$query = $f;
		if ($f['category_id'])
		{
			$query['category_ids'] = $this->Category_model->descendant_ids($f['category_id']);
		}

		$this->render('inventory/ingredients/index', array(
			'title'       => 'Bahan Baku',
			'rows'        => $this->Ingredient_model->all($query),
			'filters'     => $f,
			'categories'  => $this->Category_model->options(FALSE),
			'can_cost'    => can('inventory.view_cost'),
		));
	}

	public function show($id = NULL)
	{
		$ing = $this->Ingredient_model->find($id);
		if ( ! $ing)
		{
			show_404();
		}
		$this->load->library('stock_service', NULL, 'stock'); // label alasan di kartu stok
		$from = $this->_date($this->input->get('from'), date('Y-m-d', strtotime('-30 days')));
		$to = $this->_date($this->input->get('to'), date('Y-m-d'));

		$this->render('inventory/ingredients/show', array(
			'title'     => $ing['name'],
			'ing'       => $ing,
			'units'     => $this->Ingredient_model->units($ing['id']),
			'batches'   => $this->Stock_model->open_batches($ing['id']),
			'movements' => $this->Stock_model->movements(array('ingredient_id' => $ing['id'], 'from' => $from, 'to' => $to), 500),
			'from'      => $from,
			'to'        => $to,
			'can_cost'  => can('inventory.view_cost'),
			'expiry_days' => (int) setting('expiry_alert_days', 7),
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
		$ing = $this->Ingredient_model->find($id);
		if ( ! $ing)
		{
			show_404();
		}
		$this->_form($ing);
	}

	/**
	 * Hapus hanya kalau belum pernah ada pergerakan stok. Bahan yang sudah
	 * punya riwayat harus di-set "Tidak diproduksi lagi" supaya riwayat & laporan utuh.
	 */
	public function delete($id = NULL)
	{
		$this->require_permission('inventory.delete');
		$this->_require_post();
		$ing = $this->Ingredient_model->find($id);
		if ( ! $ing)
		{
			show_404();
		}
		if ($this->Ingredient_model->has_movements($ing['id']))
		{
			flash('warning', "{$ing['name']} sudah punya riwayat stok sehingga tidak bisa dihapus. Ubah statusnya menjadi \"Tidak diproduksi lagi\".");
			redirect('inventory/ingredients/show/' . $ing['id']);
		}
		$this->Ingredient_model->delete($ing['id']);
		$this->_delete_image($ing['image_path']);
		$this->audit->log('ingredient_delete', array('table_name' => 'ingredients', 'record_id' => $ing['id'], 'detail' => array('code' => $ing['code'], 'name' => $ing['name'])));
		flash('success', "Bahan {$ing['name']} dihapus.");
		redirect('inventory/ingredients');
	}

	/** Export daftar bahan (sesuai filter) ke CSV. */
	public function export()
	{
		$f = array('q' => trim((string) $this->input->get('q')), 'status' => (string) $this->input->get('status'));
		if ($this->input->get('category_id'))
		{
			$f['category_ids'] = $this->Category_model->descendant_ids((int) $this->input->get('category_id'));
		}
		$can_cost = can('inventory.view_cost');
		$rows = array();
		foreach ($this->Ingredient_model->all($f) as $r)
		{
			$line = array($r['code'], $r['name'], $r['category_name'], $r['unit'], $r['qty_on_hand'], $r['min_stock'], $r['max_stock'],
				$r['reorder_point'], $r['reorder_qty'], $r['location'], $r['supplier_name'], Ingredient_model::$statuses[$r['status']]);
			if ($can_cost)
			{
				$line[] = $r['current_price'];
				$line[] = $r['stock_value'];
			}
			$rows[] = $line;
		}
		$header = array('Kode', 'Nama', 'Kategori', 'Satuan', 'Stok', 'Min', 'Max', 'Reorder Point', 'Reorder Qty', 'Lokasi', 'Supplier', 'Status');
		if ($can_cost)
		{
			array_push($header, 'Harga Terakhir', 'Nilai Stok');
		}
		send_csv('bahan-baku-' . date('Ymd') . '.csv', $header, $rows);
	}

	protected function _form($ing)
	{
		$is_edit = (bool) $ing;
		$errors = array();
		$input = $is_edit ? $ing : array(
			'code' => '', 'name' => '', 'description' => '', 'category_id' => '', 'unit' => '',
			'default_supplier_id' => '', 'min_stock' => 0, 'max_stock' => 0, 'reorder_point' => 0,
			'reorder_qty' => 0, 'location' => '', 'status' => 'active', 'notes' => '', 'image_path' => NULL,
			'current_price' => 0,
		);
		$units = $is_edit ? array_map(function ($u) { return array($u['unit'], (float) $u['factor']); }, $this->Ingredient_model->units($ing['id'])) : array();

		if ($this->input->method() === 'post')
		{
			foreach (array('code', 'name', 'description', 'unit', 'location', 'notes') as $k)
			{
				$input[$k] = trim((string) $this->input->post($k));
			}
			$input['code'] = strtoupper($input['code']);
			$input['category_id'] = (int) $this->input->post('category_id');
			$input['default_supplier_id'] = (int) $this->input->post('default_supplier_id');
			$input['status'] = (string) $this->input->post('status');
			foreach (array('min_stock', 'max_stock', 'reorder_point', 'reorder_qty') as $k)
			{
				$input[$k] = num_in($this->input->post($k));
				if (is_nan($input[$k]) OR $input[$k] < 0)
				{
					$errors[] = 'Nilai min/max/reorder harus angka ≥ 0.';
					$input[$k] = 0;
				}
			}
			if ( ! $is_edit && can('inventory.view_cost'))
			{
				$input['current_price'] = num_in($this->input->post('current_price'));
				if (is_nan($input['current_price']) OR $input['current_price'] < 0)
				{
					$errors[] = 'Harga acuan harus angka ≥ 0.';
					$input['current_price'] = 0;
				}
			}

			// Satuan alternatif
			$units = array();
			$alt_units = (array) $this->input->post('alt_unit');
			$alt_factors = (array) $this->input->post('alt_factor');
			foreach ($alt_units as $i => $u)
			{
				$u = trim((string) $u);
				$factor = num_in(isset($alt_factors[$i]) ? $alt_factors[$i] : '');
				if ($u === '' && ($factor === 0.0 OR is_nan($factor)))
				{
					continue;
				}
				if ($u === '' OR is_nan($factor) OR $factor <= 0)
				{
					$errors[] = 'Setiap satuan alternatif harus punya nama dan faktor konversi > 0.';
					continue;
				}
				if (strcasecmp($u, $input['unit']) === 0 OR in_array(strtolower($u), array_map('strtolower', array_column($units, 0)), TRUE))
				{
					$errors[] = "Satuan \"$u\" dobel.";
					continue;
				}
				$units[] = array(mb_substr($u, 0, 20), round($factor, 6));
			}

			if ($input['name'] === '')
			{
				$errors[] = 'Nama bahan wajib diisi.';
			}
			if ($input['unit'] === '' OR mb_strlen($input['unit']) > 20)
			{
				$errors[] = 'Satuan standar wajib diisi (maks. 20 karakter).';
			}
			if ($is_edit && $input['unit'] !== $ing['unit'] && $this->Ingredient_model->has_movements($ing['id']))
			{
				$errors[] = 'Satuan standar tidak bisa diubah karena bahan sudah punya riwayat stok.';
				$input['unit'] = $ing['unit'];
			}
			if ($input['code'] !== '' && ! preg_match('/^[A-Z0-9._-]{2,30}$/', $input['code']))
			{
				$errors[] = 'Kode hanya boleh huruf, angka, titik, strip (2-30 karakter). Kosongkan untuk kode otomatis.';
			}
			elseif ($input['code'] !== '' && $this->Ingredient_model->code_exists($input['code'], $is_edit ? $ing['id'] : NULL))
			{
				$errors[] = 'Kode sudah dipakai bahan lain.';
			}
			if ( ! array_key_exists($input['status'], Ingredient_model::$statuses))
			{
				$errors[] = 'Status tidak valid.';
			}
			if ($input['max_stock'] > 0 && $input['min_stock'] > $input['max_stock'])
			{
				$errors[] = 'Stok minimum tidak boleh lebih besar dari stok maksimum.';
			}
			if ($input['category_id'] && ! $this->Category_model->find($input['category_id']))
			{
				$errors[] = 'Kategori tidak ditemukan.';
			}
			if ($input['default_supplier_id'] && ! $this->Supplier_model->find($input['default_supplier_id']))
			{
				$errors[] = 'Supplier tidak ditemukan.';
			}

			$new_image = NULL;
			if (empty($errors) && ! empty($_FILES['image']['name']))
			{
				$new_image = $this->_upload_image($errors);
			}

			if (empty($errors))
			{
				$data = $input;
				if ($new_image)
				{
					$data['image_path'] = $new_image;
				}
				elseif ($is_edit && $this->input->post('remove_image'))
				{
					$data['image_path'] = NULL;
				}
				else
				{
					unset($data['image_path']);
				}
				if ($is_edit)
				{
					unset($data['current_price']);
				}

				$id = $this->Ingredient_model->save($data, $units, $is_edit ? $ing['id'] : NULL);
				if ($id === FALSE)
				{
					$errors[] = 'Gagal menyimpan. Coba lagi.';
					$this->_delete_image($new_image);
				}
				else
				{
					if ($is_edit && array_key_exists('image_path', $data))
					{
						$this->_delete_image($ing['image_path']);
					}
					$saved = $this->Ingredient_model->find($id);
					$this->audit->log($is_edit ? 'ingredient_update' : 'ingredient_create', array(
						'table_name' => 'ingredients', 'record_id' => $id,
						'detail' => array('code' => $saved['code'], 'name' => $saved['name']),
					));
					flash('success', "Bahan {$saved['name']} ({$saved['code']}) disimpan.");
					redirect('inventory/ingredients/show/' . $id);
				}
			}
		}

		$this->render('inventory/ingredients/form', array(
			'title'      => $is_edit ? 'Edit Bahan Baku' : 'Tambah Bahan Baku',
			'ing'        => $ing,
			'input'      => $input,
			'units'      => $units,
			'categories' => $this->Category_model->options(TRUE),
			'suppliers'  => $this->Supplier_model->options(),
			'errors'     => $errors,
			'has_movements' => $is_edit && $this->Ingredient_model->has_movements($ing['id']),
		));
	}

	/**
	 * Validasi gambar dengan getimagesize() (ekstensi fileinfo tidak tersedia
	 * di server ini) dan simpan dengan nama acak.
	 */
	protected function _upload_image(array &$errors)
	{
		$file = $_FILES['image'];
		if ($file['error'] !== UPLOAD_ERR_OK)
		{
			$errors[] = $file['error'] === UPLOAD_ERR_INI_SIZE ? 'Ukuran foto terlalu besar.' : 'Upload foto gagal.';
			return NULL;
		}
		if ($file['size'] > self::IMAGE_MAX_BYTES)
		{
			$errors[] = 'Ukuran foto maksimal 2 MB.';
			return NULL;
		}
		$info = @getimagesize($file['tmp_name']);
		$types = array(IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp');
		if ( ! $info OR ! isset($types[$info[2]]))
		{
			$errors[] = 'Foto harus berupa gambar JPG, PNG, atau WEBP.';
			return NULL;
		}
		$dir = FCPATH . self::IMAGE_DIR;
		if ( ! is_dir($dir) && ! @mkdir($dir, 0755, TRUE))
		{
			$errors[] = 'Folder upload tidak bisa dibuat.';
			return NULL;
		}
		$name = bin2hex(random_bytes(12)) . '.' . $types[$info[2]];
		if ( ! move_uploaded_file($file['tmp_name'], $dir . $name))
		{
			$errors[] = 'Gagal menyimpan foto.';
			return NULL;
		}
		return self::IMAGE_DIR . $name;
	}

	protected function _delete_image($path)
	{
		if ($path && strpos($path, self::IMAGE_DIR) === 0 && is_file(FCPATH . $path))
		{
			@unlink(FCPATH . $path);
		}
	}

	protected function _date($value, $default)
	{
		return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) ? $value : $default;
	}

	protected function _require_post()
	{
		if ($this->input->method() !== 'post')
		{
			show_error('Method not allowed', 405);
		}
	}
}
