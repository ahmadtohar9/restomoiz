<?php defined('BASEPATH') OR exit('No direct script access allowed');
$CI =& get_instance();
?><!doctype html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Label Menu · <?= e($resto) ?></title>
	<style>
		body { font: 12px/1.35 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; margin: 0; background: #f3f4f6; color: #111; }
		.bar { max-width: 210mm; margin: 12px auto; display: flex; gap: 8px; align-items: center; }
		.bar button { padding: 6px 14px; font: inherit; cursor: pointer; }
		.sheet { max-width: 210mm; margin: 0 auto 24px; background: #fff; padding: 8mm; display: grid; grid-template-columns: repeat(3, 1fr); gap: 4mm; }
		.label { border: 1px dashed #bbb; border-radius: 2mm; padding: 3mm; text-align: center; break-inside: avoid; display: flex; flex-direction: column; align-items: center; gap: 1.5mm; }
		.name { font-weight: 700; font-size: 13px; }
		.variant { color: #444; }
		.price { font-weight: 700; font-size: 14px; }
		.label svg { max-width: 100%; height: auto; }
		.qr canvas, .qr img { width: 22mm; height: 22mm; }
		.resto { font-size: 9px; color: #777; }
		@media print { body { background: #fff; } .bar { display: none; } .sheet { margin: 0; padding: 0; max-width: none; } .label { border-color: #ddd; } }
		@media (max-width: 640px) { .sheet { grid-template-columns: repeat(2, 1fr); padding: 4mm; } }
	</style>
</head>
<body>
<div class="bar"><button onclick="window.print()">Cetak</button><span><?= count($rows) * $copies ?> label</span></div>
<div class="sheet">
<?php foreach ($rows as $r): $price = $info[$r['id']]['price']; for ($c = 0; $c < $copies; $c++): ?>
	<div class="label">
		<div class="name"><?= e($r['menu_name']) ?></div>
		<?php if ($r['name'] !== 'Reguler'): ?><div class="variant"><?= e($r['name']) ?></div><?php endif; ?>
		<?php if ($show_price && $price !== NULL): ?><div class="price"><?= rupiah($price) ?></div><?php endif; ?>
		<?php if ($r['barcode']): ?><?= $CI->barcode->svg($r['barcode'], 1, 36) ?><?php endif; ?>
		<?php if ($show_qr): ?><div class="qr" data-qr="<?= e(site_url('menu-online') . '#m' . $r['menu_id']) ?>"></div><?php endif; ?>
		<div class="resto"><?= e($resto) ?></div>
	</div>
<?php endfor; endforeach; ?>
</div>
<?php if ($show_qr): ?>
<script src="<?= base_url('assets/vendor/qrcode-generator/qrcode.js') ?>"></script>
<script>
document.querySelectorAll('[data-qr]').forEach(function (el) {
	var qr = qrcode(0, 'M');
	qr.addData(el.getAttribute('data-qr'));
	qr.make();
	el.innerHTML = qr.createSvgTag({ cellSize: 3, margin: 0, scalable: true });
});
</script>
<?php endif; ?>
</body>
</html>
