<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Menu_exception extends RuntimeException {}

/**
 * Aturan menu, resep, harga, dan COGS (PRD 2.3.1 - 2.3.3).
 *
 * COGS per porsi = jumlah (qty resep satuan standar x biaya bahan).
 * Biaya bahan = harga beli terakhir (ingredients.current_price); kalau belum
 * pernah dibeli, pakai rata-rata nilai stok. Harga beli terakhir dipakai
 * (bukan FIFO) karena mencerminkan biaya mengganti bahan saat ini, yang
 * relevan untuk menentukan harga jual.
 */
class Menu_service {

	protected $CI;
	protected $db;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->db = $this->CI->db;
	}

	public function run(callable $fn)
	{
		$this->db->trans_begin();
		try
		{
			$result = $fn($this);
			if ($this->db->trans_status() === FALSE)
			{
				throw new Menu_exception('Gagal menyimpan ke database.');
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

	/** Ekspresi SQL biaya per satuan standar bahan (alias tabel ingredients = g). */
	public static function cost_sql()
	{
		return 'CASE WHEN g.current_price > 0 THEN g.current_price WHEN g.qty_on_hand > 0 THEN g.stock_value / g.qty_on_hand ELSE 0 END';
	}

	// ------------------------------------------------------------------
	// Data turunan per varian: harga berlaku, COGS, ketersediaan porsi
	// ------------------------------------------------------------------

	/**
	 * Informasi lengkap varian untuk daftar/POS.
	 * @param int[] $variant_ids
	 * @return array [variant_id => price, member_price, bulk_min_qty, bulk_price, next_price, next_from,
	 *                cogs, margin_pct, portions (NULL = tanpa resep), missing_cost]
	 */
	public function variant_info(array $variant_ids, $at = NULL)
	{
		$variant_ids = array_values(array_unique(array_filter(array_map('intval', $variant_ids))));
		if (empty($variant_ids))
		{
			return array();
		}
		$at = $at ?: date('Y-m-d H:i:s');
		$out = array();
		foreach ($variant_ids as $id)
		{
			$out[$id] = array('price' => NULL, 'member_price' => NULL, 'bulk_min_qty' => NULL, 'bulk_price' => NULL,
				'next_price' => NULL, 'next_from' => NULL, 'cogs' => 0.0, 'margin_pct' => NULL, 'portions' => NULL, 'missing_cost' => 0, 'recipe_lines' => 0);
		}

		// Harga berlaku: effective_from terbaru yang <= $at.
		$prices = $this->db->query(
			'SELECT p.* FROM menu_variant_prices p
			 JOIN (SELECT variant_id, MAX(effective_from) AS ef FROM menu_variant_prices
				   WHERE variant_id IN ? AND effective_from <= ? GROUP BY variant_id) cur
			   ON cur.variant_id = p.variant_id AND cur.ef = p.effective_from
			 ORDER BY p.id',
			array($variant_ids, $at)
		)->result_array();
		foreach ($prices as $p)
		{
			$v =& $out[$p['variant_id']];
			$v['price'] = (float) $p['price'];
			$v['member_price'] = $p['member_price'] !== NULL ? (float) $p['member_price'] : NULL;
			$v['bulk_min_qty'] = $p['bulk_min_qty'] !== NULL ? (int) $p['bulk_min_qty'] : NULL;
			$v['bulk_price'] = $p['bulk_price'] !== NULL ? (float) $p['bulk_price'] : NULL;
			unset($v);
		}
		// Perubahan harga terjadwal berikutnya.
		$next = $this->db->query(
			'SELECT variant_id, price, effective_from FROM menu_variant_prices
			 WHERE variant_id IN ? AND effective_from > ? ORDER BY effective_from DESC',
			array($variant_ids, $at)
		)->result_array();
		foreach ($next as $n)
		{
			$out[$n['variant_id']]['next_price'] = (float) $n['price'];
			$out[$n['variant_id']]['next_from'] = $n['effective_from'];
		}

		// COGS & porsi yang bisa dibuat dari stok.
		$rows = $this->db->query(
			'SELECT r.variant_id, COUNT(*) AS line_count, SUM(r.qty_std * (' . self::cost_sql() . ')) AS cogs,
				MIN(FLOOR(g.qty_on_hand / r.qty_std)) AS portions,
				SUM(CASE WHEN (' . self::cost_sql() . ') <= 0 THEN 1 ELSE 0 END) AS missing_cost
			 FROM menu_recipes r JOIN ingredients g ON g.id = r.ingredient_id
			 WHERE r.variant_id IN ? AND r.qty_std > 0
			 GROUP BY r.variant_id',
			array($variant_ids)
		)->result_array();
		foreach ($rows as $r)
		{
			$v =& $out[$r['variant_id']];
			$v['cogs'] = round((float) $r['cogs'], 2);
			$v['portions'] = max(0, (int) $r['portions']);
			$v['missing_cost'] = (int) $r['missing_cost'];
			$v['recipe_lines'] = (int) $r['line_count'];
			unset($v);
		}
		foreach ($out as &$v)
		{
			if ($v['price'] > 0 && $v['recipe_lines'] > 0)
			{
				$v['margin_pct'] = round(($v['price'] - $v['cogs']) / $v['price'] * 100, 1);
			}
		}
		unset($v);
		return $out;
	}

	/**
	 * Status tampil menu/varian: available | low | out (habis stok) | manual_out | inactive.
	 * 'low' = bisa dibuat tapi < 5 porsi (peringatan saat diorder, PRD 2.3.2).
	 */
	public static function availability($menu_status, $variant_active, $portions)
	{
		if ($menu_status === 'inactive' OR ! $variant_active)
		{
			return 'inactive';
		}
		if ($menu_status === 'out_of_stock')
		{
			return 'manual_out';
		}
		if ($portions !== NULL && setting('menu_auto_oos', '1') === '1' && $portions < 1)
		{
			return 'out';
		}
		if ($portions !== NULL && $portions < 5)
		{
			return 'low';
		}
		return 'available';
	}

	// ------------------------------------------------------------------
	// Simpan
	// ------------------------------------------------------------------

	/**
	 * Simpan menu + varian.
	 * @param array $m         code, name, description, category_id, status, prep_minutes, allergens, spicy_level, sort_order, image_path?
	 * @param array $variants  [id (0 = baru), name, barcode, is_active, sort_order, price?, member_price?, bulk_min_qty?, bulk_price?]
	 *                         Field harga hanya diproses untuk varian baru (harga awal). Perubahan harga lewat set_price().
	 * @return int menu id
	 */
	public function save_menu(array $m, array $variants, $menu_id = NULL)
	{
		if (empty($variants))
		{
			throw new Menu_exception('Menu minimal punya satu varian.');
		}
		$row = array(
			'name'         => $m['name'],
			'description'  => $m['description'] ?: NULL,
			'category_id'  => $m['category_id'] ?: NULL,
			'status'       => $m['status'],
			'prep_minutes' => $m['prep_minutes'] ?: NULL,
			'allergens'    => $m['allergens'] ?: NULL,
			'spicy_level'  => $m['spicy_level'] === '' || $m['spicy_level'] === NULL ? NULL : (int) $m['spicy_level'],
			'sort_order'   => (int) $m['sort_order'],
		);
		if (array_key_exists('image_path', $m))
		{
			$row['image_path'] = $m['image_path'];
		}

		if ($menu_id)
		{
			if ($m['code'] !== '')
			{
				$row['code'] = $m['code'];
			}
			$this->db->where('id', (int) $menu_id)->update('menus', $row);
		}
		else
		{
			$this->CI->load->library('sequence');
			$row['code'] = $m['code'] !== '' ? $m['code'] : $this->CI->sequence->next('MN', '', 4);
			$row['created_by'] = $this->_uid();
			$this->db->insert('menus', $row);
			$menu_id = (int) $this->db->insert_id();
		}

		$existing = array_map('intval', array_column($this->db->select('id')->where('menu_id', $menu_id)->get('menu_variants')->result_array(), 'id'));
		$kept = array();
		$this->CI->load->library('barcode');
		foreach ($variants as $i => $v)
		{
			$vrow = array(
				'name'       => $v['name'],
				'is_active'  => $v['is_active'] ? 1 : 0,
				'sort_order' => $i,
			);
			if ($v['barcode'] !== '')
			{
				$vrow['barcode'] = $v['barcode'];
			}
			if ($v['id'] && in_array((int) $v['id'], $existing, TRUE))
			{
				$this->db->where('id', (int) $v['id'])->update('menu_variants', $vrow);
				$kept[] = (int) $v['id'];
				continue;
			}
			$vrow['menu_id'] = $menu_id;
			$this->db->insert('menu_variants', $vrow);
			$vid = (int) $this->db->insert_id();
			if ($v['barcode'] === '')
			{
				$this->db->where('id', $vid)->update('menu_variants', array('barcode' => $this->CI->barcode->generate($vid)));
			}
			$this->set_price($vid, $v, date('Y-m-d H:i:s'), 'Harga awal');
			$kept[] = $vid;
		}

		// Varian yang dihapus dari form: hapus kalau belum pernah dipakai, kalau
		// sudah (fase POS) cukup dinonaktifkan supaya riwayat penjualan utuh.
		foreach (array_diff($existing, $kept) as $vid)
		{
			if ($this->variant_used($vid))
			{
				$this->db->where('id', $vid)->update('menu_variants', array('is_active' => 0));
			}
			else
			{
				$this->db->where('id', $vid)->delete('menu_variants');
			}
		}
		if ( ! $this->db->where('menu_id', $menu_id)->count_all_results('menu_variants'))
		{
			throw new Menu_exception('Menu minimal punya satu varian.');
		}
		return (int) $menu_id;
	}

	/** Varian sudah dipakai di transaksi penjualan (tabel dibuat di fase POS). */
	public function variant_used($variant_id)
	{
		return $this->db->table_exists('order_items')
			&& $this->db->where('variant_id', (int) $variant_id)->count_all_results('order_items') > 0;
	}

	/**
	 * Catat harga (langsung atau terjadwal). Harga lama tetap tersimpan sebagai riwayat.
	 * @param array $p price, member_price, bulk_min_qty, bulk_price
	 */
	public function set_price($variant_id, array $p, $effective_from, $reason)
	{
		$price = round((float) $p['price'], 2);
		if ($price < 0)
		{
			throw new Menu_exception('Harga tidak boleh negatif.');
		}
		$member = isset($p['member_price']) && $p['member_price'] !== '' && $p['member_price'] !== NULL ? round((float) $p['member_price'], 2) : NULL;
		$bulk_qty = isset($p['bulk_min_qty']) && (int) $p['bulk_min_qty'] > 1 ? (int) $p['bulk_min_qty'] : NULL;
		$bulk_price = $bulk_qty && isset($p['bulk_price']) && $p['bulk_price'] !== '' ? round((float) $p['bulk_price'], 2) : NULL;
		if ($member !== NULL && $member > $price)
		{
			throw new Menu_exception('Harga member tidak boleh lebih mahal dari harga normal.');
		}
		if ($bulk_qty && $bulk_price === NULL)
		{
			throw new Menu_exception('Isi harga grosir untuk minimal qty yang diisi.');
		}
		if ($bulk_price !== NULL && $bulk_price > $price)
		{
			throw new Menu_exception('Harga grosir tidak boleh lebih mahal dari harga normal.');
		}
		// Hapus jadwal lain di waktu yang sama persis supaya tidak ambigu.
		$this->db->where('variant_id', (int) $variant_id)->where('effective_from', $effective_from)->delete('menu_variant_prices');
		$this->db->insert('menu_variant_prices', array(
			'variant_id'     => (int) $variant_id,
			'price'          => $price,
			'member_price'   => $member,
			'bulk_min_qty'   => $bulk_qty,
			'bulk_price'     => $bulk_price,
			'effective_from' => $effective_from,
			'reason'         => $reason !== '' ? mb_substr($reason, 0, 255) : NULL,
			'created_by'     => $this->_uid(),
		));
	}

	/** Batalkan perubahan harga yang BELUM berlaku. */
	public function cancel_scheduled_price($price_id)
	{
		$row = $this->db->where('id', (int) $price_id)->get('menu_variant_prices')->row_array();
		if ( ! $row OR $row['effective_from'] <= date('Y-m-d H:i:s'))
		{
			throw new Menu_exception('Hanya harga terjadwal yang belum berlaku yang bisa dibatalkan.');
		}
		$this->db->where('id', $row['id'])->delete('menu_variant_prices');
		return $row;
	}

	/**
	 * Ganti seluruh resep satu varian.
	 * @param array $lines [ingredient_id, qty, unit, factor, notes]
	 */
	public function save_recipe($variant_id, array $lines)
	{
		$seen = array();
		foreach ($lines as $l)
		{
			if (isset($seen[$l['ingredient_id']]))
			{
				throw new Menu_exception('Bahan yang sama muncul lebih dari sekali di resep. Gabungkan jumlahnya.');
			}
			$seen[$l['ingredient_id']] = TRUE;
		}
		$this->db->where('variant_id', (int) $variant_id)->delete('menu_recipes');
		foreach ($lines as $l)
		{
			$this->db->insert('menu_recipes', array(
				'variant_id'    => (int) $variant_id,
				'ingredient_id' => (int) $l['ingredient_id'],
				'qty'           => $l['qty'],
				'unit'          => $l['unit'],
				'factor'        => $l['factor'],
				'qty_std'       => round($l['qty'] * $l['factor'], 4),
				'notes'         => $l['notes'] !== '' ? mb_substr($l['notes'], 0, 255) : NULL,
			));
		}
	}

	/**
	 * Simpan COGS & harga hari ini ke riwayat (PRD: "Track COGS history").
	 * Dipanggil setelah resep/harga berubah, dan sekali sehari saat daftar menu dibuka.
	 */
	public function snapshot_cogs(array $variant_ids = NULL)
	{
		if ($variant_ids === NULL)
		{
			$variant_ids = array_column($this->db->select('id')->get('menu_variants')->result_array(), 'id');
		}
		$info = $this->variant_info($variant_ids);
		$today = date('Y-m-d');
		foreach ($info as $vid => $v)
		{
			if ($v['recipe_lines'] === 0 && $v['price'] === NULL)
			{
				continue;
			}
			$this->db->query(
				'INSERT INTO menu_cogs_history (variant_id, recorded_on, cogs, price) VALUES (?, ?, ?, ?)
				 ON DUPLICATE KEY UPDATE cogs = VALUES(cogs), price = VALUES(price)',
				array($vid, $today, $v['cogs'], (float) $v['price'])
			);
		}
		return count($info);
	}

	/** Jalankan snapshot harian kalau hari ini belum ada. */
	public function ensure_daily_snapshot()
	{
		$last = $this->db->query('SELECT MAX(recorded_on) AS d FROM menu_cogs_history')->row()->d;
		if ($last !== date('Y-m-d') && $this->db->count_all('menu_variants') > 0)
		{
			$this->snapshot_cogs();
		}
	}

	protected function _uid()
	{
		return (int) $this->CI->session->userdata('user_id') ?: NULL;
	}
}
