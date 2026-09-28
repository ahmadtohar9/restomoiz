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
