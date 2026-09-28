<?php defined('BASEPATH') OR exit('No direct script access allowed');
$base = 'reports/sales';
$this->load->view('reports/_filters', compact('from', 'to', 'preset', 'base'));
$qs = 'from=' . $from . '&to=' . $to;
$can_cogs = can_any(array('menu.view_cogs', 'report.financial'));
$maxh = 0; foreach ($heat as $hours) { foreach ($hours as $v) { $maxh = max($maxh, $v['tx']); } }
$ramp = array('var(--viz-seq-1)', 'var(--viz-seq-2)', 'var(--viz-seq-3)', 'var(--viz-seq-4)', 'var(--viz-seq-5)', 'var(--viz-seq-6)', 'var(--viz-seq-7)');
$exp = function ($k) use ($qs) { return '<a class="small ms-auto no-print" href="' . site_url('reports/sales?' . $qs . '&export=' . $k) . '"><i class="bi bi-download"></i> CSV</a>'; };
?>
<div class="row g-3 mb-3">
	<div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="stat-label">Pendapatan bersih</div><div class="stat-value fs-4"><?= rupiah($summary['revenue']) ?></div></div></div></div>
	<div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="stat-label">Transaksi</div><div class="stat-value fs-4"><?= (int) $summary['tx'] ?></div></div></div></div>
	<div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="stat-label">Rata-rata bill</div><div class="stat-value fs-4"><?= rupiah($summary['avg_bill']) ?></div></div></div></div>
	<div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="stat-label">Diskon promo</div><div class="stat-value fs-4"><?= rupiah($summary['discount']) ?></div></div></div></div>
</div>

<div class="row g-3 mb-3">
	<div class="col-lg-6">
		<div class="card h-100"><div class="card-header d-flex">Per kategori <?= $exp('category') ?></div>
			<div class="card-body"><div id="c-cat"></div></div></div>
	</div>
	<div class="col-lg-6">
		<div class="card h-100"><div class="card-header d-flex">Per waktu makan</div>
			<div class="card-body"><div id="c-daypart"></div>
				<table class="table table-sm small mt-2 mb-0"><thead><tr><th>Tipe pesanan</th><th class="text-end">Transaksi</th><th class="text-end">Total</th><th class="text-end">Rata-rata</th></tr></thead>
					<?php foreach ($types as $t): ?><tr><td><?= e(Pos_service::$order_types[$t['k']]) ?></td><td class="text-end"><?= (int) $t['tx'] ?></td><td class="text-end"><?= rupiah($t['total']) ?></td><td class="text-end"><?= rupiah($t['avg_bill']) ?></td></tr><?php endforeach; ?>
				</table></div></div>
	</div>
</div>

<div class="card mb-3">
	<div class="card-header d-flex">Jam ramai (jumlah transaksi per hari & jam) <?= $exp('hours') ?></div>
	<div class="card-body">
		<?php if ( ! $maxh): ?><p class="text-muted mb-0">Belum ada transaksi.</p><?php else: ?>
		<div class="table-responsive" id="heat">
			<table class="heat mb-2">
				<tr><th></th><?php for ($h = 0; $h < 24; $h++): ?><th><?= $h ?></th><?php endfor; ?></tr>
				<?php foreach (Promo_engine::$days as $d => $dl): ?>
					<tr><th><?= $dl ?></th>
					<?php for ($h = 0; $h < 24; $h++): $v = isset($heat[$d][$h]) ? $heat[$d][$h] : NULL;
						$i = $v ? min(6, (int) floor($v['tx'] / $maxh * 6.999)) : -1; ?>
						<td <?= $v ? 'data-v="' . (int) $v['tx'] . ' transaksi · ' . e(rupiah($v['total'])) . '" data-tip="' . $dl . ' ' . sprintf('%02d:00', $h) . '" style="background:' . $ramp[$i] . ($i >= 4 ? ';color:#fff' : '') . '"' : 'style="background:#f4f3f0"' ?>><?= $v && $maxh <= 99 ? (int) $v['tx'] : '' ?></td>
					<?php endfor; ?></tr>
				<?php endforeach; ?>
			</table>
			<div class="small text-muted d-flex align-items-center gap-1">Sedikit <?php foreach ($ramp as $c): ?><span style="display:inline-block;width:18px;height:10px;border-radius:2px;background:<?= $c ?>"></span><?php endforeach; ?> Banyak (maks. <?= $maxh ?> transaksi)</div>
		</div>
		<?php endif; ?>
	</div>
