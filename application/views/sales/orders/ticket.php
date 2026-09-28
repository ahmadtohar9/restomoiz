<?php defined('BASEPATH') OR exit('No direct script access allowed');
$w = $paper === '58' ? '58mm' : '80mm';
?><!doctype html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Tiket dapur <?= e($o['order_number']) ?></title>
	<style>
		@page { size: <?= $w ?> auto; margin: 0; }
		body { margin: 0; background: #e5e7eb; font: 14px/1.35 "Courier New", ui-monospace, monospace; color: #000; }
		.t { width: <?= $w ?>; margin: 12px auto; background: #fff; padding: 4mm 3mm; box-sizing: border-box; }
		.head { font-size: 18px; font-weight: 700; text-align: center; }
		hr { border: 0; border-top: 1px dashed #000; margin: 2mm 0; }
		.item { font-size: 16px; font-weight: 700; margin-top: 2mm; }
		.sub { font-size: 13px; padding-left: 5mm; }
		.bar { width: <?= $w ?>; margin: 12px auto 0; text-align: center; }
		.bar button { padding: 6px 14px; font: 14px system-ui, sans-serif; cursor: pointer; }
		@media print { body { background: #fff; } .t { margin: 0; } .bar { display: none; } }
	</style>
</head>
<body>
<div class="bar"><button onclick="window.print()">Cetak tiket</button></div>
<div class="t">
	<div class="head"><?= $o['table_name'] ? 'MEJA ' . e($o['table_name']) : strtoupper(e(Pos_service::$order_types[$o['order_type']])) ?></div>
	<div style="text-align:center"><?= e($o['order_number']) ?><?= $partial ? ' · TAMBAHAN' : '' ?></div>
	<div style="text-align:center">Jam <?= date('H:i', strtotime($partial && $items ? $items[0]['created_at'] : $o['created_at'])) ?><?= $o['customer_name'] ? ' · ' . e($o['customer_name']) : '' ?></div>
	<hr>
	<?php foreach ($items as $it): ?>
		<div class="item">[ ] <?= (int) $it['qty'] ?> x <?= e($it['name']) ?></div>
		<?php foreach ($it['modifiers'] as $m): ?><div class="sub">+ <?= e($m['name']) ?></div><?php endforeach; ?>
		<?php if ($it['notes']): ?><div class="sub">* <?= e($it['notes']) ?></div><?php endif; ?>
	<?php endforeach; ?>
	<?php if ($o['notes']): ?><hr><div>Catatan: <?= e($o['notes']) ?></div><?php endif; ?>
</div>
<script>if (location.search.indexOf('autoprint') > -1) window.print();</script>
</body>
</html>
