<?php defined('BASEPATH') OR exit('No direct script access allowed');
$base = 'reports';
$this->load->view('reports/_filters', compact('from', 'to', 'preset', 'base'));
$delta = function ($a, $b, $invert = FALSE) {
	if ( ! $b) return '<span class="small text-muted">— vs periode lalu</span>';
	$p = ($a - $b) / abs($b) * 100;
	$up = $p >= 0;
	$good = $invert ? ! $up : $up;
	return '<span class="small kpi-delta ' . ($good ? 'up' : 'down') . '"><i class="bi bi-arrow-' . ($up ? 'up' : 'down') . '-short"></i>' . number_format(abs($p), 1, ',', '.') . '% vs periode lalu</span>';
};
$can_fin = can_any(array('report.financial', 'menu.view_cogs'));
$labels_short = array(1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des');
?>
<div class="row g-3 mb-3">
	<?php foreach (array(
		array('Pendapatan bersih', rupiah($cur['revenue']), $delta($cur['revenue'], $prev['revenue']), 'Penjualan setelah diskon & refund, tanpa PPN'),
		array('Transaksi', number_format($cur['tx'], 0, ',', '.'), $delta($cur['tx'], $prev['tx']), ''),
		array('Rata-rata bill', rupiah($cur['avg_bill']), $delta($cur['avg_bill'], $prev['avg_bill']), 'Termasuk PPN & service'),
	) as $k): ?>
		<div class="col-12 col-md-4"><div class="card h-100"><div class="card-body">
			<div class="stat-label" title="<?= e($k[3]) ?>"><?= e($k[0]) ?></div><div class="stat-value"><?= $k[1] ?></div><?= $k[2] ?>
		</div></div></div>
	<?php endforeach; ?>
	<?php if ($can_fin): ?>
		<?php foreach (array(
			array('COGS', rupiah($cur['cogs']), $cur['revenue'] > 0 ? number_format($cur['cogs'] / $cur['revenue'] * 100, 1, ',', '.') . '% dari pendapatan' : ''),
			array('Laba kotor', rupiah($cur['gross_profit']), $delta($cur['gross_profit'], $prev['gross_profit'])),
			array('Margin kotor', $cur['margin'] !== NULL ? number_format($cur['margin'], 1, ',', '.') . '%' : '-', 'Sebelum beban operasional'),
		) as $k): ?>
			<div class="col-12 col-md-4"><div class="card h-100"><div class="card-body">
				<div class="stat-label"><?= e($k[0]) ?></div><div class="stat-value"><?= $k[1] ?></div><span class="small text-muted"><?= $k[2] ?></span>
			</div></div></div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>

<div class="card mb-3">
	<div class="card-header d-flex align-items-center">Tren pendapatan bersih harian <span class="small text-muted fw-normal ms-2"><?= count($daily) ?> hari</span></div>
	<div class="card-body">
		<?php if (count($daily) > 1): ?>
			<div id="chart-trend"></div>
			<details class="mt-2 small"><summary>Lihat tabel</summary>
				<table class="table table-sm mt-2 mb-0"><thead><tr><th>Tanggal</th><th class="text-end">Transaksi</th><th class="text-end">Pendapatan</th></tr></thead>
					<?php foreach ($daily as $d): ?><tr><td><?= tgl($d['date'], FALSE) ?></td><td class="text-end"><?= (int) $d['tx'] ?></td><td class="text-end"><?= rupiah($d['revenue']) ?></td></tr><?php endforeach; ?></table>
			</details>
		<?php else: ?>
			<p class="text-muted mb-0">Pilih rentang lebih dari satu hari untuk melihat tren. Pendapatan hari ini: <strong><?= rupiah($daily[0]['revenue']) ?></strong> dari <?= (int) $daily[0]['tx'] ?> transaksi.</p>
		<?php endif; ?>
	</div>
</div>

<div class="row g-3 mb-3">
	<div class="col-lg-7">
		<div class="card h-100">
			<div class="card-header d-flex">Menu terlaris <a class="ms-auto small no-print" href="<?= site_url('reports/sales?from=' . $from . '&to=' . $to) ?>">rincian</a></div>
			<div class="card-body"><div id="chart-items"></div>
				<?php if ($items): ?><details class="mt-2 small"><summary>Lihat tabel</summary><table class="table table-sm mt-2 mb-0">
					<?php foreach ($items as $it): ?><tr><td><?= e($it['name']) ?></td><td class="text-end"><?= (int) $it['qty'] ?> porsi</td><td class="text-end"><?= rupiah($it['revenue']) ?></td></tr><?php endforeach; ?></table></details><?php endif; ?></div>
		</div>
	</div>
	<div class="col-lg-5">
		<div class="card h-100">
			<div class="card-header">Metode pembayaran</div>
			<div class="card-body"><div id="chart-pay"></div>
				<?php if ($payment): $tot = array_sum(array_column($payment, 'total')); ?>
					<table class="table table-sm small mt-2 mb-0">
						<?php foreach ($payment as $p): ?><tr><td><?= e(Promo_engine::$payment_methods[$p['k']]) ?></td><td class="text-end"><?= (int) $p['tx'] ?> trx</td><td class="text-end"><?= $tot ? number_format($p['total'] / $tot * 100, 1, ',', '.') : 0 ?>%</td></tr><?php endforeach; ?>
					</table>
				<?php endif; ?></div>
		</div>
	</div>
</div>

<div class="row g-3">
	<div class="col-lg-7">
		<div class="card h-100">
			<div class="card-header">Perlu perhatian</div>
			<ul class="list-group list-group-flush">
				<?php $any = FALSE; foreach (array(
					array($alerts['low_stock'], 'bahan di bawah minimum / perlu reorder', 'inventory/stock/alerts', 'danger'),
					array($alerts['expiring'], 'batch mendekati / lewat kedaluwarsa', 'inventory/stock/alerts#expiring', 'danger'),
					array($alerts['po'], 'PO menunggu approval', 'purchase/orders?status=submitted', 'warning'),
					array($alerts['refunds'], 'refund menunggu approval', 'sales/refunds?status=pending_approval', 'warning'),
					array($alerts['overdue'], 'invoice supplier lewat jatuh tempo', 'purchase/invoices?payment=outstanding', 'danger'),
				) as $a): if ( ! $a[0]) continue; $any = TRUE; ?>
					<li class="list-group-item d-flex align-items-center gap-2"><i class="bi bi-exclamation-triangle-fill text-<?= $a[3] ?>"></i>
						<a href="<?= site_url($a[2]) ?>"><?= (int) $a[0] ?> <?= e($a[1]) ?></a></li>
				<?php endforeach; ?>
				<li class="list-group-item d-flex align-items-center gap-2"><i class="bi bi-<?= $cur['refund_rate'] > 3 ? 'exclamation-triangle-fill text-warning' : 'check-circle-fill text-success' ?>"></i>
					Refund rate <?= number_format($cur['refund_rate'], 1, ',', '.') ?>% (<?= (int) $cur['refund_count'] ?> refund, <?= rupiah($cur['refunds']) ?>)<?= $cur['refund_rate'] > 3 ? ' — pantau kualitas' : '' ?></li>
				<?php if ( ! $any): ?><li class="list-group-item text-success small"><i class="bi bi-check-circle"></i> Tidak ada peringatan operasional.</li><?php endif; ?>
			</ul>
		</div>
	</div>
	<div class="col-lg-5 no-print">
		<div class="card h-100">
			<div class="card-header">Aksi cepat</div>
			<div class="card-body d-flex flex-wrap gap-2">
				<?php if (can('purchase.create')): ?><a class="btn btn-outline-primary btn-sm" href="<?= site_url('purchase/orders/create?reorder=1') ?>"><i class="bi bi-cart-plus"></i> Buat PO</a><?php endif; ?>
				<?php if (can_any(array('sales.refund_approve', 'sales.refund_owner'))): ?><a class="btn btn-outline-primary btn-sm" href="<?= site_url('sales/refunds?status=pending_approval') ?>"><i class="bi bi-arrow-counterclockwise"></i> Proses refund</a><?php endif; ?>
				<a class="btn btn-outline-primary btn-sm" href="<?= site_url('reports/sales') ?>"><i class="bi bi-bar-chart"></i> Laporan penjualan</a>
				<?php if (can('report.financial')): ?><a class="btn btn-outline-primary btn-sm" href="<?= site_url('reports/pnl') ?>"><i class="bi bi-file-earmark-bar-graph"></i> Laba rugi</a><?php endif; ?>
				<?php if (can('admin.settings')): ?><a class="btn btn-outline-secondary btn-sm" href="<?= site_url('admin/settings') ?>"><i class="bi bi-gear"></i> Pengaturan</a><?php endif; ?>
			</div>
			<div class="card-body pt-0 small text-muted">Pembanding: <?= tgl($prev_range[0], FALSE) ?> – <?= tgl($prev_range[1], FALSE) ?>.</div>
		</div>
	</div>
</div>

<script src="<?= asset_v('assets/js/charts.js') ?>"></script>
<script>
(function () {
	var daily = <?= json_encode(array_map(function ($d) { return array(date('j/n', strtotime($d['date'])), (float) $d['revenue']); }, $daily)) ?>;
	if (document.getElementById('chart-trend')) Charts.line(document.getElementById('chart-trend'), { labels: daily.map(function (d) { return d[0]; }), values: daily.map(function (d) { return d[1]; }), format: 'rp', name: 'Pendapatan bersih' });
	Charts.bars(document.getElementById('chart-items'), { format: 'rp', name: 'Menu terlaris', items: <?= json_encode(array_map(function ($i) { return array('label' => $i['name'], 'value' => round((float) $i['revenue']), 'note' => (int) $i['qty'] . ' porsi'); }, $items)) ?> });
	Charts.bars(document.getElementById('chart-pay'), { format: 'rp', name: 'Metode pembayaran', items: <?= json_encode(array_map(function ($p) { return array('label' => Promo_engine::$payment_methods[$p['k']], 'value' => round((float) $p['total']), 'note' => (int) $p['tx'] . ' transaksi'); }, $payment)) ?> });
})();
</script>
