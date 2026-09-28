<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Upload dokumen privat (invoice, bukti bayar, surat jalan) ke folder
 * storage/ di luar document root. File hanya bisa diunduh lewat controller
 * Files yang mengecek permission, bukan lewat URL langsung.
 *
 * Validasi tanpa ekstensi fileinfo (tidak tersedia di server):
 * gambar dengan getimagesize(), PDF dengan signature "%PDF-".
 */
class Private_upload {

	const MAX_BYTES = 5242880; // 5 MB

	/** Folder yang boleh dipakai => permission untuk mengunduh. */
	public static $folders = array(
		'receipts' => 'purchase.view',
		'invoices' => 'purchase.view',
		'payments' => 'purchase.view',
	);

	public static $mimes = array('jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf');

	public function root()
	{
		return rtrim(dirname(FCPATH), '/') . '/storage/';
	}

	/**
	 * @param string $field   nama input file
	 * @param string $folder  salah satu key $folders
	 * @param array  $errors  pesan error ditambahkan ke sini
	 * @return string|NULL path relatif (folder/nama.ext), NULL kalau tidak ada file / gagal
	 */
	public function save($field, $folder, array &$errors)
	{
		if (empty($_FILES[$field]['name']))
		{
			return NULL;
		}
		if ( ! isset(self::$folders[$folder]))
		{
			$errors[] = 'Folder upload tidak valid.';
			return NULL;
		}
		$file = $_FILES[$field];
		if ($file['error'] !== UPLOAD_ERR_OK)
		{
			$errors[] = in_array($file['error'], array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE), TRUE) ? 'Ukuran lampiran terlalu besar.' : 'Upload lampiran gagal.';
			return NULL;
		}
		if ($file['size'] > self::MAX_BYTES)
		{
			$errors[] = 'Ukuran lampiran maksimal 5 MB.';
			return NULL;
		}

		$ext = NULL;
		$info = @getimagesize($file['tmp_name']);
		$image_types = array(IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp');
		if ($info && isset($image_types[$info[2]]))
		{
			$ext = $image_types[$info[2]];
		}
		else
		{
			$head = (string) @file_get_contents($file['tmp_name'], FALSE, NULL, 0, 5);
			if ($head === '%PDF-')
			{
				$ext = 'pdf';
			}
		}
		if ( ! $ext)
		{
			$errors[] = 'Lampiran harus berupa PDF atau gambar (JPG, PNG, WEBP).';
			return NULL;
		}

		$dir = $this->root() . $folder . '/' . date('Y/m') . '/';
		if ( ! is_dir($dir) && ! @mkdir($dir, 0750, TRUE))
		{
			$errors[] = 'Folder penyimpanan tidak bisa dibuat.';
			return NULL;
		}
		$name = bin2hex(random_bytes(16)) . '.' . $ext;
		if ( ! move_uploaded_file($file['tmp_name'], $dir . $name))
		{
			$errors[] = 'Gagal menyimpan lampiran.';
			return NULL;
		}
		return $folder . '/' . date('Y/m') . '/' . $name;
	}

	/** Path absolut yang sudah divalidasi, atau NULL (cegah path traversal). */
	public function resolve($path)
	{
		if ( ! preg_match('#^([a-z]+)/\d{4}/\d{2}/[a-f0-9]{32}\.(jpg|png|webp|pdf)$#', (string) $path, $m) OR ! isset(self::$folders[$m[1]]))
		{
			return NULL;
		}
		$full = $this->root() . $path;
		return is_file($full) ? $full : NULL;
	}

	public function delete($path)
	{
		$full = $this->resolve($path);
		if ($full)
		{
			@unlink($full);
		}
	}
}
