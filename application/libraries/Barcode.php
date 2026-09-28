<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Barcode menu (PRD 2.3.5).
 *
 *  - generate(): kode internal format EAN-13 dengan prefix 20 (rentang
 *    "in-store" GS1, tidak bentrok dengan barcode produk pabrik).
 *  - svg(): gambar CODE128 sebagai SVG, tanpa library eksternal.
 *    Angka dengan panjang genap memakai Code Set C (lebih pendek),
 *    selainnya Code Set B.
 */
class Barcode {

	/** Pola bar/spasi CODE128, indeks = nilai simbol (0-106). */
	protected static $patterns = array(
		'212222','222122','222221','121223','121322','131222','122213','122312','132212','221213',
		'221312','231212','112232','122132','122231','113222','123122','123221','223211','221132',
		'221231','213212','223112','312131','311222','321122','321221','312212','322112','322211',
		'212123','212321','232121','111323','131123','131321','112313','132113','132311','211313',
		'231113','231311','112133','112331','132131','113123','113321','133121','313121','211331',
		'231131','213113','213311','213131','311123','311321','331121','312113','312311','332111',
		'314111','221411','431111','111224','111422','121124','121421','141122','141221','112214',
		'112412','122114','122411','142112','142211','241211','221114','413111','241112','134111',
		'111242','121142','121241','114212','124112','124211','411212','421112','421211','212141',
		'214121','412121','111143','111341','131141','114113','114311','411113','411311','113141',
		'114131','311141','411131','211412','211214','211232','2331112',
	);

	/** Kode EAN-13 internal: 20 + 10 digit id + check digit. */
	public function generate($id)
	{
		$base = '20' . str_pad((string) (int) $id, 10, '0', STR_PAD_LEFT);
		return $base . $this->ean_check_digit($base);
	}

	public function ean_check_digit($digits12)
	{
		$sum = 0;
		for ($i = 0; $i < 12; $i++)
		{
			$sum += (int) $digits12[$i] * ($i % 2 ? 3 : 1);
		}
		return (string) ((10 - $sum % 10) % 10);
	}

	/** Barcode kustom yang diizinkan: ASCII cetak 4-40 karakter. */
	public function is_valid($code)
	{
		return (bool) preg_match('/^[\x20-\x7E]{4,40}$/', (string) $code);
	}

	/**
	 * @param string $text   isi barcode
	 * @param int    $module lebar satu modul (px)
	 * @param int    $height tinggi bar (px)
	 * @return string markup <svg>
	 */
	public function svg($text, $module = 2, $height = 50, $show_text = TRUE)
	{
		$codes = $this->encode((string) $text);
		$bars = '';
		$x = 10 * $module; // quiet zone
		foreach ($codes as $code)
		{
			$pattern = self::$patterns[$code];
			for ($i = 0, $n = strlen($pattern); $i < $n; $i++)
			{
				$w = (int) $pattern[$i] * $module;
				if ($i % 2 === 0)
				{
					$bars .= '<rect x="' . $x . '" y="0" width="' . $w . '" height="' . $height . '"/>';
				}
				$x += $w;
			}
		}
		$width = $x + 10 * $module;
		$total_h = $height + ($show_text ? 16 : 0);
		$label = $show_text
			? '<text x="' . ($width / 2) . '" y="' . ($height + 13) . '" text-anchor="middle" font-family="monospace" font-size="12" fill="#000">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</text>'
			: '';
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' ' . $total_h . '" width="' . $width . '" height="' . $total_h
			. '" role="img" aria-label="Barcode ' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '"><rect width="100%" height="100%" fill="#fff"/><g fill="#000">'
			. $bars . '</g>' . $label . '</svg>';
	}

	/** Deretan nilai simbol CODE128 termasuk start, checksum, stop. */
	public function encode($text)
	{
		if ($text === '' OR ! preg_match('/^[\x20-\x7E]+$/', $text))
		{
			throw new InvalidArgumentException('Barcode hanya boleh karakter ASCII cetak.');
		}
		$codes = array();
		if (ctype_digit($text) && strlen($text) % 2 === 0)
		{
			$codes[] = 105; // Start C
			foreach (str_split($text, 2) as $pair)
			{
				$codes[] = (int) $pair;
			}
		}
		else
		{
			$codes[] = 104; // Start B
			foreach (str_split($text) as $ch)
			{
				$codes[] = ord($ch) - 32;
			}
		}
		$sum = $codes[0];
		for ($i = 1, $n = count($codes); $i < $n; $i++)
		{
			$sum += $codes[$i] * $i;
		}
		$codes[] = $sum % 103;
		$codes[] = 106; // Stop
		return $codes;
	}
}
