<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Master menu, varian, resep, dan harga (PRD 2.3.1 - 2.3.3).
 *
 * Hak lihat: menu.view (menu & harga jual), menu.view_recipe (resep, mis.
 * Staff Dapur tanpa harga), menu.view_cogs (COGS & margin).
 */
class Items extends MY_Controller {

	const IMAGE_DIR = 'uploads/menus/';

	public function __construct()
	{
		parent::__construct();
		if ( ! can_any(array('menu.view', 'menu.view_recipe')))
		{
			$this->require_permission('menu.view');
		}
		$this->load->model(array('Menu_model', 'Menu_category_model'));
		$this->load->library('menu_service');
	}

	public function index()
	{
		$this->menu_service->ensure_daily_snapshot();
		$f = array(
			'q'           => trim((string) $this->input->get('q')),
			'category_id' => (int) $this->input->get('category_id'),
			'status'      => array_key_exists($this->input->get('status'), Menu_model::$statuses) ? $this->input->get('status') : '',
			'avail'       => in_array($this->input->get('avail'), array('out', 'low', 'margin'), TRUE) ? $this->input->get('avail') : '',
		);
		$q = $f;
		if ($f['category_id'])
		{
			$q['category_ids'] = $this->Menu_category_model->descendant_ids($f['category_id']);
		}
		$menus = $this->Menu_model->all($q);
		$info = $this->menu_service->variant_info($this->_variant_ids($menus));
		$warn = (float) setting('menu_margin_warning', 40);

		// Filter turunan (ketersediaan / margin) dihitung setelah info varian ada.
		if ($f['avail'])
		{
			$menus = array_values(array_filter($menus, function ($m) use ($info, $f, $warn) {
				foreach ($m['variants'] as $v)
				{
					$i = $info[$v['id']];
					$a = Menu_service::availability($m['status'], $v['is_active'], $i['portions']);
					if ($f['avail'] === 'out' && in_array($a, array('out', 'manual_out'), TRUE)) return TRUE;
					if ($f['avail'] === 'low' && $a === 'low') return TRUE;
					if ($f['avail'] === 'margin' && $i['margin_pct'] !== NULL && $i['margin_pct'] < $warn) return TRUE;
				}
				return FALSE;
			}));
		}

		$this->render('menu/items/index', array(
			'title'      => 'Menu',
			'menus'      => $menus,
			'info'       => $info,
			'filters'    => $f,
			'categories' => $this->Menu_category_model->options(FALSE),
			'warn'       => $warn,
		));
	}

