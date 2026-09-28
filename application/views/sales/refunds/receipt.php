<?php defined('BASEPATH') OR exit('No direct script access allowed');
$w = $paper === '58' ? '58mm' : '80mm';
?><!doctype html>
<html lang="id">
<head>
	<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Bukti refund <?= e($r['refund_number']) ?></title>
	<style>
		@page { size: <?= $w ?> auto; margin: 0; }
		body { margin: 0; background: #e5e7eb; font: 12px/1.35 "Courier New", ui-monospace, monospace; }
		.r { width: <?= $w ?>; margin: 12px auto; background: #fff; padding: 4mm 3mm; box-sizing: border-box; }
		.c { text-align: center; } .b { font-weight: 700; } hr { border: 0; border-top: 1px dashed #000; margin: 2mm 0; }
		table { width: 100%; border-collapse: collapse; } .n { text-align: right; white-space: nowrap; }
		.bar { text-align: center; margin-top: 12px; } .bar button { padding: 6px 14px; font: 14px system-ui; }
		@media print { body { background: #fff; } .r { margin: 0; } .bar { display: none; } }
	</style>
</head>
<body>
<div class="bar"><button onclick="window.print()">Cetak</button></div>
<div class="r">
	<div class="c b"><?= e($resto) ?></div>
	<div class="c b">BUKTI REFUND</div>
	<hr>
	<table>
		<tr><td>No</td><td class="n"><?= e($r['refund_number']) ?></td></tr>
		<tr><td>Transaksi</td><td class="n"><?= e($r['order_number']) ?></td></tr>
		<tr><td>Tanggal</td><td class="n"><?= date('d/m/Y H:i', strtotime($r['completed_at'])) ?></td></tr>
	</table>
	<hr>
	<table>
		<?php foreach ($items as $it): ?><tr><td><?= (int) $it['qty'] ?> x <?= e($it['name']) ?></td><td class="n"><?= number_format($it['amount'], 0, ',', '.') ?></td></tr><?php endforeach; ?>
		<?php if ($r['service_amount'] > 0): ?><tr><td>Service</td><td class="n"><?= number_format($r['service_amount'], 0, ',', '.') ?></td></tr><?php endif; ?>
		<?php if ($r['tax_amount'] > 0): ?><tr><td>PPN</td><td class="n"><?= number_format($r['tax_amount'], 0, ',', '.') ?></td></tr><?php endif; ?>
		<tr class="b"><td>TOTAL REFUND</td><td class="n"><?= number_format($r['refund_amount'], 0, ',', '.') ?></td></tr>
	</table>
	<hr>
	<div>Alasan: <?= e($r['reason']) ?></div>
	<div>Dikembalikan: <?= $r['refund_method'] === 'cash' ? 'Tunai' : 'Metode asal' ?></div>
	<br><br><div class="c">(.........................)<br>Tanda tangan pelanggan</div>
</div>
</body>
</html>
