<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if ( ! function_exists('e'))
{
	/** Escape output HTML. */
	function e($value)
	{
		return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
	}
}

if ( ! function_exists('can'))
{
	/** Cek permission dari view: <?php if (can('menu.view_cogs')): ?> */
	function can($permission)
	{
		$CI =& get_instance();
		return isset($CI->rbac) && $CI->rbac->has_permission($permission);
	}
}

if ( ! function_exists('can_any'))
{
	function can_any(array $permissions)
	{
		$CI =& get_instance();
		return isset($CI->rbac) && $CI->rbac->has_any($permissions);
	}
}

if ( ! function_exists('flash'))
{
	/** Set flash message: flash('success', 'Tersimpan'). */
	function flash($type, $message)
	{
		$CI =& get_instance();
		$CI->session->set_flashdata('flash', array('type' => $type, 'message' => $message));
	}
}

if ( ! function_exists('rupiah'))
{
	function rupiah($amount)
	{
		return 'Rp ' . number_format((float) $amount, 0, ',', '.');
	}
}

if ( ! function_exists('tgl'))
{
	/** Format tanggal-jam Indonesia: 28 Sep 2026 14:32 */
	function tgl($datetime, $with_time = TRUE)
	{
		if (empty($datetime))
		{
			return '-';
		}
		$bulan = array(1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des');
		$ts = strtotime($datetime);
		$out = date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
		return $with_time ? $out . ' ' . date('H:i', $ts) : $out;
	}
}

if ( ! function_exists('is_active_nav'))
{
	/** Kelas 'active' untuk item sidebar berdasarkan segmen URI. */
	function is_active_nav($prefix)
	{
		$uri = uri_string();
		return ($uri === $prefix OR strpos($uri, $prefix . '/') === 0) ? 'active' : '';
	}
}

if ( ! function_exists('asset_v'))
{
	/** URL aset lokal + ?v=waktu-ubah file, agar browser mengambil versi baru setelah deploy. */
	function asset_v($path)
	{
		$file = FCPATH . $path;
		return base_url($path) . (is_file($file) ? '?v=' . filemtime($file) : '');
	}
}

if ( ! function_exists('validate_password'))
{
	/**
	 * Aturan password: minimal 8 karakter, mengandung huruf dan angka.
	 * @return string[] daftar pesan error (kosong = valid)
	 */
	function validate_password($password, $confirm, $old_password = NULL)
	{
		$errors = array();
		if (strlen($password) < 8)
		{
			$errors[] = 'Password baru minimal 8 karakter.';
		}
		if ( ! preg_match('/[A-Za-z]/', $password) OR ! preg_match('/[0-9]/', $password))
		{
			$errors[] = 'Password baru harus mengandung huruf dan angka.';
		}
		if ($password !== $confirm)
		{
			$errors[] = 'Konfirmasi password tidak sama.';
		}
		if ($old_password !== NULL && $password === $old_password)
		{
			$errors[] = 'Password baru tidak boleh sama dengan password lama.';
		}
		return $errors;
	}
}

if ( ! function_exists('setting'))
{
	/** Nilai dari tabel app_settings (di-cache per request). */
	function setting($key, $default = NULL)
	{
		static $cache = NULL;
		if ($cache === NULL)
		{
			$CI =& get_instance();
			$cache = array();
			foreach ($CI->db->get('app_settings')->result_array() as $row)
			{
				$cache[$row['key']] = $row['value'];
			}
		}
		return isset($cache[$key]) && $cache[$key] !== '' ? $cache[$key] : $default;
	}
}

if ( ! function_exists('qty'))
{
	/** Format jumlah stok: 1.250,5 (tanpa nol di belakang koma). */
	function qty($value, $decimals = 3)
	{
		$formatted = number_format((float) $value, $decimals, ',', '.');
		return strpos($formatted, ',') !== FALSE ? rtrim(rtrim($formatted, '0'), ',') : $formatted;
	}
}

if ( ! function_exists('num_in'))
{
	/**
	 * Parse input <input type="number"> (desimal selalu titik) ke float.
	 * Kosong = $empty; tidak valid = NAN (cek dengan is_nan()).
	 */
	function num_in($value, $empty = 0.0)
	{
		$value = trim((string) $value);
		if ($value === '')
		{
			return $empty;
		}
		return is_numeric($value) ? (float) $value : NAN;
	}
}

if ( ! function_exists('send_csv'))
{
	/**
	 * Kirim file CSV (UTF-8 dengan BOM supaya rapi di Excel) lalu hentikan request.
	 * Sel yang diawali = + - @ diberi tanda kutip untuk mencegah formula injection.
	 */
	function send_csv($filename, array $header, $rows)
	{
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) . '"');
		$out = fopen('php://output', 'w');
		fwrite($out, "\xEF\xBB\xBF");
		fputcsv($out, $header);
		foreach ($rows as $row)
		{
			fputcsv($out, array_map(function ($v) {
				$v = (string) $v;
				return ($v !== '' && strpos('=+-@', $v[0]) !== FALSE && ! is_numeric($v)) ? "'" . $v : $v;
			}, $row));
		}
		fclose($out);
		exit;
	}
}

if ( ! function_exists('file_url'))
{
	/** URL unduh lampiran privat (lihat controller Files). */
	function file_url($path)
	{
		return site_url('files/view?path=' . rawurlencode($path));
	}
}

if ( ! function_exists('terms_days'))
{
	/** Jumlah hari dari kode termin pembayaran (NET30 -> 30, COD -> 0). */
	function terms_days($terms)
	{
		return preg_match('/^NET(\d+)$/', (string) $terms, $m) ? (int) $m[1] : 0;
	}
}
