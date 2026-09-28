<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Mesin promo (PRD 2.3.4). Dipakai simulator promo dan POS (fase 5)
 * supaya hasil perhitungan selalu sama.
 *
 * Input keranjang, per baris:
 *   variant_id, menu_id, category_ids (kategori menu + semua induknya), qty (int), unit_price, name
 * Konteks:
 *   at (Y-m-d H:i:s), payment_method (cash|debit|credit|ewallet|NULL), is_member (bool),
 *   codes (kode promo yang diinput), customer_usage ([promo_id => jumlah pakai pelanggan])
 *
 * Aturan penggabungan: promo "stackable" boleh digabung satu sama lain;
 * promo non-stackable berdiri sendiri. Mesin memilih kombinasi yang paling
 * menguntungkan pelanggan: semua promo stackable ATAU satu promo
 * non-stackable terbaik. Diskon per baris tidak pernah melebihi nilai baris.
 */
class Promo_engine {

	public static $types = array(
		'fixed'   => 'Potongan nominal',
		'percent' => 'Potongan persen',
		'buy_get' => 'Beli X gratis Y',
		'bundle'  => 'Paket bundling',
	);

	public static $payment_methods = array(
		'cash'    => 'Tunai',
		'debit'   => 'Kartu debit',
		'credit'  => 'Kartu kredit',
		'ewallet' => 'E-wallet / QRIS',
	);