	public function show($id = NULL)
	{
		$m = $this->_menu($id);
		$info = $this->menu_service->variant_info($this->_variant_ids(array($m)));
		$details = array();
		foreach ($m['variants'] as $v)
		{
			$details[$v['id']] = array(
				'recipe'  => can_any(array('menu.view_recipe', 'menu.view_cogs')) ? $this->Menu_model->recipe($v['id']) : array(),
				'prices'  => can('menu.view') ? $this->Menu_model->price_history($v['id']) : array(),
				'cogs'    => can('menu.view_cogs') ? $this->Menu_model->cogs_history($v['id']) : array(),
			);
		}
		$this->load->library('barcode');
		$this->render('menu/items/show', array(
			'title'   => $m['name'],
			'm'       => $m,
			'info'    => $info,
			'details' => $details,
			'warn'    => (float) setting('menu_margin_warning', 40),
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
		$this->_form($this->_menu($id));
	}

	public function delete($id = NULL)
	{
		$this->require_permission('menu.delete');
		$this->_require_post();
		$m = $this->_menu($id);
		foreach ($m['variants'] as $v)
		{
			if ($this->menu_service->variant_used($v['id']))
			{
				flash('warning', "{$m['name']} sudah pernah terjual sehingga tidak bisa dihapus. Ubah statusnya menjadi Nonaktif.");
				redirect('menu/items/show/' . $m['id']);
			}
		}
		$this->db->where('id', $m['id'])->delete('menus');
		$this->_delete_image($m['image_path']);
		$this->audit->log('menu_delete', array('table_name' => 'menus', 'record_id' => $m['id'], 'detail' => array('code' => $m['code'], 'name' => $m['name'])));
		flash('success', "Menu {$m['name']} dihapus.");
		redirect('menu/items');
	}

	/** Tandai habis / tersedia lagi secara manual. */
	public function toggle_stock($id = NULL)
	{
		$this->require_permission('menu.edit');
		$this->_require_post();
		$m = $this->_menu($id);
		$new = $m['status'] === 'out_of_stock' ? 'active' : 'out_of_stock';
		if ($m['status'] === 'inactive')
		{
			flash('warning', 'Menu nonaktif. Aktifkan dulu lewat Edit.');
			redirect('menu/items/show/' . $m['id']);
		}
		$this->db->where('id', $m['id'])->update('menus', array('status' => $new));
		$this->audit->log('menu_stock_status', array('table_name' => 'menus', 'record_id' => $m['id'], 'detail' => array('name' => $m['name'], 'status' => $new)));
		flash('success', $new === 'out_of_stock' ? "{$m['name']} ditandai habis." : "{$m['name']} tersedia lagi.");
		redirect($this->input->post('back') === 'list' ? 'menu/items' : 'menu/items/show/' . $m['id']);
	}

	/** Edit resep satu varian. */
	public function recipe($variant_id = NULL)
	{
		$this->require_permission('menu.edit');
		$variant = $this->Menu_model->find_variant($variant_id);
		if ( ! $variant)
		{
			show_404();
		}
		$this->load->model('Ingredient_model');
		$ingredients = $this->Ingredient_model->for_stock_form();
		$siblings = $this->db->select('id, name')->where('menu_id', $variant['menu_id'])->where('id !=', $variant['id'])->get('menu_variants')->result_array();
		$errors = array();
		$lines = array_map(function ($r) {
			return array('ingredient_id' => $r['ingredient_id'], 'qty' => (float) $r['qty'], 'unit' => $r['unit'], 'notes' => (string) $r['notes']);
		}, $this->Menu_model->recipe($variant['id']));

		// Satuan yang sudah tersimpan di resep (milik varian ini & saudaranya) tetap bisa dipilih
		// walau satuan alternatif itu sudah dihapus dari bahan. Tanpa ini, form diam-diam beralih
		// ke satuan standar (mis. 5 gram jadi 5 kg) dan menyimpan jumlah yang salah.
		$recipe_variants = array_merge(array((int) $variant['id']), array_map('intval', array_column($siblings, 'id')));
		foreach ($this->db->select('ingredient_id, unit, factor')->where_in('variant_id', $recipe_variants)->get('menu_recipes')->result_array() as $saved)
		{
			if ( ! isset($ingredients[$saved['ingredient_id']]))
			{
				continue;
			}
			$known = array_column($ingredients[$saved['ingredient_id']]['units'], 'unit');
			if ( ! in_array($saved['unit'], $known, TRUE))
			{
				$ingredients[$saved['ingredient_id']]['units'][] = array('unit' => $saved['unit'], 'factor' => (float) $saved['factor']);
			}
		}

		$copied = FALSE;
		if ($this->input->get('copy_from') && $this->input->method() !== 'post')
		{
			$src = (int) $this->input->get('copy_from');
			if (in_array($src, array_map('intval', array_column($siblings, 'id')), TRUE))
			{
				$lines = array_map(function ($r) {
					return array('ingredient_id' => $r['ingredient_id'], 'qty' => (float) $r['qty'], 'unit' => $r['unit'], 'notes' => (string) $r['notes']);
				}, $this->Menu_model->recipe($src));
				$copied = TRUE;
			}
		}

		if ($this->input->method() === 'post')
		{
			$lines = array();
			$prepared = array();
			foreach ((array) $this->input->post('lines') as $l)
			{
				$line = array(
					'ingredient_id' => (int) (isset($l['ingredient_id']) ? $l['ingredient_id'] : 0),
					'qty'   => isset($l['qty']) ? trim((string) $l['qty']) : '',
					'unit'  => isset($l['unit']) ? trim((string) $l['unit']) : '',
					'notes' => isset($l['notes']) ? trim((string) $l['notes']) : '',
				);
				if ( ! $line['ingredient_id'] && $line['qty'] === '')
				{
					continue;
				}
				$lines[] = $line;
				$n = count($lines);
				if ( ! isset($ingredients[$line['ingredient_id']]))
				{
					$errors[] = "Baris $n: pilih bahan (aktif).";
					continue;
				}
				$ing = $ingredients[$line['ingredient_id']];
				$qty = num_in($line['qty']);
				$factor = NULL;
				foreach ($ing['units'] as $u)
				{
					if ($u['unit'] === $line['unit'])
					{
						$factor = $u['factor'];
					}
				}
				if (is_nan($qty) OR $qty <= 0)
				{
					$errors[] = "Baris $n ({$ing['name']}): jumlah per porsi harus lebih dari 0.";
				}
				elseif ($factor === NULL)
				{
					$errors[] = "Baris $n ({$ing['name']}): satuan tidak valid.";
				}
				else
				{
					$prepared[] = array('ingredient_id' => $ing['id'], 'qty' => round($qty, 3), 'unit' => $line['unit'], 'factor' => $factor, 'notes' => $line['notes']);
				}
			}

			if (empty($errors))
			{
				try
				{
					$this->menu_service->run(function ($svc) use ($variant, $prepared) {
						$svc->save_recipe($variant['id'], $prepared);
						$svc->snapshot_cogs(array($variant['id']));
					});
					$info = $this->menu_service->variant_info(array($variant['id']));
					$this->audit->log('menu_recipe_update', array('table_name' => 'menu_recipes', 'record_id' => $variant['id'],
						'detail' => array('menu' => $variant['menu_name'], 'variant' => $variant['name'], 'lines' => count($prepared), 'cogs' => $info[$variant['id']]['cogs'])));
					$vi = $info[$variant['id']];
					flash('success', 'Resep disimpan. HPP dari resep: ' . rupiah($vi['cogs_recipe']) . ' per porsi'
						. ($vi['cogs_manual'] !== NULL ? ' (yang dipakai tetap HPP manual ' . rupiah($vi['cogs_manual']) . ').' : '.'));
					redirect('menu/items/show/' . $variant['menu_id'] . '#v' . $variant['id']);
				}
				catch (Menu_exception $e)
				{
					$errors[] = $e->getMessage();
				}
			}
		}

		if (empty($lines))
		{
			$lines[] = array('ingredient_id' => '', 'qty' => '', 'unit' => '', 'notes' => '');
		}
		$info = $this->menu_service->variant_info(array($variant['id']));
		$this->render('menu/items/recipe', array(
			'title'       => 'Resep: ' . $variant['menu_name'] . ($variant['name'] !== 'Reguler' ? ' - ' . $variant['name'] : ''),
			'variant'     => $variant,
			'lines'       => $lines,
			'ingredients' => $ingredients,
			'siblings'    => $siblings,
			'price'       => $info[$variant['id']]['price'],
			'cogs_manual' => $info[$variant['id']]['cogs_manual'],
			'copied'      => $copied,
			'errors'      => $errors,
		));
	}

	/**
	 * HPP manual per porsi untuk satu varian. Kosong / mode "resep" = kembali ke HPP dari resep.
	 * POST: mode (resep|manual), cogs_manual, note
	 */
	public function cogs($variant_id = NULL)
	{
		$this->require_permission('menu.edit');
		$this->require_permission('menu.view_cogs');
		if ($this->input->method() !== 'post')
		{
			show_error('Method not allowed', 405);
		}
		$variant = $this->Menu_model->find_variant($variant_id);
		if ( ! $variant)
		{
			show_404();
		}
		$back = 'menu/items/show/' . $variant['menu_id'] . '#v' . $variant['id'];
		$manual = $this->input->post('mode') === 'manual';
		$value = NULL;
		if ($manual)
		{
			$value = num_in($this->input->post('cogs_manual'), NAN);
			if (is_nan($value) OR $value < 0)
			{
				flash('danger', 'HPP manual harus diisi angka 0 atau lebih.');
				redirect($back);
			}
		}
		try
		{
			$res = $this->menu_service->run(function ($svc) use ($variant, $value) {
				$r = $svc->set_manual_cogs($variant['id'], $value, (string) $this->input->post('note'));
				$svc->snapshot_cogs(array($variant['id']));
				return $r;
			});
			$label = $variant['menu_name'] . ($variant['name'] !== 'Reguler' ? ' - ' . $variant['name'] : '');
			$this->audit->log('menu_cogs_manual', array('table_name' => 'menu_variants', 'record_id' => $variant['id'],
				'detail' => array('menu' => $label, 'old' => $res['old'], 'new' => $res['new'], 'note' => (string) $this->input->post('note'))));
			flash('success', $value === NULL ? "HPP $label kembali dihitung dari resep." : "HPP manual $label disimpan: " . rupiah($value) . ' per porsi.');
		}
		catch (Menu_exception $e)
		{
			flash('danger', $e->getMessage());
		}
		redirect($back);
	}

	/**
	 * Tambah satuan alternatif bahan langsung dari halaman resep (mis. jeruk: 1 buah = 0,15 kg).
	 * POST ingredient_id, unit, factor (jumlah satuan standar per 1 satuan baru). Balas JSON daftar satuan.
	 */
	public function add_unit()
	{
		$this->require_permission('menu.edit');
		if ($this->input->method() !== 'post')
		{
			show_error('Method not allowed', 405);
		}
		$ing = $this->db->select('id, name, unit')->where('id', (int) $this->input->post('ingredient_id'))->get('ingredients')->row_array();
		$unit = trim((string) $this->input->post('unit'));
		$factor = num_in(str_replace(',', '.', (string) $this->input->post('factor')), NAN); // terima 0,15 maupun 0.15
		$error = NULL;
		if ( ! $ing)
		{
			$error = 'Bahan tidak ditemukan.';
		}
		elseif ($unit === '' OR mb_strlen($unit) > 20)
		{
			$error = 'Nama satuan wajib diisi (maks. 20 karakter).';
		}
		elseif (mb_strtolower($unit) === mb_strtolower($ing['unit']))
		{
			$error = "\"$unit\" sudah menjadi satuan standar {$ing['name']}.";
		}
		elseif (is_nan($factor) OR $factor <= 0)
		{
			$error = 'Isi konversi: 1 ' . $unit . ' = berapa ' . $ing['unit'] . ' (harus lebih dari 0).';
		}
		elseif ($this->db->where('ingredient_id', $ing['id'])->where('unit', $unit)->count_all_results('ingredient_units'))
		{
			$error = "Satuan \"$unit\" sudah ada untuk {$ing['name']}.";
		}
		if ($error !== NULL)
		{
			$this->output->set_status_header(422);
			return $this->_json(array('ok' => FALSE, 'message' => $error));
		}
		$this->db->insert('ingredient_units', array('ingredient_id' => $ing['id'], 'unit' => $unit, 'factor' => round($factor, 6)));
		$this->audit->log('ingredient_unit_add', array('table_name' => 'ingredient_units', 'record_id' => $ing['id'],
			'detail' => array('ingredient' => $ing['name'], 'unit' => $unit, 'factor' => $factor, 'std_unit' => $ing['unit'], 'from' => 'resep')));
		$this->load->model('Ingredient_model');
		$all = $this->Ingredient_model->for_stock_form();
		return $this->_json(array('ok' => TRUE, 'unit' => $unit, 'units' => isset($all[$ing['id']]) ? $all[$ing['id']]['units'] : array(),
			'message' => "Satuan $unit ditambahkan: 1 $unit = " . rtrim(rtrim(number_format($factor, 6, ',', '.'), '0'), ',') . ' ' . $ing['unit'] . '.'));
	}

	/** Ubah harga (langsung atau terjadwal) dengan alasan (PRD 2.3.3). */
	public function price($variant_id = NULL)
	{
		$this->require_permission('menu.edit_price');
		$variant = $this->Menu_model->find_variant($variant_id);
		if ( ! $variant)
		{
			show_404();
		}
		$info = $this->menu_service->variant_info(array($variant['id']));
		$cur = $info[$variant['id']];
		$errors = array();
		$input = array('price' => $cur['price'], 'member_price' => $cur['member_price'], 'bulk_min_qty' => $cur['bulk_min_qty'],
			'bulk_price' => $cur['bulk_price'], 'when' => 'now', 'effective_date' => date('Y-m-d', strtotime('+1 day')), 'effective_time' => '00:00', 'reason' => '');

		if ($this->input->method() === 'post')
		{
			foreach (array_keys($input) as $k)
			{
				$input[$k] = trim((string) $this->input->post($k));
			}
			$price = num_in($input['price'], NAN);
			$member = num_in($input['member_price'], NULL);
			$bulk_q = num_in($input['bulk_min_qty'], NULL);
			$bulk_p = num_in($input['bulk_price'], NULL);
			if (is_nan($price) OR $price < 0)
			{
				$errors[] = 'Harga normal wajib diisi (angka ≥ 0).';
			}
			foreach (array('Harga member' => $member, 'Harga grosir' => $bulk_p) as $label => $v)
			{
				if ($v !== NULL && (is_nan($v) OR $v < 0))
				{
					$errors[] = "$label harus angka ≥ 0.";
				}
			}
			if ($bulk_q !== NULL && (is_nan($bulk_q) OR $bulk_q < 2 OR floor($bulk_q) != $bulk_q))
			{
				$errors[] = 'Minimal qty grosir harus bilangan bulat ≥ 2.';
			}
			if ($input['reason'] === '')
			{
				$errors[] = 'Alasan perubahan harga wajib diisi (mis. kenaikan bahan, promo, penyesuaian kompetitor).';
			}
			if ($input['when'] === 'schedule')
			{
				$effective = $input['effective_date'] . ' ' . $input['effective_time'] . ':00';
				$d = DateTime::createFromFormat('Y-m-d H:i:s', $effective);
				if ( ! $d OR $d->format('Y-m-d H:i:s') !== $effective OR $effective <= date('Y-m-d H:i:s'))
				{
					$errors[] = 'Waktu berlaku terjadwal harus di masa depan.';
				}
			}
			else
			{
				$effective = date('Y-m-d H:i:s');
			}

			if (empty($errors))
			{
				try
				{
					$data = array('price' => $price, 'member_price' => $member, 'bulk_min_qty' => $bulk_q, 'bulk_price' => $bulk_p);
					$this->menu_service->run(function ($svc) use ($variant, $data, $effective, $input) {
						$svc->set_price($variant['id'], $data, $effective, $input['reason']);
						$svc->snapshot_cogs(array($variant['id']));
					});
					$this->audit->log('menu_price_change', array('table_name' => 'menu_variant_prices', 'record_id' => $variant['id'],
						'detail' => array('menu' => $variant['menu_name'], 'variant' => $variant['name'], 'from' => $cur['price'], 'to' => $price,
							'effective' => $effective, 'reason' => $input['reason'])));
					flash('success', $input['when'] === 'schedule'
						? 'Perubahan harga dijadwalkan mulai ' . tgl($effective) . '.'
						: 'Harga diubah menjadi ' . rupiah($price) . '.');
					redirect('menu/items/show/' . $variant['menu_id'] . '#v' . $variant['id']);
				}
				catch (Menu_exception $e)
				{
					$errors[] = $e->getMessage();
				}
			}
		}

		$this->render('menu/items/price', array(
			'title'   => 'Harga: ' . $variant['menu_name'] . ($variant['name'] !== 'Reguler' ? ' - ' . $variant['name'] : ''),
			'variant' => $variant,
			'cur'     => $cur,
			'input'   => $input,
			'history' => $this->Menu_model->price_history($variant['id']),
			'errors'  => $errors,
		));
	}

	public function cancel_price($price_id = NULL)
	{
		$this->require_permission('menu.edit_price');
		$this->_require_post();
		try
		{
			$row = $this->menu_service->cancel_scheduled_price($price_id);
			$v = $this->Menu_model->find_variant($row['variant_id']);
			$this->audit->log('menu_price_cancel', array('table_name' => 'menu_variant_prices', 'record_id' => $row['variant_id'],
				'detail' => array('price' => $row['price'], 'effective' => $row['effective_from'])));
			flash('success', 'Perubahan harga terjadwal dibatalkan.');
			redirect('menu/items/price/' . $v['id']);
		}
		catch (Menu_exception $e)
		{
			flash('danger', $e->getMessage());
			redirect('menu/items');
		}
	}

	protected function _form($m)
	{
		$is_edit = (bool) $m;
		$errors = array();
		$can_price = can('menu.edit_price');
		$input = $is_edit ? $m : array('code' => '', 'name' => '', 'description' => '', 'category_id' => '', 'status' => 'active',
			'prep_minutes' => '', 'allergens' => '', 'spicy_level' => '', 'sort_order' => 0, 'image_path' => NULL);
		$variants = $is_edit
			? array_map(function ($v) { return array('id' => $v['id'], 'name' => $v['name'], 'barcode' => (string) $v['barcode'], 'is_active' => (int) $v['is_active'], 'price' => ''); }, $m['variants'])
			: array(array('id' => 0, 'name' => 'Reguler', 'barcode' => '', 'is_active' => 1, 'price' => ''));

		if ($this->input->method() === 'post')
		{
			foreach (array('code', 'name', 'description', 'allergens') as $k)
			{
				$input[$k] = trim((string) $this->input->post($k));
			}
			$input['code'] = strtoupper($input['code']);
			$input['category_id'] = (int) $this->input->post('category_id');
			$input['status'] = (string) $this->input->post('status');
			$input['prep_minutes'] = trim((string) $this->input->post('prep_minutes'));
			$input['spicy_level'] = trim((string) $this->input->post('spicy_level'));
			$input['sort_order'] = (int) $this->input->post('sort_order');

			if ($input['name'] === '' OR mb_strlen($input['name']) > 150)
			{
				$errors[] = 'Nama menu wajib diisi (maks. 150 karakter).';
			}
			if ($input['code'] !== '' && ! preg_match('/^[A-Z0-9._-]{2,30}$/', $input['code']))
			{
				$errors[] = 'Kode hanya huruf, angka, titik, strip (2-30). Kosongkan untuk otomatis.';
			}
			elseif ($input['code'] !== '' && $this->Menu_model->code_exists($input['code'], $is_edit ? $m['id'] : NULL))
			{
				$errors[] = 'Kode menu sudah dipakai.';
			}
			if ( ! array_key_exists($input['status'], Menu_model::$statuses))
			{
				$errors[] = 'Status tidak valid.';
			}
			if ($input['prep_minutes'] !== '' && ( ! ctype_digit($input['prep_minutes']) OR (int) $input['prep_minutes'] > 600))
			{
				$errors[] = 'Waktu persiapan 0-600 menit.';
			}
			if ($input['spicy_level'] !== '' && ( ! ctype_digit($input['spicy_level']) OR (int) $input['spicy_level'] > 5))
			{
				$errors[] = 'Level pedas 0-5.';
			}
			if ($input['category_id'] && ! $this->Menu_category_model->find($input['category_id']))
			{
				$errors[] = 'Kategori tidak ditemukan.';
			}

			// Varian
			$variants = array();
			$names = array();
			$existing_ids = $is_edit ? array_map('intval', array_column($m['variants'], 'id')) : array();
			foreach ((array) $this->input->post('variants') as $v)
			{
				$row = array(
					'id'        => (int) (isset($v['id']) ? $v['id'] : 0),
					'name'      => trim((string) (isset($v['name']) ? $v['name'] : '')),
					'barcode'   => trim((string) (isset($v['barcode']) ? $v['barcode'] : '')),
					'is_active' => ! empty($v['is_active']) ? 1 : 0,
					'price'     => trim((string) (isset($v['price']) ? $v['price'] : '')),
				);
				// Baris kosong diabaikan; varian lama yang namanya dikosongkan = dihapus.
				if ($row['name'] === '' && ($row['id'] OR $row['price'] === ''))
				{
					continue;
				}
				if ($row['id'] && ! in_array($row['id'], $existing_ids, TRUE))
				{
					$row['id'] = 0;
				}
				$variants[] = $row;
				$label = $row['name'] !== '' ? $row['name'] : 'varian #' . count($variants);
				if ($row['name'] === '' OR mb_strlen($row['name']) > 100)
				{
					$errors[] = "Nama $label wajib diisi (maks. 100).";
				}
				elseif (in_array(mb_strtolower($row['name']), $names, TRUE))
				{
					$errors[] = "Nama varian \"{$row['name']}\" dobel.";
				}
				$names[] = mb_strtolower($row['name']);
				if ($row['barcode'] !== '')
				{
					$this->load->library('barcode');
					if ( ! $this->barcode->is_valid($row['barcode']))
					{
						$errors[] = "Barcode $label tidak valid (4-40 karakter ASCII).";
					}
					elseif ($owner = $this->Menu_model->barcode_owner($row['barcode'], $row['id']))
					{
						$errors[] = "Barcode {$row['barcode']} sudah dipakai {$owner['menu_name']} - {$owner['name']}.";
					}
				}
				if ( ! $row['id'])
				{
					$p = num_in($row['price'], NAN);
					if ( ! $can_price)
					{
						$row['price'] = 0;
						$errors[] = "Varian baru $label butuh harga, tapi Anda tidak punya izin mengubah harga (menu.edit_price).";
					}
					elseif (is_nan($p) OR $p < 0)
					{
						$errors[] = "Harga $label wajib diisi.";
					}
					else
					{
						$row['price'] = $p;
					}
				}
				$variants[count($variants) - 1] = $row;
			}
			if (empty($variants))
			{
				$errors[] = 'Isi minimal satu varian (untuk menu tanpa varian, pakai "Reguler").';
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
				try
				{
					$id = $this->menu_service->run(function ($svc) use ($data, $variants, $m) {
						$id = $svc->save_menu($data, $variants, $m ? $m['id'] : NULL);
						$svc->snapshot_cogs(array_column($this->db->select('id')->where('menu_id', $id)->get('menu_variants')->result_array(), 'id'));
						return $id;
					});
					if ($is_edit && array_key_exists('image_path', $data))
					{
						$this->_delete_image($m['image_path']);
					}
					$saved = $this->Menu_model->find($id);
					$this->audit->log($is_edit ? 'menu_update' : 'menu_create', array('table_name' => 'menus', 'record_id' => $id,
						'detail' => array('code' => $saved['code'], 'name' => $saved['name'], 'variants' => array_column($saved['variants'], 'name'))));
					flash('success', "Menu {$saved['name']} disimpan." . ($is_edit ? '' : ' Lanjutkan dengan mengisi resep untuk menghitung COGS.'));
					redirect('menu/items/show/' . $id);
				}
				catch (Menu_exception $e)
				{
					$this->_delete_image($new_image);
					$errors[] = $e->getMessage();
				}
			}
		}

		$this->render('menu/items/form', array(
			'title'      => $is_edit ? 'Edit Menu' : 'Tambah Menu',
			'm'          => $m,
			'input'      => $input,
			'variants'   => $variants,
			'categories' => $this->Menu_category_model->options(TRUE),
			'can_price'  => $can_price,
			'errors'     => $errors,
		));
	}

	protected function _upload_image(array &$errors)
	{
		$file = $_FILES['image'];
		if ($file['error'] !== UPLOAD_ERR_OK OR $file['size'] > 2097152)
		{
			$errors[] = 'Foto gagal diupload atau lebih dari 2 MB.';
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

	protected function _menu($id)
	{
		$m = $this->Menu_model->find($id);
		if ( ! $m)
		{
			show_404();
		}
		return $m;
	}

	protected function _variant_ids(array $menus)
	{
		$ids = array();
		foreach ($menus as $m)
		{
			$ids = array_merge($ids, array_column($m['variants'], 'id'));
		}
		return $ids;
	}

	protected function _require_post()
	{
		if ($this->input->method() !== 'post')
		{
			show_error('Method not allowed', 405);
		}
	}
}
