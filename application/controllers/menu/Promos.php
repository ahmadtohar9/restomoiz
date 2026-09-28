<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Promo (PRD 2.3.4) + simulator untuk menguji promo sebelum dipakai di POS.
 */
class Promos extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->require_permission('menu.view');
		$this->load->model(array('Menu_model', 'Menu_category_model'));
		$this->load->library('promo_engine');
	}

	public function index()
	{
		$now = date('Y-m-d H:i:s');
		$rows = $this->db->select('p.*, u.name AS created_by_name')->from('promos p')->join('users u', 'u.id = p.created_by', 'left')
			->order_by('p.is_active', 'DESC')->order_by('p.start_at', 'DESC')->get()->result_array();
		foreach ($rows as &$r)
		{
			if ( ! $r['is_active'])
			{
				$r['state'] = array('Nonaktif', 'secondary');
			}
			elseif ($r['start_at'] > $now)
			{
				$r['state'] = array('Terjadwal', 'info');
			}
			elseif ($r['end_at'] !== NULL && $r['end_at'] < $now)
			{
				$r['state'] = array('Berakhir', 'dark');
			}
			elseif ($r['max_usage_total'] !== NULL && $r['usage_count'] >= $r['max_usage_total'])
			{
				$r['state'] = array('Kuota habis', 'warning');
			}
			else
			{
				$r['state'] = array('Berjalan', 'success');
			}
		}
		unset($r);
		$this->render('menu/promos/index', array('title' => 'Promo', 'rows' => $rows));
	}

	public function create()
	{
		$this->require_permission('menu.promo');
		$this->_form(NULL);
	}

	public function edit($id = NULL)
	{
		$this->require_permission('menu.promo');
		$this->_form($this->_promo($id));
	}

	public function toggle($id = NULL)
	{
		$this->require_permission('menu.promo');
		$this->_require_post();
		$p = $this->_promo($id);
		$this->db->where('id', $p['id'])->update('promos', array('is_active' => $p['is_active'] ? 0 : 1));
		$this->audit->log('promo_toggle', array('table_name' => 'promos', 'record_id' => $p['id'], 'detail' => array('name' => $p['name'], 'active' => ! $p['is_active'])));
		flash('success', "Promo {$p['name']} " . ($p['is_active'] ? 'dinonaktifkan.' : 'diaktifkan.'));
		redirect('menu/promos');
	}

	public function delete($id = NULL)
	{
		$this->require_permission('menu.promo');
		$this->_require_post();
		$p = $this->_promo($id);
		if ((int) $p['usage_count'] > 0)
		{
			flash('warning', "Promo {$p['name']} sudah pernah dipakai sehingga tidak bisa dihapus. Nonaktifkan saja.");
			redirect('menu/promos');
		}
		$this->db->where('id', $p['id'])->delete('promos');
		$this->audit->log('promo_delete', array('table_name' => 'promos', 'record_id' => $p['id'], 'detail' => array('name' => $p['name'])));
		flash('success', "Promo {$p['name']} dihapus.");
		redirect('menu/promos');
	}

	/** Simulasikan keranjang + konteks untuk melihat promo mana yang berlaku. */
	public function simulator()
	{
		$variants = $this->Menu_model->variant_options(TRUE);
		$qtys = array();
		foreach ((array) $this->input->get('qty') as $vid => $q)
		{
			if (isset($variants[(int) $vid]) && (int) $q > 0)
			{
				$qtys[(int) $vid] = min(999, (int) $q);
			}
		}
		$date = (string) $this->input->get('date');
		$time = (string) $this->input->get('time');
		$at = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d')) . ' ' . (preg_match('/^\d{2}:\d{2}$/', $time) ? $time : date('H:i')) . ':00';
		$ctx = array(
			'at'             => $at,
			'payment_method' => array_key_exists($this->input->get('payment'), Promo_engine::$payment_methods) ? $this->input->get('payment') : NULL,
			'is_member'      => (bool) $this->input->get('member'),
			'codes'          => array_filter(array_map('trim', explode(',', (string) $this->input->get('codes')))),
		);
		$result = NULL;
		if ($qtys)
		{
			$lines = $this->Menu_model->cart_lines($qtys, $at, $ctx['is_member']);
			$result = $this->promo_engine->evaluate($lines, $ctx);
		}
		$this->render('menu/promos/simulator', array(
			'title'    => 'Simulator Promo',
			'variants' => $variants,
			'qtys'     => $qtys,
			'ctx'      => $ctx,
			'result'   => $result,
		));
	}

	protected function _form($promo)
	{
		$is_edit = (bool) $promo;
		$errors = array();
		$items = $is_edit ? $this->db->where('promo_id', $promo['id'])->get('promo_items')->result_array() : array();
		$input = $is_edit ? $promo : array(
			'name' => '', 'description' => '', 'promo_code' => '', 'type' => 'percent', 'value' => '', 'buy_qty' => 2, 'get_qty' => 1,
			'bundle_price' => '', 'scope' => 'all', 'min_purchase' => 0, 'min_qty' => 0, 'payment_methods' => '', 'member_only' => 0,
			'max_discount' => '', 'stackable' => 0, 'max_usage_total' => '', 'max_usage_per_customer' => '', 'start_at' => date('Y-m-d 00:00:00'),
			'end_at' => '', 'days_of_week' => '', 'time_start' => '', 'time_end' => '', 'is_active' => 1,
		);
		$sel = array('category' => array(), 'menu' => array(), 'bundle' => array());
		foreach ($items as $it)
		{
			if ($it['item_type'] === 'variant')
			{
				$sel['bundle'][] = array('variant_id' => (int) $it['item_id'], 'qty' => (int) $it['qty']);
			}
			else
			{
				$sel[$it['item_type']][] = (int) $it['item_id'];
			}
		}

		if ($this->input->method() === 'post')
		{
			$post = function ($k) { return trim((string) $this->input->post($k)); };
			$input = array(
				'name'         => $post('name'),
				'description'  => $post('description'),
				'promo_code'   => strtoupper($post('promo_code')),
				'type'         => $post('type'),
				'value'        => $post('value'),
				'buy_qty'      => $post('buy_qty'),
				'get_qty'      => $post('get_qty'),
				'bundle_price' => $post('bundle_price'),
				'scope'        => $post('scope'),
				'min_purchase' => $post('min_purchase'),
				'min_qty'      => $post('min_qty'),
				'payment_methods' => implode(',', array_intersect((array) $this->input->post('payment_methods'), array_keys(Promo_engine::$payment_methods))),
				'member_only'  => $this->input->post('member_only') ? 1 : 0,
				'max_discount' => $post('max_discount'),
				'stackable'    => $this->input->post('stackable') ? 1 : 0,
				'max_usage_total' => $post('max_usage_total'),
				'max_usage_per_customer' => $post('max_usage_per_customer'),
				'start_at'     => str_replace('T', ' ', $post('start_at')),
				'end_at'       => str_replace('T', ' ', $post('end_at')),
				'days_of_week' => implode(',', array_filter(array_map('intval', (array) $this->input->post('days_of_week')), function ($d) { return $d >= 1 && $d <= 7; })),
				'time_start'   => $post('time_start'),
				'time_end'     => $post('time_end'),
				'is_active'    => $this->input->post('is_active') ? 1 : 0,
			);
			$sel = array(
				'category' => array_map('intval', (array) $this->input->post('categories')),
				'menu'     => array_map('intval', (array) $this->input->post('menus')),
				'bundle'   => array(),
			);
			foreach ((array) $this->input->post('bundle') as $b)
			{
				$vid = (int) (isset($b['variant_id']) ? $b['variant_id'] : 0);
				$q = (int) (isset($b['qty']) ? $b['qty'] : 0);
				if ($vid && $q > 0)
				{
					$sel['bundle'][] = array('variant_id' => $vid, 'qty' => $q);
				}
			}

			$row = $this->_validate($input, $sel, $errors, $is_edit ? $promo['id'] : NULL);
			if (empty($errors))
			{
				$this->db->trans_start();
				if ($is_edit)
				{
					$this->db->where('id', $promo['id'])->update('promos', $row);
					$id = (int) $promo['id'];
					$this->db->where('promo_id', $id)->delete('promo_items');
				}
				else
				{
					$row['created_by'] = $this->current_user['id'];
					$this->db->insert('promos', $row);
					$id = (int) $this->db->insert_id();
				}
				if ($row['type'] === 'bundle')
				{
					foreach ($sel['bundle'] as $b)
					{
						$this->db->insert('promo_items', array('promo_id' => $id, 'item_type' => 'variant', 'item_id' => $b['variant_id'], 'qty' => $b['qty']));
					}
				}
				elseif ($row['scope'] !== 'all')
				{
					foreach ($sel[$row['scope']] as $item_id)
					{
						$this->db->insert('promo_items', array('promo_id' => $id, 'item_type' => $row['scope'], 'item_id' => $item_id, 'qty' => 1));
					}
				}
				$this->db->trans_complete();
				$this->audit->log($is_edit ? 'promo_update' : 'promo_create', array('table_name' => 'promos', 'record_id' => $id,
					'detail' => array('name' => $row['name'], 'type' => $row['type'], 'value' => $row['value'])));
				flash('success', "Promo {$row['name']} disimpan. Coba di Simulator untuk memastikan hasilnya sesuai.");
				redirect('menu/promos');
			}
		}

		$menus = array();
		foreach ($this->db->select('id, name')->where('status !=', 'inactive')->order_by('name')->get('menus')->result_array() as $mm)
		{
			$menus[$mm['id']] = $mm['name'];
		}
		$this->render('menu/promos/form', array(
			'title'      => $is_edit ? 'Edit Promo' : 'Buat Promo',
			'promo'      => $promo,
			'input'      => $input,
			'sel'        => $sel,
			'categories' => $this->Menu_category_model->options(FALSE),
			'menus'      => $menus,
			'variants'   => $this->Menu_model->variant_options(TRUE),
			'errors'     => $errors,
		));
	}

	/** Validasi & ubah input form menjadi baris tabel promos. */
	protected function _validate(array $in, array $sel, array &$errors, $except_id)
	{
		$num = function ($v, $label, $int = FALSE, $nullable = TRUE) use (&$errors) {
			if ($v === '' OR $v === NULL)
			{
				return $nullable ? NULL : 0;
			}
			if ( ! is_numeric($v) OR $v < 0 OR ($int && floor($v) != $v))
			{
				$errors[] = "$label harus " . ($int ? 'bilangan bulat' : 'angka') . ' ≥ 0.';
				return NULL;
			}
			return $int ? (int) $v : round((float) $v, 2);
		};
		$dt = function ($v, $label, $required) use (&$errors) {
			if ($v === '')
			{
				if ($required)
				{
					$errors[] = "$label wajib diisi.";
				}
				return NULL;
			}
			$v = strlen($v) === 16 ? $v . ':00' : $v;
			$d = DateTime::createFromFormat('Y-m-d H:i:s', $v);
			if ( ! $d OR $d->format('Y-m-d H:i:s') !== $v)
			{
				$errors[] = "$label tidak valid.";
				return NULL;
			}
			return $v;
		};

		if ($in['name'] === '' OR mb_strlen($in['name']) > 150)
		{
			$errors[] = 'Nama promo wajib diisi (maks. 150 karakter).';
		}
		if ( ! isset(Promo_engine::$types[$in['type']]))
		{
			$errors[] = 'Jenis promo tidak valid.';
		}
		// Paket bundling tidak memakai pilihan cakupan (isi paket = targetnya).
		if ($in['type'] !== 'bundle' && ! in_array($in['scope'], array('all', 'category', 'menu'), TRUE))
		{
			$errors[] = 'Cakupan promo tidak valid.';
		}
		if ($in['promo_code'] !== '')
		{
			if ( ! preg_match('/^[A-Z0-9_-]{3,30}$/', $in['promo_code']))
			{
				$errors[] = 'Kode promo 3-30 karakter: huruf, angka, strip.';
			}
			else
			{
				$this->db->where('promo_code', $in['promo_code']);
				if ($except_id)
				{
					$this->db->where('id !=', (int) $except_id);
				}
				if ($this->db->count_all_results('promos'))
				{
					$errors[] = 'Kode promo sudah dipakai promo lain.';
				}
			}
		}

		$row = array(
			'name' => $in['name'], 'description' => $in['description'] ?: NULL, 'promo_code' => $in['promo_code'] ?: NULL,
			'type' => $in['type'], 'scope' => $in['type'] === 'bundle' ? 'menu' : $in['scope'],
			'value' => 0, 'buy_qty' => NULL, 'get_qty' => NULL, 'bundle_price' => NULL,
			'min_purchase' => $num($in['min_purchase'], 'Minimal belanja', FALSE, FALSE),
			'min_qty' => $num($in['min_qty'], 'Minimal qty', TRUE, FALSE),
			'payment_methods' => $in['payment_methods'] ?: NULL,
			'member_only' => $in['member_only'], 'stackable' => $in['stackable'], 'is_active' => $in['is_active'],
			'max_discount' => $num($in['max_discount'], 'Maks. diskon'),
			'max_usage_total' => $num($in['max_usage_total'], 'Kuota total', TRUE),
			'max_usage_per_customer' => $num($in['max_usage_per_customer'], 'Kuota per pelanggan', TRUE),
			'start_at' => $dt($in['start_at'], 'Mulai berlaku', TRUE),
			'end_at' => $dt($in['end_at'], 'Berakhir', FALSE),
			'days_of_week' => $in['days_of_week'] ?: NULL,
			'time_start' => NULL, 'time_end' => NULL,
		);

		switch ($in['type'])
		{
			case 'fixed':
			case 'percent':
				$row['value'] = $num($in['value'], 'Nilai potongan', FALSE, FALSE);
				if ($row['value'] <= 0)
				{
					$errors[] = 'Nilai potongan harus lebih dari 0.';
				}
				if ($in['type'] === 'percent' && $row['value'] > 100)
				{
					$errors[] = 'Persen potongan maksimal 100.';
				}
				break;
			case 'buy_get':
				$row['buy_qty'] = $num($in['buy_qty'], 'Jumlah beli', TRUE, FALSE);
				$row['get_qty'] = $num($in['get_qty'], 'Jumlah gratis', TRUE, FALSE);
				if ($row['buy_qty'] < 1 OR $row['get_qty'] < 1)
				{
					$errors[] = 'Jumlah beli dan gratis minimal 1.';
				}
				break;
			case 'bundle':
				$row['bundle_price'] = $num($in['bundle_price'], 'Harga paket', FALSE, FALSE);
				if (count($sel['bundle']) < 2)
				{
					$errors[] = 'Paket bundling minimal berisi 2 item.';
				}
				$vids = array_column($sel['bundle'], 'variant_id');
				if (count($vids) !== count(array_unique($vids)))
				{
					$errors[] = 'Item paket dobel, gabungkan qty-nya.';
				}
				break;
		}
		if ($in['type'] !== 'bundle' && $row['scope'] !== 'all' && empty($sel[$row['scope']]))
		{
			$errors[] = 'Pilih minimal satu ' . ($row['scope'] === 'category' ? 'kategori' : 'menu') . ' untuk cakupan promo.';
		}
		if ($row['start_at'] && $row['end_at'] && $row['end_at'] <= $row['start_at'])
		{
			$errors[] = 'Waktu berakhir harus setelah waktu mulai.';
		}
		if (($in['time_start'] === '') !== ($in['time_end'] === ''))
		{
			$errors[] = 'Isi jam mulai dan jam selesai sekaligus (atau kosongkan keduanya).';
		}
		elseif ($in['time_start'] !== '')
		{
			if ( ! preg_match('/^\d{2}:\d{2}$/', $in['time_start']) OR ! preg_match('/^\d{2}:\d{2}$/', $in['time_end']) OR $in['time_start'] === $in['time_end'])
			{
				$errors[] = 'Jam berlaku tidak valid.';
			}
			else
			{
				$row['time_start'] = $in['time_start'] . ':00';
				$row['time_end'] = $in['time_end'] . ':00';
			}
		}
		return $row;
	}

	protected function _promo($id)
	{
		$p = $this->db->where('id', (int) $id)->get('promos')->row_array();
		if ( ! $p)
		{
			show_404();
		}
		return $p;
	}

	protected function _require_post()
	{
		if ($this->input->method() !== 'post')
		{
			show_error('Method not allowed', 405);
		}
	}
}