</div>

<div class="card mb-3">
	<div class="card-header d-flex">Per menu <?= $exp('items') ?></div>
	<div class="table-responsive">
		<table class="table table-sm table-hover align-middle mb-0">
			<thead><tr><th>Menu</th><th class="text-end">Qty</th><th class="text-end">Penjualan bersih</th><?php if ($can_cogs): ?><th class="text-end">COGS</th><th class="text-end">Laba kotor</th><th class="text-end">Margin</th><?php endif; ?><th class="text-end">Refund</th></tr></thead>
			<tbody>
			<?php if (empty($items)): ?><tr><td colspan="7" class="text-center text-muted py-3">Belum ada penjualan.</td></tr><?php endif; ?>
			<?php foreach ($items as $it): ?>
				<tr><td><?= e($it['name']) ?></td><td class="text-end"><?= (int) $it['qty'] ?></td><td class="text-end"><?= rupiah($it['revenue']) ?></td>
					<?php if ($can_cogs): ?><td class="text-end small"><?= rupiah($it['cogs']) ?></td><td class="text-end small"><?= rupiah($it['profit']) ?></td>
						<td class="text-end small <?= $it['margin'] !== NULL && $it['margin'] < (float) setting('menu_margin_warning', 40) ? 'text-danger' : '' ?>"><?= $it['margin'] !== NULL ? number_format($it['margin'], 1, ',', '.') . '%' : '-' ?></td><?php endif; ?>
					<td class="text-end small"><?= $it['refunded_qty'] ? (int) $it['refunded_qty'] . ' (' . number_format($it['refund_rate'], 1, ',', '.') . '%)' : '-' ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

<div class="row g-3">
	<div class="col-lg-6">
		<div class="card h-100"><div class="card-header d-flex">Metode pembayaran <?= $exp('payment') ?></div>
			<div class="card-body"><div id="c-pay"></div></div></div>
	</div>
	<div class="col-lg-6">
		<div class="card h-100"><div class="card-header d-flex">Pemakaian promo <?= $exp('promos') ?></div>
			<div class="table-responsive"><table class="table table-sm mb-0">
				<thead><tr><th>Promo</th><th class="text-end">Dipakai</th><th class="text-end">Diskon</th><th class="text-end">Rata-rata bill</th></tr></thead>
				<?php if (empty($promos)): ?><tr><td colspan="4" class="text-muted text-center py-3">Tidak ada promo terpakai.</td></tr><?php endif; ?>
				<?php foreach ($promos as $p): ?><tr><td><?= e($p['name']) ?></td><td class="text-end"><?= (int) $p['used'] ?>×</td><td class="text-end"><?= rupiah($p['discount']) ?></td><td class="text-end"><?= rupiah($p['avg_bill']) ?></td></tr><?php endforeach; ?>
			</table></div>
			<div class="card-body small text-muted py-2">ROI kasar promo = rata-rata bill dengan promo dibanding rata-rata keseluruhan (<?= rupiah($summary['avg_bill']) ?>).</div></div>
	</div>
</div>

<script src="<?= base_url('assets/js/charts.js') ?>"></script>
<script>
(function () {
	Charts.bars(document.getElementById('c-cat'), { format: 'rp', name: 'Per kategori', items: <?= json_encode(array_map(function ($c) { return array('label' => $c['name'], 'value' => round($c['revenue']), 'note' => $c['qty'] . ' porsi'); }, array_values($category))) ?> });
	Charts.bars(document.getElementById('c-daypart'), { format: 'rp', name: 'Per waktu makan', items: <?= json_encode(array_map(function ($d) { return array('label' => $d['k'], 'value' => round((float) $d['total']), 'note' => (int) $d['tx'] . ' transaksi'); }, $daypart)) ?> });
	Charts.bars(document.getElementById('c-pay'), { format: 'rp', name: 'Metode pembayaran', items: <?= json_encode(array_map(function ($p) { return array('label' => Promo_engine::$payment_methods[$p['k']], 'value' => round((float) $p['total']), 'note' => (int) $p['tx'] . ' transaksi'); }, $payment)) ?> });
	if (document.getElementById('heat')) Charts.cells(document.getElementById('heat'));
})();
</script>
