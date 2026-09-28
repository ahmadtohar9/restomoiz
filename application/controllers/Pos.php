<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Layar POS (PRD 2.4.1 - 2.4.3).
 *
 * Halaman memuat katalog sebagai JSON lalu keranjang dikelola di browser.
 * Setiap perubahan keranjang meminta /pos/quote untuk total & promo, dan
 * /pos/submit menyimpan pesanan (+ bayar) dalam satu transaksi. Semua
 * harga dihitung ulang di server oleh Pos_service.
 *
 * Request POST dikirim sebagai form (field "payload" berisi JSON) supaya
 * proteksi CSRF CodeIgniter tetap berlaku.
 */
class Pos extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		if ( ! can_any(array('sales.process', 'sales.order')))
		{
			$this->require_permission('sales.process');
		}
		$this->load->library('pos_service', NULL, 'pos');
	}

	public function index()
	{
		$shift = $this->pos->current_shift($this->current_user['id']);
		$this->load->view('pos/index', array(
			'title'     => 'POS',
			'shift'     => $shift,
			'can_pay'   => can('sales.process'),
			'order_id'  => (int) $this->input->get('order'),
			'resto'     => setting('resto_name', 'Resto Moiz'),
		));
	}

	/** Katalog menu, meja, tambahan, dan pesanan terbuka untuk POS. */
	public function catalog()
	{
		$this->load->model(array('Menu_model', 'Menu_category_model'));
		$menus = $this->Menu_model->all(array());
		$ids = array();
		foreach ($menus as $m)
		{
			$ids = array_merge($ids, array_column($m['variants'], 'id'));
		}
		$info = $this->menu_service->variant_info($ids);

		$out_menus = array();
		foreach ($menus as $m)
		{
			if ($m['status'] === 'inactive')
			{
				continue;
			}
			$variants = array();
			foreach ($m['variants'] as $v)
			{
				if ( ! $v['is_active'] OR $info[$v['id']]['price'] === NULL)
				{
					continue;
				}
				$i = $info[$v['id']];
				$variants[] = array(
					'id' => (int) $v['id'], 'name' => $v['name'], 'barcode' => $v['barcode'],
					'price' => $i['price'], 'member_price' => $i['member_price'], 'bulk_min_qty' => $i['bulk_min_qty'], 'bulk_price' => $i['bulk_price'],
					'avail' => Menu_service::availability($m['status'], 1, $i['portions']), 'portions' => $i['portions'],
				);
			}
			if ($variants)
			{
				$out_menus[] = array('id' => (int) $m['id'], 'name' => $m['name'], 'code' => $m['code'], 'category_id' => (int) $m['category_id'],
					'image' => $m['image_path'] ? base_url($m['image_path']) : NULL, 'spicy' => $m['spicy_level'], 'variants' => $variants);
			}
		}

		$tables = $this->db->query(
			"SELECT t.id, t.name, t.area, t.capacity, o.id AS order_id, o.order_number
			 FROM dining_tables t LEFT JOIN orders o ON o.table_id = t.id AND o.status = 'open'
			 WHERE t.is_active = 1 ORDER BY t.sort_order, t.name"
		)->result_array();

		$this->_json(array(
			'categories' => array_values(array_map(function ($c) { return array('id' => (int) $c['id'], 'name' => $c['name'], 'parent_id' => (int) $c['parent_id'], 'depth' => $c['depth']); }, $this->Menu_category_model->tree(TRUE))),
			'menus'      => $out_menus,
			'modifiers'  => array_map(function ($m) { return array('id' => (int) $m['id'], 'name' => $m['name'], 'price' => (float) $m['price']); },
				$this->db->where('is_active', 1)->order_by('sort_order')->order_by('name')->get('modifiers')->result_array()),
			'tables'     => $tables,
			'open_orders'=> $this->_open_orders(),
			'settings'   => array('tax_enabled' => setting('tax_enabled', '1') === '1', 'tax_rate' => (float) setting('tax_rate', 11),
				'service_rate' => (float) setting('service_charge_rate', 0)),
			'payment_methods' => Promo_engine::$payment_methods,
			'card_types' => Pos_service::$card_types,
		));
	}

	/** Detail pesanan terbuka untuk dimuat ke POS. */
	public function order($id = NULL)
	{
		$o = $this->db->select('o.*, t.name AS table_name')->from('orders o')->join('dining_tables t', 't.id = o.table_id', 'left')
			->where('o.id', (int) $id)->get()->row_array();
		if ( ! $o)
		{
			$this->output->set_status_header(404);
			return $this->_json(array('message' => 'Pesanan tidak ditemukan.'));
		}
		$items = $this->db->select('id, name, qty, unit_price, line_total, notes, modifiers, kitchen_status')
			->where('order_id', $o['id'])->order_by('id')->get('order_items')->result_array();
		foreach ($items as &$it)
		{
			$it['modifiers'] = $it['modifiers'] ? json_decode($it['modifiers'], TRUE) : array();
		}
		$this->_json(array('order' => $o, 'items' => $items));
	}

	/** Hitung total & promo untuk keranjang (+ item pesanan terbuka). */
	public function quote()
	{
		$p = $this->_payload();
		try
		{
			$h = $this->_header($p);
			$items = array();
			$existing = 0;
			if ( ! empty($p['order_id']))
			{
				$order = $this->db->where('id', (int) $p['order_id'])->where('status', 'open')->get('orders')->row_array();
				if ( ! $order)
				{
					throw new Pos_exception('Pesanan tidak ditemukan atau sudah ditutup.');
				}
				$h['is_member'] = (bool) $order['is_member'];
				$h['customer_id'] = $order['customer_id'];
				$items = $this->pos->order_items_for_quote($order['id']);
				$existing = count($items);
			}
			$warnings = array();
			if ( ! empty($p['cart']))
			{
				$new = $this->pos->prepare_items($p['cart'], $h['is_member']);
				foreach ($new as $n)
				{
					if ($n['portions'] !== NULL && $n['portions'] < $n['qty'])
					{
						$warnings[] = $n['name'] . ': stok bahan cukup untuk ' . max(0, (int) $n['portions']) . ' porsi.';
					}
				}
				$items = array_merge($items, $new);
			}
			if ( ! $items)
			{
				return $this->_json(array('ok' => TRUE, 'empty' => TRUE));
			}
			$q = $this->pos->quote($items, array('payment_method' => isset($p['payment']['method']) ? $p['payment']['method'] : NULL,
				'is_member' => $h['is_member'], 'codes' => $this->_codes($p), 'customer_id' => $h['customer_id']));
			// Diskon per baris keranjang baru (indeks setelah item lama).
			$cart_disc = array();
			foreach ($q['line_discounts'] as $idx => $d)
			{
				if ($idx >= $existing)
				{
					$cart_disc[$idx - $existing] = $d;
				}
			}
			$q['cart_discounts'] = $cart_disc;
			unset($q['line_discounts']);
			$q['ok'] = TRUE;
			$q['warnings'] = $warnings;
			$this->_json($q);
		}
		catch (Pos_exception $e)
		{
			$this->_json(array('ok' => FALSE, 'message' => $e->getMessage()));
		}
	}

	/**
	 * Simpan pesanan: action = kitchen (kirim ke dapur, bayar nanti) | pay (simpan + bayar).
	 * payload: order_id?, header{...}, cart[], codes[], payment{...}
	 */
	public function submit()
	{
		if ($this->input->method() !== 'post')
		{
			show_error('Method not allowed', 405);
		}
		$p = $this->_payload();
		$action = isset($p['action']) ? $p['action'] : 'kitchen';
		if ($action === 'pay' && ! can('sales.process'))
		{
			$this->output->set_status_header(403);
			return $this->_json(array('ok' => FALSE, 'message' => 'Anda tidak punya akses menerima pembayaran.'));
		}

		try
		{
			$h = $this->_header($p);
			$payment = isset($p['payment']) ? (array) $p['payment'] : array();
			$payment = array_merge(array('method' => '', 'paid_amount' => 0, 'card_type' => '', 'card_last4' => '', 'approval_code' => '', 'payment_ref' => ''), $payment);
			$payment['codes'] = $this->_codes($p);
			$order_id = ! empty($p['order_id']) ? (int) $p['order_id'] : NULL;
			$cart = isset($p['cart']) ? (array) $p['cart'] : array();

			$result = $this->pos->run(function ($pos) use ($h, $cart, $order_id, $action, $payment) {
				$added = 0;
				if ($order_id)
				{
					$order = $this->db->where('id', $order_id)->get('orders')->row_array();
					if ($cart)
					{
						$items = $pos->prepare_items($cart, (bool) ($order ? $order['is_member'] : FALSE));
						$pos->add_items($order_id, $items);
						$added = count($items);
					}
				}
				else
				{
					$items = $pos->prepare_items($cart, $h['is_member']);
					$order_id = $pos->place_order($h, $items);
					$added = count($items);
				}
				$quote = $action === 'pay' ? $pos->pay($order_id, $payment) : NULL;
				return array($order_id, $added, $quote);
			});
			list($order_id, $added, $quote) = $result;
			$order = $this->db->where('id', $order_id)->get('orders')->row_array();

			$this->audit->log($action === 'pay' ? 'order_paid' : 'order_placed', array('table_name' => 'orders', 'record_id' => $order['order_number'],
				'detail' => array('items_added' => $added, 'total' => $order['total'], 'method' => $order['payment_method'])));

			$this->_json(array(
				'ok'           => TRUE,
				'order_id'     => (int) $order['id'],
				'order_number' => $order['order_number'],
				'status'       => $order['status'],
				'total'        => (float) $order['total'],
				'change'       => $order['change_amount'] !== NULL ? (float) $order['change_amount'] : NULL,
				'receipt_url'  => site_url('sales/orders/receipt/' . $order['id']),
				'ticket_url'   => site_url('sales/orders/ticket/' . $order['id'] . '?new=' . $added),
				'message'      => $action === 'pay' ? "Pembayaran {$order['order_number']} berhasil." : "Pesanan {$order['order_number']} dikirim ke dapur.",
			));
		}
		catch (Exception $e)
		{
			if ( ! ($e instanceof Pos_exception) && ! ($e instanceof Stock_exception))
			{
				throw $e;
			}
			$this->_json(array('ok' => FALSE, 'message' => $e->getMessage()));
		}
	}

	/** Cari pelanggan (nama / telepon) untuk POS. */
	public function customers()
	{
		$q = trim((string) $this->input->get('q'));
		if (mb_strlen($q) < 2)
		{
			return $this->_json(array());
		}
		$rows = $this->db->select('id, name, phone, address, is_member')->from('customers')
			->group_start()->like('name', $q)->or_like('phone', $q)->group_end()
			->order_by('name')->limit(10)->get()->result_array();
		$this->_json($rows);
	}

	protected function _open_orders()
	{
		return $this->db->query(
			"SELECT o.id, o.order_number, o.order_type, o.customer_name, o.subtotal, o.created_at, t.name AS table_name
			 FROM orders o LEFT JOIN dining_tables t ON t.id = o.table_id
			 WHERE o.status = 'open' ORDER BY o.id DESC LIMIT 50"
		)->result_array();
	}

	protected function _payload()
	{
		$raw = (string) $this->input->post('payload');
		$data = json_decode($raw, TRUE);
		return is_array($data) ? $data : array();
	}

	/** Header pesanan dari payload + pelanggan (buat/ambil otomatis dari telepon). */
	protected function _header(array $p)
	{
		$h = isset($p['header']) ? (array) $p['header'] : array();
		$h = array_merge(array('order_type' => 'takeaway', 'table_id' => NULL, 'guest_count' => NULL, 'customer_id' => NULL, 'customer_name' => '',
			'customer_phone' => '', 'delivery_address' => '', 'delivery_time' => NULL, 'is_member' => FALSE, 'notes' => ''), $h);
		foreach (array('customer_name', 'customer_phone', 'delivery_address', 'notes') as $k)
		{
			$h[$k] = trim((string) $h[$k]);
		}
		$h['is_member'] = FALSE;
		if ($h['customer_id'])
		{
			$c = $this->db->where('id', (int) $h['customer_id'])->get('customers')->row_array();
			if ($c)
			{
				$h['customer_name'] = $h['customer_name'] ?: $c['name'];
				$h['customer_phone'] = $h['customer_phone'] ?: (string) $c['phone'];
				$h['delivery_address'] = $h['delivery_address'] ?: (string) $c['address'];
				$h['is_member'] = (bool) $c['is_member'];
			}
			else
			{
				$h['customer_id'] = NULL;
			}
		}
		if ($h['delivery_time'])
		{
			$d = DateTime::createFromFormat('Y-m-d\TH:i', (string) $h['delivery_time']);
			$h['delivery_time'] = $d ? $d->format('Y-m-d H:i:s') : NULL;
		}
		return $h;
	}

	protected function _codes(array $p)
	{
		return isset($p['codes']) ? array_slice(array_filter(array_map('trim', (array) $p['codes'])), 0, 5) : array();
	}
}