	public static $days = array(1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min');

	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	/** Promo aktif (flag aktif & rentang tanggal), lengkap dengan item target. */
	public function load_promos($at)
	{
		$promos = $this->CI->db->query(
			'SELECT * FROM promos WHERE is_active = 1 AND start_at <= ? AND (end_at IS NULL OR end_at >= ?) ORDER BY id',
			array($at, $at)
		)->result_array();
		if ( ! $promos)
		{
			return array();
		}
		$items = $this->CI->db->where_in('promo_id', array_column($promos, 'id'))->get('promo_items')->result_array();
		$by = array();
		foreach ($items as $it)
		{
			$by[$it['promo_id']][] = $it;
		}
		foreach ($promos as &$p)
		{
			$p['items'] = isset($by[$p['id']]) ? $by[$p['id']] : array();
		}
		return $promos;
	}

	/**
	 * @return array subtotal, total_discount, total, applied[] (promo, discount, lines[idx => nominal]),
	 *               considered[] (promo, discount, status: applied|not_best|rejected, reason), line_discounts[idx => nominal]
	 */
	public function evaluate(array $lines, array $ctx, array $promos = NULL)
	{
		$ctx = array_merge(array('at' => date('Y-m-d H:i:s'), 'payment_method' => NULL, 'is_member' => FALSE,
			'codes' => array(), 'customer_usage' => array()), $ctx);
		$codes = array_map('strtoupper', array_map('trim', (array) $ctx['codes']));
		if ($promos === NULL)
		{
			$promos = $this->load_promos($ctx['at']);
		}

		$subtotal = 0.0;
		foreach ($lines as $i => $l)
		{
			$lines[$i]['qty'] = max(0, (int) $l['qty']);
			$lines[$i]['amount'] = round($lines[$i]['qty'] * (float) $l['unit_price'], 2);
			$subtotal += $lines[$i]['amount'];
		}

		$considered = array();
		$candidates = array();
		foreach ($promos as $p)
		{
			$reason = $this->_reject_reason($p, $lines, $ctx, $codes, $subtotal);
			if ($reason === NULL)
			{
				$alloc = $this->_discount($p, $lines);
				$total = array_sum($alloc);
				if ($total <= 0)
				{
					$reason = 'Belum ada item yang memenuhi';
				}
				else
				{
					$candidates[] = array('promo' => $p, 'discount' => $total, 'lines' => $alloc);
					continue;
				}
			}
			$considered[] = array('promo' => $p, 'discount' => 0, 'status' => 'rejected', 'reason' => $reason);
		}

		// Pilih kombinasi terbaik.
		$stack = array_values(array_filter($candidates, function ($c) { return (int) $c['promo']['stackable'] === 1; }));
		$solo = array_values(array_filter($candidates, function ($c) { return (int) $c['promo']['stackable'] !== 1; }));
		usort($solo, function ($a, $b) { return $b['discount'] <=> $a['discount']; });
		$stack_total = array_sum(array_column($stack, 'discount'));
		$chosen = ($solo && $solo[0]['discount'] > $stack_total) ? array($solo[0]) : $stack;

		// Batasi diskon per baris maksimal nilai baris (urut sesuai pilihan).
		$line_disc = array_fill_keys(array_keys($lines), 0.0);
		$applied = array();
		foreach ($chosen as $c)
		{
			$got = array();
			foreach ($c['lines'] as $idx => $amt)
			{
				$room = $lines[$idx]['amount'] - $line_disc[$idx];
				$take = floor(min($amt, max(0, $room)));
				if ($take > 0)
				{
					$got[$idx] = $take;
					$line_disc[$idx] += $take;
				}
			}
			if (array_sum($got) > 0)
			{
				$applied[] = array('promo' => $c['promo'], 'discount' => array_sum($got), 'lines' => $got);
			}
		}
		$applied_ids = array_map(function ($a) { return (int) $a['promo']['id']; }, $applied);
		$chosen_ids = array_map(function ($c) { return (int) $c['promo']['id']; }, $chosen);
		foreach ($candidates as $c)
		{
			$id = (int) $c['promo']['id'];
			$is = in_array($id, $applied_ids, TRUE);
			if ($is)
			{
				$reason = NULL;
			}
			elseif (in_array($id, $chosen_ids, TRUE))
			{
				$reason = 'Nilai item sudah habis didiskon promo lain';
			}
			else
			{
				$reason = (int) $c['promo']['stackable'] === 1 ? 'Kalah dengan promo non-gabung yang lebih besar' : 'Ada kombinasi promo lain yang lebih menguntungkan';
			}
			$considered[] = array('promo' => $c['promo'], 'discount' => $c['discount'], 'status' => $is ? 'applied' : 'not_best', 'reason' => $reason);
		}

		$total_discount = array_sum($line_disc);
		return array(
			'subtotal'       => round($subtotal, 2),
			'total_discount' => round($total_discount, 2),
			'total'          => round($subtotal - $total_discount, 2),
			'applied'        => $applied,
			'considered'     => $considered,
			'line_discounts' => $line_disc,
			'lines'          => $lines,
		);
	}

	/** Alasan promo tidak berlaku, atau NULL kalau syarat terpenuhi. */
	protected function _reject_reason(array $p, array $lines, array $ctx, array $codes, $subtotal)
	{
		$ts = strtotime($ctx['at']);
		if ($p['promo_code'] !== NULL && $p['promo_code'] !== '' && ! in_array(strtoupper($p['promo_code']), $codes, TRUE))
		{
			return 'Butuh kode promo ' . $p['promo_code'];
		}
		if ($p['days_of_week'] !== NULL && $p['days_of_week'] !== '')
		{
			$days = array_map('intval', explode(',', $p['days_of_week']));
			if ( ! in_array((int) date('N', $ts), $days, TRUE))
			{
				return 'Hanya berlaku hari ' . implode(', ', array_map(function ($d) { return Promo_engine::$days[$d]; }, $days));
			}
		}
		if ($p['time_start'] !== NULL && $p['time_end'] !== NULL)
		{
			$now = date('H:i:s', $ts);
			$in = $p['time_start'] <= $p['time_end']
				? ($now >= $p['time_start'] && $now <= $p['time_end'])
				: ($now >= $p['time_start'] OR $now <= $p['time_end']); // melewati tengah malam
			if ( ! $in)
			{
				return 'Hanya berlaku jam ' . substr($p['time_start'], 0, 5) . '–' . substr($p['time_end'], 0, 5);
			}
		}
		if ((int) $p['member_only'] && ! $ctx['is_member'])
		{
			return 'Khusus member';
		}
		if ($p['payment_methods'] !== NULL && $p['payment_methods'] !== '')
		{
			$methods = explode(',', $p['payment_methods']);
			if ( ! in_array($ctx['payment_method'], $methods, TRUE))
			{
				return 'Khusus pembayaran ' . implode('/', array_map(function ($m) {
					return isset(Promo_engine::$payment_methods[$m]) ? Promo_engine::$payment_methods[$m] : $m;
				}, $methods));
			}
		}
		if ($p['max_usage_total'] !== NULL && (int) $p['usage_count'] >= (int) $p['max_usage_total'])
		{
			return 'Kuota promo habis';
		}
		if ($p['max_usage_per_customer'] !== NULL && isset($ctx['customer_usage'][$p['id']])
			&& (int) $ctx['customer_usage'][$p['id']] >= (int) $p['max_usage_per_customer'])
		{
			return 'Batas pemakaian per pelanggan tercapai';
		}
		if ((float) $p['min_purchase'] > 0 && $subtotal < (float) $p['min_purchase'])
		{
			return 'Minimal belanja ' . rupiah($p['min_purchase']);
		}
		if ((int) $p['min_qty'] > 0)
		{
			$qty = 0;
			foreach ($this->_eligible($p, $lines) as $idx)
			{
				$qty += $lines[$idx]['qty'];
			}
			if ($qty < (int) $p['min_qty'])
			{
				return 'Minimal ' . (int) $p['min_qty'] . ' item yang berlaku';
			}
		}
		return NULL;
	}

	/** Indeks baris yang termasuk cakupan promo. */
	protected function _eligible(array $p, array $lines)
	{
		$cats = $menus = $variants = array();
		foreach ($p['items'] as $it)
		{
			if ($it['item_type'] === 'category') $cats[] = (int) $it['item_id'];
			if ($it['item_type'] === 'menu') $menus[] = (int) $it['item_id'];
			if ($it['item_type'] === 'variant') $variants[] = (int) $it['item_id'];
		}
		$out = array();
		foreach ($lines as $idx => $l)
		{
			if ($l['qty'] <= 0)
			{
				continue;
			}
			// Pakai || (bukan OR): OR kalah prioritas dari '=' sehingga hasilnya salah.
			$ok = $p['scope'] === 'all'
				|| ($p['scope'] === 'category' && array_intersect(array_map('intval', (array) $l['category_ids']), $cats))
				|| ($p['scope'] === 'menu' && (in_array((int) $l['menu_id'], $menus, TRUE) || in_array((int) $l['variant_id'], $variants, TRUE)));
			if ($ok)
			{
				$out[] = $idx;
			}
		}
		return $out;
	}

	/** Diskon per baris (sebelum dibatasi) [idx => nominal]. */
	protected function _discount(array $p, array $lines)
	{
		$alloc = array();
		$value = (float) $p['value'];

		switch ($p['type'])
		{
			case 'fixed':
				$eligible = $this->_eligible($p, $lines);
				if ($p['scope'] === 'all')
				{
					// Potongan sekali per transaksi, dibagi proporsional ke baris.
					$alloc = $this->_spread(min($value, $this->_sum($lines, $eligible)), $lines, $eligible);
				}
				else
				{
					foreach ($eligible as $idx)
					{
						$alloc[$idx] = min($value, (float) $lines[$idx]['unit_price']) * $lines[$idx]['qty'];
					}
				}
				break;

			case 'percent':
				foreach ($this->_eligible($p, $lines) as $idx)
				{
					$alloc[$idx] = $lines[$idx]['amount'] * min(100, $value) / 100;
				}
				break;

			case 'buy_get':
				$buy = max(1, (int) $p['buy_qty']);
				$get = max(1, (int) $p['get_qty']);
				$units = array();
				foreach ($this->_eligible($p, $lines) as $idx)
				{
					for ($i = 0; $i < $lines[$idx]['qty']; $i++)
					{
						$units[] = array($idx, (float) $lines[$idx]['unit_price']);
					}
				}
				// Urut harga termahal dulu; di tiap kelompok (beli + gratis) yang
				// gratis adalah item termurah (harga sama atau lebih murah, PRD 2.3.4).
				usort($units, function ($a, $b) { return $b[1] <=> $a[1]; });
				$group = $buy + $get;
				$full = intdiv(count($units), $group);
				for ($g = 0; $g < $full; $g++)
				{
					for ($k = $buy; $k < $group; $k++)
					{
						list($idx, $price) = $units[$g * $group + $k];
						$alloc[$idx] = (isset($alloc[$idx]) ? $alloc[$idx] : 0) + $price;
					}
				}
				break;

			case 'bundle':
				$need = array();
				foreach ($p['items'] as $it)
				{
					if ($it['item_type'] === 'variant')
					{
						$need[(int) $it['item_id']] = (isset($need[(int) $it['item_id']]) ? $need[(int) $it['item_id']] : 0) + max(1, (int) $it['qty']);
					}
				}
				if ( ! $need)
				{
					break;
				}
				$have = array();
				$price_of = array();
				$idx_of = array();
				foreach ($lines as $idx => $l)
				{
					$vid = (int) $l['variant_id'];
					if (isset($need[$vid]))
					{
						$have[$vid] = (isset($have[$vid]) ? $have[$vid] : 0) + $l['qty'];
						$price_of[$vid] = (float) $l['unit_price'];
						$idx_of[$vid][] = $idx;
					}
				}
				$sets = PHP_INT_MAX;
				foreach ($need as $vid => $q)
				{
					$sets = min($sets, isset($have[$vid]) ? intdiv($have[$vid], $q) : 0);
				}
				if ($sets < 1)
				{
					break;
				}
				$normal = 0.0;
				foreach ($need as $vid => $q)
				{
					$normal += $price_of[$vid] * $q;
				}
				$per_set = $normal - (float) $p['bundle_price'];
				if ($per_set <= 0)
				{
					break;
				}
				// Bagi potongan ke item paket sesuai porsi nilainya.
				foreach ($need as $vid => $q)
				{
					$share = $per_set * $sets * ($price_of[$vid] * $q) / $normal;
					$idx = $idx_of[$vid][0];
					$alloc[$idx] = (isset($alloc[$idx]) ? $alloc[$idx] : 0) + $share;
				}
				break;
		}

		// Batas diskon per transaksi.
		$total = array_sum($alloc);
		if ($p['max_discount'] !== NULL && (float) $p['max_discount'] > 0 && $total > (float) $p['max_discount'])
		{
			$ratio = (float) $p['max_discount'] / $total;
			foreach ($alloc as $idx => $amt)
			{
				$alloc[$idx] = $amt * $ratio;
			}
		}
		// Bulatkan ke rupiah penuh tanpa kehilangan sisa: total dibulatkan ke
		// bawah, sisa pembulatan per baris diberikan ke baris terbesar.
		$target = floor(array_sum($alloc) + 0.0001);
		$largest = NULL;
		foreach ($alloc as $idx => $amt)
		{
			$alloc[$idx] = floor($amt + 0.0001);
			if ($largest === NULL OR $amt > $alloc[$largest])
			{
				$largest = $idx;
			}
		}
		if ($largest !== NULL)
		{
			$alloc[$largest] += $target - array_sum($alloc);
		}
		return array_filter($alloc, function ($amt) { return $amt > 0; });
	}

	protected function _sum(array $lines, array $idxs)
	{
		$s = 0.0;
		foreach ($idxs as $idx)
		{
			$s += $lines[$idx]['amount'];
		}
		return $s;
	}

	protected function _spread($amount, array $lines, array $idxs)
	{
		$base = $this->_sum($lines, $idxs);
		$out = array();
		if ($base <= 0)
		{
			return $out;
		}
		foreach ($idxs as $idx)
		{
			$out[$idx] = $amount * $lines[$idx]['amount'] / $base;
		}
		return $out;
	}
}
