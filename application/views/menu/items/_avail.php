<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Badge ketersediaan varian. Butuh: $a (hasil Menu_service::availability), $portions */
$map = array(
	'available'  => array('Tersedia', 'success'),
	'low'        => array('Sisa ' . (int) $portions . ' porsi', 'warning'),
	'out'        => array('Habis (bahan)', 'danger'),
	'manual_out' => array('Habis', 'danger'),
	'inactive'   => array('Nonaktif', 'secondary'),
);
?><span class="badge text-bg-<?= $map[$a][1] ?>"><?= e($map[$a][0]) ?></span><?php if ($a === 'available' && $portions !== NULL && $portions < 1000): ?> <span class="small text-muted"><?= (int) $portions ?> porsi</span><?php endif; ?>
