<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pos_exception extends RuntimeException {}

/**
 * Aturan penjualan & POS (PRD 2.4). Harga selalu dihitung ulang di server
 * dari master menu; angka dari browser hanya dipakai untuk tampilan.
 *
 * Urutan perhitungan total:
 *   subtotal (harga item + tambahan) - diskon promo = dasar
 *   service charge = dasar x service_charge_rate
 *   PPN            = (dasar + service) x tax_rate   (jika tax_enabled)
 *   total          = dasar + service + PPN          (dibulatkan ke rupiah)
 */
class Pos_service {

	public static $order_types = array('dine_in' => 'Dine-in', 'takeaway' => 'Takeaway', 'delivery' => 'Delivery');
	public static $kitchen_flow = array('pending' => 'Baru', 'preparing' => 'Dimasak', 'ready' => 'Siap', 'served' => 'Diantar', 'void' => 'Batal');
	public static $card_types = array('VISA', 'Mastercard', 'JCB', 'GPN', 'AMEX', 'Lainnya');

	protected $CI;
	protected $db;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->db = $this->CI->db;
		$this->CI->load->library(array('sequence', 'menu_service', 'promo_engine'));
		$this->CI->load->library('stock_service', NULL, 'stock');
	}

	public function run(callable $fn)
	{
		$this->db->trans_begin();
		try
		{
			$result = $fn($this);
			if ($this->db->trans_status() === FALSE)
			{
				throw new Pos_exception('Gagal menyimpan ke database.');
			}
			$this->db->trans_commit();
			return $result;
		}
		catch (Exception $e)
		{
			$this->db->trans_rollback();
			throw $e;
		}
	}

	// ------------------------------------------------------------------
	// Harga & perhitungan
	// ------------------------------------------------------------------

	/**
	 * Validasi keranjang dari POS dan hitung harga per item dari master.
	 * @param array $cart [[variant_id, qty, modifiers => [id...], notes]]
	 * @return array item siap simpan
	 */
	public function prepare_items(array $cart, $is_member)
	{
		$cart = array_values(array_filter($cart, function ($c) { return isset($c['variant_id']) && (int) $c['qty'] > 0; }));
		if ( ! $cart)
		{
			throw new Pos_exception('Keranjang masih kosong.');
		}
		$vids = array_unique(array_map(function ($c) { return (int) $c['variant_id']; }, $cart));
		$rows = $this->db->select('v.id, v.name, v.is_active, v.menu_id, m.name AS menu_name, m.status, m.category_id')
			->from('menu_variants v')->join('menus m', 'm.id = v.menu_id')->where_in('v.id', $vids)->get()->result_array();
		$variants = array_column($rows, NULL, 'id');
		$info = $this->CI->menu_service->variant_info($vids);

		$mod_rows = $this->db->where('is_active', 1)->get('modifiers')->result_array();
		$mods = array_column($mod_rows, NULL, 'id');

		// Total qty per varian untuk harga grosir.
		$qty_per_variant = array();
		foreach ($cart as $c)
		{
			$vid = (int) $c['variant_id'];
			$qty_per_variant[$vid] = (isset($qty_per_variant[$vid]) ? $qty_per_variant[$vid] : 0) + (int) $c['qty'];
		}

		$items = array();
		foreach ($cart as $c)
		{
			$vid = (int) $c['variant_id'];
			$qty = (int) $c['qty'];
			if ( ! isset($variants[$vid]))
			{
				throw new Pos_exception('Menu tidak ditemukan.');
			}
			$v = $variants[$vid];
			$label = $v['menu_name'] . ($v['name'] !== 'Reguler' ? ' - ' . $v['name'] : '');
			if ($qty > 999)
			{
				throw new Pos_exception("$label: jumlah terlalu besar.");
			}
			$i = $info[$vid];
			$avail = Menu_service::availability($v['status'], (int) $v['is_active'], $i['portions']);
			if ($avail === 'inactive')
			{
				throw new Pos_exception("$label sedang tidak dijual.");
			}
			if ($avail === 'manual_out')
			{
				throw new Pos_exception("$label ditandai habis.");
			}
			if ($i['price'] === NULL)
			{
				throw new Pos_exception("$label belum punya harga.");
			}
			$price = $i['price'];
			if ($is_member && $i['member_price'] !== NULL)
			{
				$price = min($price, $i['member_price']);
			}
			if ($i['bulk_min_qty'] && $qty_per_variant[$vid] >= $i['bulk_min_qty'])
			{
				$price = min($price, $i['bulk_price']);
			}

			$chosen = array();
			$mod_price = 0.0;
			foreach (array_unique(array_map('intval', isset($c['modifiers']) ? (array) $c['modifiers'] : array())) as $mid)
			{
				if ( ! isset($mods[$mid]))
				{
					throw new Pos_exception("$label: tambahan tidak tersedia.");
				}
				$chosen[] = array('id' => $mid, 'name' => $mods[$mid]['name'], 'price' => (float) $mods[$mid]['price'],
					'ingredient_id' => $mods[$mid]['ingredient_id'], 'qty_std' => $mods[$mid]['qty_std']);
				$mod_price += (float) $mods[$mid]['price'];
			}
			$unit = round($price + $mod_price, 2);
			$items[] = array(
				'variant_id'      => $vid,
				'menu_id'         => (int) $v['menu_id'],
				'category_id'     => $v['category_id'],
				'name'            => $label,
				'qty'             => $qty,
				'base_price'      => $price,
				'modifiers'       => $chosen,
				'modifiers_price' => $mod_price,
				'unit_price'      => $unit,
				'line_total'      => round($unit * $qty, 2),
				'notes'           => isset($c['notes']) ? mb_substr(trim((string) $c['notes']), 0, 255) : '',
				'portions'        => $i['portions'],
			);
		}
		return $items;
	}

	/**
	 * Hitung promo, service, pajak untuk item (baru atau item order tersimpan).
	 * @param array $items item dengan variant_id, menu_id, category_id, qty, unit_price, name
	 * @param array $ctx   payment_method, is_member, codes, customer_id, at
	 */
	public function quote(array $items, array $ctx)
	{
		$parents = array_column($this->db->select('id, parent_id')->get('menu_categories')->result_array(), 'parent_id', 'id');
		$lines = array();
		foreach ($items as $idx => $it)
		{
			$cats = array();
			$c = $it['category_id'];
			while ($c && ! in_array((int) $c, $cats, TRUE))
			{
				$cats[] = (int) $c;
				$c = isset($parents[$c]) ? $parents[$c] : NULL;
			}
			$lines[$idx] = array('variant_id' => (int) $it['variant_id'], 'menu_id' => (int) $it['menu_id'], 'category_ids' => $cats,
				'qty' => (int) $it['qty'], 'unit_price' => (float) $it['unit_price'], 'name' => $it['name']);
		}

		$usage = array();
		if ( ! empty($ctx['customer_id']))
		{
			$usage = array_column($this->db->query(
				"SELECT op.promo_id, COUNT(*) AS n FROM order_promos op JOIN orders o ON o.id = op.order_id
				 WHERE o.customer_id = ? AND o.status = 'paid' GROUP BY op.promo_id",
				array((int) $ctx['customer_id'])
			)->result_array(), 'n', 'promo_id');
		}

		$promo = $this->CI->promo_engine->evaluate($lines, array(
			'at'             => isset($ctx['at']) ? $ctx['at'] : date('Y-m-d H:i:s'),
			'payment_method' => isset($ctx['payment_method']) ? $ctx['payment_method'] : NULL,
			'is_member'      => ! empty($ctx['is_member']),
			'codes'          => isset($ctx['codes']) ? (array) $ctx['codes'] : array(),
			'customer_usage' => $usage,
		));

		$subtotal = $promo['subtotal'];
		$discount = $promo['total_discount'];
		$base = $subtotal - $discount;
		$service = round($base * (float) setting('service_charge_rate', 0) / 100);
		$tax = setting('tax_enabled', '1') === '1' ? round(($base + $service) * (float) setting('tax_rate', 11) / 100) : 0;

		return array(
			'subtotal'       => $subtotal,
			'discount'       => $discount,
			'service_charge' => $service,
			'tax'            => $tax,
			'total'          => round($base + $service + $tax),
			'line_discounts' => $promo['line_discounts'],
			'applied'        => array_map(function ($a) { return array('id' => (int) $a['promo']['id'], 'name' => $a['promo']['name'], 'discount' => $a['discount']); }, $promo['applied']),
			'considered'     => array_map(function ($c) { return array('name' => $c['promo']['name'], 'status' => $c['status'], 'reason' => $c['reason'], 'code' => $c['promo']['promo_code']); }, $promo['considered']),
		);
	}

	// ------------------------------------------------------------------
	// Pesanan
	// ------------------------------------------------------------------

	/**
	 * Buat pesanan baru (status open) dan kirim item ke dapur.
	 * @param array $h order_type, table_id, guest_count, customer_id, customer_name, customer_phone,
	 *                 delivery_address, delivery_time, is_member, notes
	 */
	public function place_order(array $h, array $items)
	{
		if ( ! isset(self::$order_types[$h['order_type']]))
		{
			throw new Pos_exception('Pilih tipe pesanan.');
		}
		if ($h['order_type'] === 'dine_in')
		{
			if (empty($h['table_id']))
			{
				throw new Pos_exception('Pesanan dine-in wajib memilih meja.');
			}
			$table = $this->db->where('id', (int) $h['table_id'])->where('is_active', 1)->get('dining_tables')->row_array();
			if ( ! $table)
			{
				throw new Pos_exception('Meja tidak ditemukan.');
			}
			$busy = $this->db->query("SELECT order_number FROM orders WHERE table_id = ? AND status = 'open' LIMIT 1 FOR UPDATE", array($table['id']))->row_array();
			if ($busy)
			{
				throw new Pos_exception("Meja {$table['name']} masih punya pesanan terbuka {$busy['order_number']}. Tambahkan item ke pesanan itu.");
			}
		}
		if ($h['order_type'] === 'delivery' && (trim((string) $h['customer_name']) === '' OR trim((string) $h['customer_phone']) === '' OR trim((string) $h['delivery_address']) === ''))
		{
			throw new Pos_exception('Pesanan delivery wajib mengisi nama, telepon, dan alamat pelanggan.');
		}

		$this->db->insert('orders', array(
			'order_number'     => $this->CI->sequence->next('ORD', date('ymd'), 4),
			'order_type'       => $h['order_type'],
			'table_id'         => $h['order_type'] === 'dine_in' ? (int) $h['table_id'] : NULL,
			'guest_count'      => ! empty($h['guest_count']) ? (int) $h['guest_count'] : NULL,
			'customer_id'      => ! empty($h['customer_id']) ? (int) $h['customer_id'] : NULL,
			'customer_name'    => $h['customer_name'] !== '' ? mb_substr($h['customer_name'], 0, 100) : NULL,
			'customer_phone'   => $h['customer_phone'] !== '' ? mb_substr($h['customer_phone'], 0, 30) : NULL,
			'delivery_address' => $h['order_type'] === 'delivery' ? mb_substr($h['delivery_address'], 0, 255) : NULL,
			'delivery_time'    => $h['order_type'] === 'delivery' && ! empty($h['delivery_time']) ? $h['delivery_time'] : NULL,
			'is_member'        => ! empty($h['is_member']) ? 1 : 0,
			'notes'            => $h['notes'] !== '' ? mb_substr($h['notes'], 0, 255) : NULL,
			'created_by'       => $this->_uid(),
		));
		$order_id = (int) $this->db->insert_id();
		$this->_insert_items($order_id, $items);
		return $order_id;
	}

	/** Tambah item ke pesanan terbuka (mis. tamu dine-in pesan lagi). */
	public function add_items($order_id, array $items)
	{
		$order = $this->_lock_order($order_id);
		if ($order['status'] !== 'open')
		{
			throw new Pos_exception('Pesanan ini sudah ' . ($order['status'] === 'paid' ? 'dibayar' : 'dibatalkan') . '.');
		}
		$this->_insert_items($order['id'], $items);
	}

	protected function _insert_items($order_id, array $items)
	{
		$order = $this->db->where('id', $order_id)->get('orders')->row_array();
		$allow_partial = setting('pos_block_insufficient_stock', '1') !== '1';
		$doc_no = $this->CI->stock->new_document_no();

		foreach ($items as $it)
		{
			$this->db->insert('order_items', array(
				'order_id'        => $order_id,
				'variant_id'      => $it['variant_id'],
				'menu_id'         => $it['menu_id'],
				'name'            => $it['name'],
				'qty'             => $it['qty'],
				'base_price'      => $it['base_price'],
				'modifiers'       => $it['modifiers'] ? json_encode(array_map(function ($m) { return array('id' => $m['id'], 'name' => $m['name'], 'price' => $m['price']); }, $it['modifiers']), JSON_UNESCAPED_UNICODE) : NULL,
				'modifiers_price' => $it['modifiers_price'],
				'unit_price'      => $it['unit_price'],
				'line_total'      => $it['line_total'],
				'notes'           => $it['notes'] !== '' ? $it['notes'] : NULL,
				'created_by'      => $this->_uid(),
			));
			$item_id = (int) $this->db->insert_id();

			// Kebutuhan bahan: resep varian + bahan tambahan, dikali qty.
			$need = array();
			foreach ($this->db->select('ingredient_id, qty_std')->where('variant_id', $it['variant_id'])->get('menu_recipes')->result_array() as $r)
			{
				$need[$r['ingredient_id']] = (isset($need[$r['ingredient_id']]) ? $need[$r['ingredient_id']] : 0) + (float) $r['qty_std'] * $it['qty'];
			}
			foreach ($it['modifiers'] as $m)
			{
				if ($m['ingredient_id'] && (float) $m['qty_std'] > 0)
				{
					$need[$m['ingredient_id']] = (isset($need[$m['ingredient_id']]) ? $need[$m['ingredient_id']] : 0) + (float) $m['qty_std'] * $it['qty'];
				}
			}

			$cogs = 0.0;
			$short = FALSE;
			foreach ($need as $ing_id => $q)
			{
				$q = round($q, 3);
				if ($q <= 0)
				{
					continue;
				}
				$o = array('reason' => 'sales', 'movement_no' => $doc_no, 'ref_type' => 'order_item', 'ref_id' => $item_id,
					'notes' => $order['order_number'] . ' ' . $it['name'], 'allow_partial' => $allow_partial);
				try
				{
					$mid = $this->CI->stock->issue($ing_id, $q, $o);
				}
				catch (Stock_exception $e)
				{
					throw new Pos_exception($it['name'] . ': ' . $e->getMessage() . ' Perbarui stok atau tandai menu habis.');
				}
				if ($mid)
				{
					$cogs += -(float) $this->db->select('total_cost')->where('id', $mid)->get('stock_movements')->row()->total_cost;
				}
				if ($o['shortage'] > 0)
				{
					// Kekurangan tetap dihitung ke COGS dengan harga beli terakhir supaya laba tidak terlihat lebih besar.
					$price = (float) $this->db->select('current_price')->where('id', $ing_id)->get('ingredients')->row()->current_price;
					$cogs += $o['shortage'] * $price;
					$short = TRUE;
				}
			}
			$this->db->where('id', $item_id)->update('order_items', array('cogs' => round($cogs, 2), 'stock_shortage' => $short ? 1 : 0));
		}
		$this->_refresh_open_totals($order_id);
	}

	/** Subtotal sementara untuk pesanan terbuka (total final dihitung saat bayar). */
	protected function _refresh_open_totals($order_id)
	{
		$r = $this->db->query("SELECT COALESCE(SUM(line_total), 0) AS s, COALESCE(SUM(cogs), 0) AS c FROM order_items WHERE order_id = ? AND kitchen_status != 'void'", array($order_id))->row_array();
		$this->db->where('id', $order_id)->update('orders', array('subtotal' => $r['s'], 'total' => $r['s'], 'cogs_total' => $r['c']));
	}

	/** Item aktif pesanan dalam format untuk quote(). */
	public function order_items_for_quote($order_id)
	{
		return $this->db->select('oi.id, oi.variant_id, oi.menu_id, m.category_id, oi.qty, oi.unit_price, oi.name')
			->from('order_items oi')->join('menus m', 'm.id = oi.menu_id')
			->where('oi.order_id', (int) $order_id)->where('oi.kitchen_status !=', 'void')
			->order_by('oi.id')->get()->result_array();
	}

	/**
	 * Bayar pesanan. Butuh shift kasir yang sedang buka.
	 * @param array $p method, paid_amount (tunai), card_type, card_last4, approval_code, payment_ref, codes[]
	 */
	public function pay($order_id, array $p)
	{
		$order = $this->_lock_order($order_id);
		if ($order['status'] !== 'open')
		{
			throw new Pos_exception('Pesanan ini sudah ' . ($order['status'] === 'paid' ? 'dibayar' : 'dibatalkan') . '.');
		}
		$shift = $this->current_shift($this->_uid());
		if ( ! $shift)
		{
			throw new Pos_exception('Buka shift kasir dulu sebelum menerima pembayaran.');
		}
		if ( ! isset(Promo_engine::$payment_methods[$p['method']]))
		{
			throw new Pos_exception('Pilih metode pembayaran.');
		}
		$items = $this->order_items_for_quote($order['id']);
		if ( ! $items)
		{
			throw new Pos_exception('Pesanan tidak punya item aktif.');
		}

		$q = $this->quote($items, array('payment_method' => $p['method'], 'is_member' => (bool) $order['is_member'],
			'codes' => $p['codes'], 'customer_id' => $order['customer_id']));

		$row = array('payment_method' => $p['method'], 'card_type' => NULL, 'card_last4' => NULL, 'approval_code' => NULL, 'payment_ref' => NULL);
		switch ($p['method'])
		{
			case 'cash':
				$paid = round((float) $p['paid_amount']);
				if ($paid < $q['total'])
				{
					throw new Pos_exception('Uang diterima kurang dari total ' . rupiah($q['total']) . '.');
				}
				$row['paid_amount'] = $paid;
				$row['change_amount'] = $paid - $q['total'];
				break;
			case 'debit':
			case 'credit':
				// Hanya 4 digit terakhir & kode approval EDC (PCI-DSS: nomor kartu & CVV tidak disimpan).
				if ( ! preg_match('/^\d{4}$/', (string) $p['card_last4']))
				{
					throw new Pos_exception('Isi 4 digit terakhir kartu.');
				}
				if (trim((string) $p['approval_code']) === '')
				{
					throw new Pos_exception('Isi kode approval dari mesin EDC.');
				}
				$row['card_type'] = in_array($p['card_type'], self::$card_types, TRUE) ? $p['card_type'] : 'Lainnya';
				$row['card_last4'] = $p['card_last4'];
				$row['approval_code'] = mb_substr(trim($p['approval_code']), 0, 30);
				$row['paid_amount'] = $q['total'];
				$row['change_amount'] = 0;
				break;
			case 'ewallet':
				if (trim((string) $p['payment_ref']) === '')
				{
					throw new Pos_exception('Isi nomor referensi transaksi e-wallet / QRIS.');
				}
				$row['payment_ref'] = mb_substr(trim($p['payment_ref']), 0, 100);
				$row['paid_amount'] = $q['total'];
				$row['change_amount'] = 0;
				break;
		}

		// Kuota promo dicek ulang dengan kunci baris supaya tidak terlampaui saat bersamaan.
		foreach ($q['applied'] as $a)
		{
			$pr = $this->db->query('SELECT max_usage_total, usage_count FROM promos WHERE id = ? FOR UPDATE', array($a['id']))->row_array();
			if ($pr['max_usage_total'] !== NULL && (int) $pr['usage_count'] >= (int) $pr['max_usage_total'])
			{
				throw new Pos_exception("Kuota promo {$a['name']} baru saja habis. Hitung ulang pembayaran.");
			}
			$this->db->query('UPDATE promos SET usage_count = usage_count + 1 WHERE id = ?', array($a['id']));
			$this->db->insert('order_promos', array('order_id' => $order['id'], 'promo_id' => $a['id'], 'name' => $a['name'], 'discount' => $a['discount']));
		}
		foreach ($items as $idx => $it)
		{
			$this->db->where('id', $it['id'])->update('order_items', array('discount' => isset($q['line_discounts'][$idx]) ? $q['line_discounts'][$idx] : 0));
		}

		$cogs = (float) $this->db->query("SELECT COALESCE(SUM(cogs), 0) AS c FROM order_items WHERE order_id = ? AND kitchen_status != 'void'", array($order['id']))->row()->c;
		$codes = array_filter(array_map('strtoupper', array_map('trim', (array) $p['codes'])));
		$this->db->where('id', $order['id'])->update('orders', array_merge($row, array(
			'status'         => 'paid',
			'subtotal'       => $q['subtotal'],
			'discount_total' => $q['discount'],
			'service_charge' => $q['service_charge'],
			'tax'            => $q['tax'],
			'total'          => $q['total'],
			'cogs_total'     => round($cogs, 2),
			'promo_codes'    => $codes ? mb_substr(implode(',', $codes), 0, 100) : NULL,
			'shift_id'       => $shift['id'],
			'paid_by'        => $this->_uid(),
			'paid_at'        => date('Y-m-d H:i:s'),
		)));
		return $q;
	}

	/**
	 * Batalkan satu item pesanan terbuka. Item yang belum dimasak: stok
	 * bahan dikembalikan. Yang sudah dimasak: bahan dianggap terbuang.
	 */
	public function void_item($item_id, $reason)
	{
		$item = $this->db->query('SELECT * FROM order_items WHERE id = ? FOR UPDATE', array((int) $item_id))->row_array();
		if ( ! $item)
		{
			throw new Pos_exception('Item tidak ditemukan.');
		}
		$order = $this->_lock_order($item['order_id']);
		if ($order['status'] !== 'open')
		{
			throw new Pos_exception('Item hanya bisa dibatalkan saat pesanan belum dibayar. Pembatalan setelah bayar lewat refund.');
		}
		if ($item['kitchen_status'] === 'void')
		{
			throw new Pos_exception('Item sudah dibatalkan.');
		}
		if (trim($reason) === '')
		{
			throw new Pos_exception('Alasan pembatalan wajib diisi.');
		}
		$returned = FALSE;
		if ($item['kitchen_status'] === 'pending')
		{
			$returned = $this->_return_stock($item, $order);
		}
		$this->db->where('id', $item['id'])->update('order_items', array(
			'kitchen_status' => 'void', 'void_reason' => mb_substr($reason, 0, 255), 'void_by' => $this->_uid(),
			'stock_returned' => $returned ? 1 : 0, 'cogs' => $returned ? 0 : $item['cogs'],
		));
		$this->_refresh_open_totals($order['id']);
		return $returned;
	}

	/** Batalkan seluruh pesanan yang belum dibayar. */
	public function void_order($order_id, $reason)
	{
		$order = $this->_lock_order($order_id);
		if ($order['status'] !== 'open')
		{
			throw new Pos_exception('Hanya pesanan yang belum dibayar yang bisa dibatalkan. Setelah bayar gunakan refund.');
		}
		if (trim($reason) === '')
		{
			throw new Pos_exception('Alasan pembatalan wajib diisi.');
		}
		$items = $this->db->where('order_id', $order['id'])->where('kitchen_status !=', 'void')->get('order_items')->result_array();
		$waste = 0;
		foreach ($items as $it)
		{
			if ( ! $this->void_item($it['id'], $reason))
			{
				$waste++;
			}
		}
		$this->db->where('id', $order['id'])->update('orders', array(
			'status' => 'void', 'void_by' => $this->_uid(), 'void_at' => date('Y-m-d H:i:s'), 'void_reason' => mb_substr($reason, 0, 255),
		));
		return $waste;
	}

	protected function _return_stock(array $item, array $order)
	{
		$moves = $this->db->select('id')->where('ref_type', 'order_item')->where('ref_id', (string) $item['id'])->where('qty <', 0)->get('stock_movements')->result_array();
		foreach ($moves as $m)
		{
			$this->CI->stock->reverse($m['id'], array('reason' => 'sales_return', 'notes' => 'Batal ' . $order['order_number'] . ' ' . $item['name']));
		}
		return TRUE;
	}

	/** Ubah status dapur (maju satu langkah atau langsung ke status tertentu yang lebih lanjut). */
	public function set_kitchen_status($item_id, $status)
	{
		$flow = array('pending', 'preparing', 'ready', 'served');
		if ( ! in_array($status, $flow, TRUE))
		{
			throw new Pos_exception('Status tidak valid.');
		}
		$item = $this->db->query('SELECT oi.*, o.status AS order_status FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.id = ? FOR UPDATE', array((int) $item_id))->row_array();
		if ( ! $item OR $item['kitchen_status'] === 'void' OR $item['order_status'] === 'void')
		{
			throw new Pos_exception('Item tidak ditemukan atau sudah dibatalkan.');
		}
		if (array_search($status, $flow, TRUE) <= array_search($item['kitchen_status'], $flow, TRUE))
		{
			return FALSE; // sudah di status itu atau lebih lanjut
		}
		$now = date('Y-m-d H:i:s');
		$row = array('kitchen_status' => $status);
		foreach (array('preparing', 'ready', 'served') as $st)
		{
			if (array_search($st, $flow, TRUE) <= array_search($status, $flow, TRUE) && ! $item[$st . '_at'])
			{
				$row[$st . '_at'] = $now;
			}
		}
		$this->db->where('id', $item['id'])->update('order_items', $row);
		return TRUE;
	}

	public function mark_delivered($order_id)
	{
		$order = $this->_lock_order($order_id);
		if ($order['order_type'] !== 'delivery' OR $order['status'] === 'void')
		{
			throw new Pos_exception('Hanya pesanan delivery yang aktif.');
		}
		$this->db->where('order_id', $order['id'])->where_in('kitchen_status', array('pending', 'preparing', 'ready'))
			->update('order_items', array('kitchen_status' => 'served', 'served_at' => date('Y-m-d H:i:s')));
		$this->db->where('id', $order['id'])->update('orders', array('delivered_at' => date('Y-m-d H:i:s')));
	}

	// ------------------------------------------------------------------
	// Shift kasir (PRD 2.4.6)
	// ------------------------------------------------------------------

	public function current_shift($user_id)
	{
		return $this->db->where('user_id', (int) $user_id)->where('status', 'open')->order_by('id', 'DESC')->get('shifts')->row_array();
	}

	public function open_shift($opening_balance, $shift_name, $register)
	{
		if ($this->current_shift($this->_uid()))
		{
			throw new Pos_exception('Anda masih punya shift yang terbuka.');
		}
		if ($opening_balance < 0)
		{
			throw new Pos_exception('Modal awal tidak boleh negatif.');
		}
		$this->db->insert('shifts', array(
			'user_id'         => $this->_uid(),
			'register_name'   => $register !== '' ? mb_substr($register, 0, 50) : 'Kasir 1',
			'shift_name'      => $shift_name !== '' ? mb_substr($shift_name, 0, 50) : NULL,
			'opened_at'       => date('Y-m-d H:i:s'),
			'opening_balance' => round($opening_balance, 2),
		));
		return (int) $this->db->insert_id();
	}

	/**
	 * Ringkasan shift (PRD 2.4.6 Shift Summary). Refund tunai masuk di fase 6.
	 */
	public function shift_summary($shift_id)
	{
		$s = $this->db->select('s.*, u.name AS user_name, a.name AS approved_by_name')->from('shifts s')
			->join('users u', 'u.id = s.user_id')->join('users a', 'a.id = s.approved_by', 'left')
			->where('s.id', (int) $shift_id)->get()->row_array();
		if ( ! $s)
		{
			return NULL;
		}
		$by = $this->db->query("SELECT payment_method, COUNT(*) AS n, SUM(total) AS total FROM orders WHERE shift_id = ? AND status = 'paid' GROUP BY payment_method", array($s['id']))->result_array();
		$s['by_method'] = array_column($by, NULL, 'payment_method');
		$agg = $this->db->query("SELECT COUNT(*) AS n, COALESCE(SUM(total), 0) AS total, COALESCE(SUM(discount_total), 0) AS discount,
				COALESCE(SUM(tax), 0) AS tax, COALESCE(SUM(service_charge), 0) AS service
			 FROM orders WHERE shift_id = ? AND status = 'paid'", array($s['id']))->row_array();
		$s['transactions'] = (int) $agg['n'];
		$s['sales_total'] = (float) $agg['total'];
		$s['discount_total'] = (float) $agg['discount'];
		$s['tax_total'] = (float) $agg['tax'];
		$s['service_total'] = (float) $agg['service'];
		$s['avg_bill'] = $agg['n'] ? $agg['total'] / $agg['n'] : 0;
		$s['cash_sales'] = isset($s['by_method']['cash']) ? (float) $s['by_method']['cash']['total'] : 0.0;
		$s['cash_refunds'] = $this->_cash_refunds($s['id']);
		$s['expected_now'] = round((float) $s['opening_balance'] + $s['cash_sales'] - $s['cash_refunds'], 2);
		$s['top_items'] = $this->db->query(
			"SELECT oi.name, SUM(oi.qty) AS qty, SUM(oi.line_total - oi.discount) AS total FROM order_items oi JOIN orders o ON o.id = oi.order_id
			 WHERE o.shift_id = ? AND o.status = 'paid' AND oi.kitchen_status != 'void' GROUP BY oi.name ORDER BY qty DESC LIMIT 5",
			array($s['id'])
		)->result_array();
		$s['promo_usage'] = (int) $this->db->query("SELECT COUNT(*) AS n FROM order_promos op JOIN orders o ON o.id = op.order_id WHERE o.shift_id = ? AND o.status = 'paid'", array($s['id']))->row()->n;
		return $s;
	}

	/** Refund tunai di shift ini (tabel refund dibuat di fase 6). */
	protected function _cash_refunds($shift_id)
	{
		if ( ! $this->db->table_exists('order_refunds'))
		{
			return 0.0;
		}
		return (float) $this->db->query("SELECT COALESCE(SUM(refund_amount), 0) AS v FROM order_refunds WHERE shift_id = ? AND refund_method = 'cash' AND status = 'completed'", array((int) $shift_id))->row()->v;
	}

	/**
	 * Tutup shift. Selisih kas dalam batas cash_variance_limit langsung
	 * ditutup; di atasnya menunggu approval manajer.
	 * @return string status baru
	 */
	public function close_shift($shift_id, $closing_balance, $note)
	{
		$s = $this->db->query('SELECT * FROM shifts WHERE id = ? FOR UPDATE', array((int) $shift_id))->row_array();
		if ( ! $s OR $s['status'] !== 'open')
		{
			throw new Pos_exception('Shift tidak sedang terbuka.');
		}
		if ((int) $s['user_id'] !== $this->_uid() && ! $this->CI->rbac->has_permission('sales.shift_approve'))
		{
			throw new Pos_exception('Hanya pemilik shift atau manajer yang bisa menutup shift ini.');
		}
		if ($closing_balance < 0)
		{
			throw new Pos_exception('Saldo akhir tidak boleh negatif.');
		}
		$open_orders = (int) $this->db->query("SELECT COUNT(*) AS n FROM orders WHERE status = 'open' AND created_by = ?", array($s['user_id']))->row()->n;
		$sum = $this->shift_summary($s['id']);
		$variance = round($closing_balance - $sum['expected_now'], 2);
		$limit = (float) setting('cash_variance_limit', 10000);
		if (abs($variance) > $limit && trim($note) === '')
		{
			throw new Pos_exception('Selisih kas ' . rupiah($variance) . ' melebihi batas ' . rupiah($limit) . '. Isi catatan penyebab selisih.');
		}
		$status = abs($variance) <= $limit ? 'closed' : 'pending_approval';
		$this->db->where('id', $s['id'])->update('shifts', array(
			'closed_at'       => date('Y-m-d H:i:s'),
			'closing_balance' => round($closing_balance, 2),
			'expected_cash'   => $sum['expected_now'],
			'variance'        => $variance,
			'status'          => $status,
			'close_note'      => trim($note) !== '' ? mb_substr(trim($note), 0, 255) : NULL,
		));
		return array('status' => $status, 'variance' => $variance, 'open_orders' => $open_orders);
	}

	public function approve_shift($shift_id, $note)
	{
		$s = $this->db->query('SELECT * FROM shifts WHERE id = ? FOR UPDATE', array((int) $shift_id))->row_array();
		if ( ! $s OR $s['status'] !== 'pending_approval')
		{
			throw new Pos_exception('Shift ini tidak menunggu approval.');
		}
		if ((int) $s['user_id'] === $this->_uid() && ! $this->CI->rbac->is_super())
		{
			throw new Pos_exception('Selisih kas harus disetujui oleh orang lain dari kasir shift ini.');
		}
		if (trim($note) === '')
		{
			throw new Pos_exception('Isi catatan approval (hasil investigasi selisih).');
		}
		$this->db->where('id', $s['id'])->update('shifts', array(
			'status' => 'closed', 'approved_by' => $this->_uid(), 'approved_at' => date('Y-m-d H:i:s'), 'approval_note' => mb_substr(trim($note), 0, 255),
		));
	}

	// ------------------------------------------------------------------

	protected function _lock_order($order_id)
	{
		$o = $this->db->query('SELECT * FROM orders WHERE id = ? FOR UPDATE', array((int) $order_id))->row_array();
		if ( ! $o)
		{
			throw new Pos_exception('Pesanan tidak ditemukan.');
		}
		return $o;
	}

	protected function _uid()
	{
		return (int) $this->CI->session->userdata('user_id');
	}
}
