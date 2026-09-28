<?php defined('BASEPATH') OR exit('No direct script access allowed');
$w = $paper === '58' ? '58mm' : '80mm';
?><!doctype html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Struk <?= e($o['order_number']) ?></title>
	<style>
		@page { size: <?= $w ?> auto; margin: 0; }
		body { margin: 0; background: #e5e7eb; font: 12px/1.35 "Courier New", ui-monospace, monospace; color: #000; }
		.r { width: <?= $w ?>; margin: 12px auto; background: #fff; padding: 4mm 3mm; box-sizing: border-box; }
		.c { text-align: center; }
		.b { font-weight: 700; }
		.big { font-size: 15px; }
		hr { border: 0; border-top: 1px dashed #000; margin: 2mm 0; }
		table { width: 100%; border-collapse: collapse; }
		td { vertical-align: top; padding: .3mm 0; }
		.n { text-align: right; white-space: nowrap; }
		.sm { font-size: 10px; }
		.bar { width: <?= $w ?>; margin: 12px auto 0; text-align: center; }
		.bar button { padding: 6px 14px; font: 14px system-ui, sans-serif; cursor: pointer; }
		@media print { body { background: #fff; } .r { margin: 0; } .bar { display: none; } }
	</style>
</head>
<body>
<div class="bar"><button onclick="window.print()">Cetak struk</button></div>
<div class="r">
	<div class="c b big"><?= e($resto['name']) ?></div>
	<?php if ($resto['address']): ?><div class="c sm"><?= e($resto['address']) ?></div><?php endif; ?>
	<?php if ($resto['phone']): ?><div class="c sm">Telp <?= e($resto['phone']) ?></div><?php endif; ?>
	<hr>
	<table class="sm">
		<tr><td>No</td><td class="n"><?= e($o['order_number']) ?></td></tr>
		<tr><td>Tanggal</td><td class="n"><?= date('d/m/Y H:i', strtotime($o['paid_at'] ?: $o['created_at'])) ?></td></tr>
		<tr><td>Kasir</td><td class="n"><?= e($o['paid_by_name'] ?: $o['created_by_name']) ?></td></tr>
		<tr><td><?= e(Pos_service::$order_types[$o['order_type']]) ?></td><td class="n"><?= $o['table_name'] ? 'Meja ' . e($o['table_name']) : e($o['customer_name']) ?></td></tr>
	</table>
	<hr>
	<table>
		<?php foreach ($items as $it): ?>
			<tr><td colspan="2"><?= e($it['name']) ?></td></tr>
			<?php foreach ($it['modifiers'] as $m): ?><tr><td colspan="2" class="sm">  + <?= e($m['name']) ?></td></tr><?php endforeach; ?>
			<tr><td class="sm">  <?= (int) $it['qty'] ?> x <?= number_format($it['unit_price'], 0, ',', '.') ?></td><td class="n"><?= number_format($it['line_total'], 0, ',', '.') ?></td></tr>
			<?php if ($it['discount'] > 0): ?><tr><td class="sm">  diskon</td><td class="n">-<?= number_format($it['discount'], 0, ',', '.') ?></td></tr><?php endif; ?>
		<?php endforeach; ?>
	</table>
	<hr>
	<table>
		<tr><td>Subtotal</td><td class="n"><?= number_format($o['subtotal'], 0, ',', '.') ?></td></tr>
		<?php foreach ($promos as $p): ?><tr><td class="sm"><?= e($p['name']) ?></td><td class="n">-<?= number_format($p['discount'], 0, ',', '.') ?></td></tr><?php endforeach; ?>
		<?php if ($o['service_charge'] > 0): ?><tr><td>Service <?= rtrim(rtrim(number_format($resto['service_rate'], 2, ',', '.'), '0'), ',') ?>%</td><td class="n"><?= number_format($o['service_charge'], 0, ',', '.') ?></td></tr><?php endif; ?>
		<?php if ($o['tax'] > 0): ?><tr><td>PPN <?= rtrim(rtrim(number_format($resto['tax_rate'], 2, ',', '.'), '0'), ',') ?>%</td><td class="n"><?= number_format($o['tax'], 0, ',', '.') ?></td></tr><?php endif; ?>
		<tr class="b big"><td>TOTAL</td><td class="n"><?= number_format($o['total'], 0, ',', '.') ?></td></tr>
	</table>
	<?php if ($o['status'] === 'paid'): ?>
		<hr>
		<table>
			<tr><td><?= e(Promo_engine::$payment_methods[$o['payment_method']]) ?></td><td class="n"><?= number_format($o['paid_amount'], 0, ',', '.') ?></td></tr>
			<?php if ($o['change_amount'] > 0): ?><tr><td>Kembalian</td><td class="n"><?= number_format($o['change_amount'], 0, ',', '.') ?></td></tr><?php endif; ?>
			<?php if ($o['card_last4']): ?><tr><td colspan="2" class="sm"><?= e($o['card_type']) ?> **** <?= e($o['card_last4']) ?> APPR <?= e($o['approval_code']) ?></td></tr><?php endif; ?>
			<?php if ($o['payment_ref']): ?><tr><td colspan="2" class="sm">Ref <?= e($o['payment_ref']) ?></td></tr><?php endif; ?>
		</table>
	<?php else: ?>
		<hr><div class="c b">BELUM DIBAYAR</div>
	<?php endif; ?>
	<?php if ($o['status'] === 'void'): ?><div class="c b">*** DIBATALKAN ***</div><?php endif; ?>
	<?php if ($resto['footer']): ?><hr><div class="c sm"><?= e($resto['footer']) ?></div><?php endif; ?>
</div>
<script>if (location.search.indexOf('autoprint') > -1) window.print();</script>
</body>
</html>
