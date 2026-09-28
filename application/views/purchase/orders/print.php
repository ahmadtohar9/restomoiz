<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?><!doctype html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<title><?= e($po['po_number']) ?> · <?= e($resto) ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<style>
		body { font: 13px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #111; margin: 0; background: #f3f4f6; }
		.page { max-width: 800px; margin: 24px auto; background: #fff; padding: 36px 40px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
		h1 { font-size: 20px; margin: 0 0 4px; letter-spacing: .04em; }
		.head { display: flex; justify-content: space-between; gap: 24px; border-bottom: 2px solid #111; padding-bottom: 14px; margin-bottom: 18px; }
		.muted { color: #555; }
		.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px; }
		.box { border: 1px solid #ddd; padding: 10px 12px; border-radius: 4px; }
		.box .label { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #666; margin-bottom: 4px; }
		table { width: 100%; border-collapse: collapse; }
		th, td { padding: 7px 8px; border-bottom: 1px solid #e5e7eb; text-align: left; vertical-align: top; }
		th { background: #f9fafb; font-size: 12px; }
		.r { text-align: right; white-space: nowrap; }
		tfoot td { border: 0; padding: 4px 8px; }
		tfoot .total td { font-weight: 700; font-size: 15px; border-top: 2px solid #111; padding-top: 8px; }
		.sign { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 48px; text-align: center; }
		.sign div { padding-top: 56px; border-bottom: 1px solid #999; }
		.sign small { display: block; margin-top: 6px; color: #555; }
		.actions { max-width: 800px; margin: 16px auto 0; text-align: right; }
		.actions button { padding: 8px 16px; font: inherit; cursor: pointer; }
		@media print { body { background: #fff; } .page { box-shadow: none; margin: 0; max-width: none; padding: 0; } .actions { display: none; } }
		@media (max-width: 640px) { .page { padding: 20px 16px; margin: 0; } .grid, .head { grid-template-columns: 1fr; display: block; } .box { margin-bottom: 10px; } }
	</style>
</head>
<body>
<div class="actions"><button onclick="window.print()">Cetak</button></div>
<div class="page">
	<div class="head">
		<div>
			<h1>PURCHASE ORDER</h1>
			<div class="muted"><?= e($resto) ?></div>
		</div>
		<div style="text-align:right">
			<div><strong><?= e($po['po_number']) ?></strong></div>
			<div class="muted">Tanggal: <?= tgl($po['po_date'], FALSE) ?></div>
			<?php if ($po['expected_delivery']): ?><div class="muted">Kirim: <?= tgl($po['expected_delivery'], FALSE) ?></div><?php endif; ?>
		</div>
	</div>

	<div class="grid">
		<div class="box">
			<div class="label">Kepada</div>
			<strong><?= e($po['supplier_name']) ?></strong><br>
			<?= e(trim($po['supplier_address'] . ', ' . $po['supplier_city'], ', ')) ?><br>
			<?php if ($po['contact_person']): ?>u.p. <?= e($po['contact_person']) ?><br><?php endif; ?>
			<?= e($po['supplier_phone']) ?>
		</div>
		<div class="box">
			<div class="label">Pengiriman & pembayaran</div>
			Alamat: <?= e($po['delivery_address'] ?: $resto) ?><br>
			Termin: <?= e(isset(Supplier_model::$payment_terms[$po['payment_terms']]) ? Supplier_model::$payment_terms[$po['payment_terms']] : $po['payment_terms']) ?>
		</div>
	</div>

	<table>
		<thead><tr><th>#</th><th>Bahan</th><th class="r">Jumlah</th><th class="r">Harga</th><th class="r">Subtotal</th></tr></thead>
		<tbody>
		<?php foreach ($items as $i => $it): ?>
			<tr>
				<td><?= $i + 1 ?></td>
				<td><?= e($it['name']) ?><?= $it['notes'] ? '<br><span class="muted">' . e($it['notes']) . '</span>' : '' ?></td>
				<td class="r"><?= qty($it['qty']) ?> <?= e($it['unit']) ?></td>
				<td class="r"><?= rupiah($it['unit_price']) ?></td>
				<td class="r"><?= rupiah($it['subtotal']) ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
		<tfoot>
			<tr><td colspan="4" class="r">Subtotal</td><td class="r"><?= rupiah($po['subtotal']) ?></td></tr>
			<?php if ($po['discount_amount'] > 0): ?><tr><td colspan="4" class="r">Diskon</td><td class="r">−<?= rupiah($po['discount_amount']) ?></td></tr><?php endif; ?>
			<?php if ($po['tax_amount'] > 0): ?><tr><td colspan="4" class="r">Pajak</td><td class="r"><?= rupiah($po['tax_amount']) ?></td></tr><?php endif; ?>
			<tr class="total"><td colspan="4" class="r">TOTAL</td><td class="r"><?= rupiah($po['total_amount']) ?></td></tr>
		</tfoot>
	</table>

	<?php if ($po['notes']): ?><p><strong>Catatan:</strong> <?= e($po['notes']) ?></p><?php endif; ?>

	<div class="sign">
		<div></div><div></div><div></div>
	</div>
	<div class="sign" style="margin-top:0">
		<small>Dibuat: <?= e($po['created_by_name']) ?></small>
		<small>Disetujui: <?= e($po['approved2_name'] ?: ($po['approved1_name'] ?: 'otomatis')) ?></small>
		<small>Supplier</small>
	</div>
</div>
</body>
</html>
