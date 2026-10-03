<?php defined('BASEPATH') OR exit('No direct script access allowed');
$hour = (int) date('G');
$greet = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));
$hari = array('Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu');
$first_name = strtok(trim($current_user['name']), ' ');

$actions = array(
	array('Buka POS', 'cash-coin', 'pos', can_any(array('sales.process', 'sales.order'))),
	array('Stok Masuk', 'box-arrow-in-down', 'inventory/stock/in', can('inventory.adjust')),
	array('Buat PO', 'cart-plus', 'purchase/orders/create', can('purchase.create')),
	array('Daftar Menu', 'journal-richtext', 'menu/items', can('menu.view')),
	array('Laporan', 'bar-chart-line', 'reports', can_any(array('report.operational', 'report.financial'))),
);
$actions = array_filter($actions, function ($a) { return $a[3]; });

// Perubahan vs kemarin (sampai jam yang sama): teks + ikon, tidak hanya warna.
$delta = function ($now, $before, $money = TRUE) {
	$now = (float) $now; $before = (float) $before;
	if ($before <= 0) {
		return $now > 0 ? '<span class="kpi-delta up"><i class="bi bi-arrow-up-right"></i> baru hari ini</span>' : '<span class="text-muted">belum ada transaksi</span>';
	}
	$pct = ($now - $before) / $before * 100;
	$cls = $pct >= 0 ? 'up' : 'down';
	$icon = $pct >= 0 ? 'arrow-up-right' : 'arrow-down-right';
	return '<span class="kpi-delta ' . $cls . '"><i class="bi bi-' . $icon . '"></i> ' . ($pct >= 0 ? '+' : '') . number_format($pct, 0, ',', '.') . '%</span> <span class="text-muted">vs kemarin</span>';
};

// Pusat tindakan: semua hal yang menunggu, dari penjualan, inventory, dan pembelian.
$todo = array();
if (isset($sales_today))
{
	$st = $sales_today;
	$todo[] = array('Pesanan belum dibayar', $st['open'], 'sales/orders?status=open', 'hourglass-split', 'warning');
	$todo[] = array('Shift menunggu approval selisih kas', $st['pending_shifts'], 'sales/shifts', 'cash-stack', 'danger');
	$todo[] = array('Refund menunggu approval', $st['refunds_pending'], 'sales/refunds?status=pending_approval', 'arrow-counterclockwise', 'danger');
	$todo[] = array('Refund disetujui, uang belum dikembalikan', $st['refunds_to_pay'], 'sales/refunds?status=approved', 'wallet2', 'info');
	$todo[] = array('Hari belum di-settle', $st['unsettled'], 'finance/settlements', 'journal-check', 'warning');
}
if (isset($stock_alerts))
{
	$todo[] = array('Bahan di bawah minimum', $stock_alerts['low'], 'inventory/ingredients?stock=low', 'exclamation-triangle', 'danger');
	$todo[] = array('Bahan perlu reorder', $stock_alerts['reorder'], 'inventory/ingredients?stock=reorder', 'arrow-repeat', 'warning');
	$todo[] = array('Mendekati / lewat kedaluwarsa', $stock_alerts['expiring'], 'inventory/stock/alerts#expiring', 'calendar-x', 'danger');
	$todo[] = array('Dead stock', $stock_alerts['dead'], 'inventory/stock/alerts#dead', 'archive', 'secondary');
	$todo[] = array('Stok di atas maksimum', $stock_alerts['over'], 'inventory/ingredients?stock=over', 'box-seam', 'info');
}
if (isset($purchase))
{
	foreach (array(
		array('PO menunggu approval Manajer', 'approval_1', 'purchase/orders?status=submitted', 'purchase.approve', 'clipboard-check', 'warning'),
		array('PO menunggu approval Owner', 'approval_2', 'purchase/orders?status=submitted', 'purchase.approve_owner', 'clipboard-check', 'warning'),
		array('PO menunggu barang datang', 'to_receive', 'purchase/receipts', 'purchase.receive', 'truck', 'primary'),
		array('PO terlambat dari tanggal kirim', 'late', 'purchase/receipts', 'purchase.view', 'truck', 'danger'),
		array('Barang diterima, belum ada invoice', 'uninvoiced', 'purchase/invoices', 'purchase.invoice', 'hourglass-split', 'warning'),
		array('Invoice supplier perlu direview', 'invoices', 'purchase/invoices?status=pending', 'purchase.invoice', 'receipt', 'warning'),
		array('Pembayaran supplier perlu diverifikasi', 'payments', 'purchase/payments?status=pending', 'purchase.payment', 'credit-card', 'warning'),
		array('Invoice lewat jatuh tempo', 'overdue', 'purchase/invoices?payment=outstanding', 'purchase.view', 'calendar-x', 'danger'),
	) as $t)
	{
		if (can($t[3])) $todo[] = array($t[0], $purchase[$t[1]], $t[2], $t[4], $t[5]);
	}
}
$todo = array_values(array_filter($todo, function ($t) { return (int) $t[1] > 0; }));
$sev = array('danger' => 0, 'warning' => 1, 'primary' => 2, 'info' => 3, 'secondary' => 4);
usort($todo, function ($a, $b) use ($sev) { return $sev[$a[4]] - $sev[$b[4]]; });

$pay_labels = array('cash' => 'Tunai', 'debit' => 'Kartu debit', 'credit' => 'Kartu kredit', 'ewallet' => 'E-wallet / QRIS', '' => 'Lainnya');
$type_labels = array('dine_in' => 'Dine-in', 'takeaway' => 'Takeaway', 'delivery' => 'Delivery');
$type_icons = array('dine_in' => 'cup-hot', 'takeaway' => 'bag', 'delivery' => 'scooter');
$can_orders = can_any(array('sales.process', 'sales.order', 'sales.edit_order', 'report.operational'));
?>

<!-- Sambutan & aksi cepat -->
<section class="dash-hero mb-4">
	<div class="tw-relative tw-z-10 tw-flex tw-flex-col tw-gap-5 2xl:tw-flex-row 2xl:tw-items-end">
		<div class="tw-min-w-0">
			<div class="tw-mb-2 tw-inline-flex tw-items-center tw-gap-2 tw-rounded-full tw-bg-white/10 tw-px-3 tw-py-1 tw-text-xs tw-font-medium tw-text-amber-200">
				<i class="bi bi-calendar-event"></i> <?= $hari[(int) date('w')] ?>, <?= tgl(date('Y-m-d'), FALSE) ?> · <span id="dash-clock"><?= date('H:i') ?></span>
			</div>
			<h2 class="tw-m-0 tw-text-2xl tw-font-semibold tw-text-white sm:tw-text-3xl"><?= $greet ?>, <?= e($first_name) ?></h2>
			<p class="tw-mb-0 tw-mt-1.5 tw-text-sm tw-text-slate-300">
				<?php if (isset($sales_today)): ?>
					<?= (int) $sales_today['tx'] ?> transaksi hari ini senilai <strong class="tw-text-white"><?= rupiah($sales_today['revenue']) ?></strong><?= $todo ? ' · ' . count($todo) . ' hal perlu perhatian' : ' · semua beres' ?>.
				<?php else: ?>
					Ringkasan operasional <?= e(setting('resto_name', 'Resto Moiz')) ?> hari ini.
				<?php endif; ?>
			</p>
		</div>
		<?php if ($actions): ?>
		<div class="tw-flex tw-flex-wrap tw-gap-2 2xl:tw-ml-auto">
			<?php foreach ($actions as $i => $a): ?>
				<a class="dash-action<?= $i === 0 ? ' is-primary' : '' ?>" href="<?= site_url($a[2]) ?>"><i class="bi bi-<?= $a[1] ?>"></i> <?= e($a[0]) ?></a>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
	</div>
</section>

<?php if (isset($sales_today)): $st = $sales_today; $sy = $sales_yesterday;
	$avg = $st['tx'] ? $st['revenue'] / $st['tx'] : 0;
	$avg_y = $sy['tx'] ? $sy['revenue'] / $sy['tx'] : 0;
	$kpis = array(
		array('Pendapatan hari ini', rupiah($st['revenue']), 'cash-coin', 'tw-bg-indigo-50 tw-text-indigo-600', $delta($st['revenue'], $sy['revenue'])),
		array('Transaksi', number_format($st['tx'], 0, ',', '.'), 'receipt', 'tw-bg-sky-50 tw-text-sky-600', $delta($st['tx'], $sy['tx'], FALSE)),
		array('Rata-rata bill', rupiah($avg), 'basket', 'tw-bg-amber-50 tw-text-amber-600', $st['tx'] && $sy['tx'] ? $delta($avg, $avg_y) : '<span class="text-muted">per transaksi</span>'),
	);
	if (can('menu.view_cogs'))
	{
		$gp = $st['revenue'] - $st['cogs'];
		$kpis[] = array('Laba kotor', rupiah($gp), 'graph-up-arrow', 'tw-bg-emerald-50 tw-text-emerald-600',
			$st['revenue'] > 0 ? '<span class="text-muted">margin</span> <strong class="tw-text-slate-700">' . number_format($gp / $st['revenue'] * 100, 1, ',', '.') . '%</strong> <span class="text-muted">· sblm pajak</span>' : '<span class="text-muted">sebelum pajak</span>');
	}
	else
	{
		$kpis[] = array('Pesanan terbuka', number_format($st['open'], 0, ',', '.'), 'hourglass-split', 'tw-bg-emerald-50 tw-text-emerald-600', '<span class="text-muted">belum dibayar</span>');
	}
?>
<div class="row g-3 mb-4">
	<?php foreach ($kpis as $k): ?>
	<div class="col-6 col-xl-3">
		<div class="card kpi-card h-100">
			<div class="card-body">
				<div class="d-flex align-items-start gap-3">
					<div class="tw-min-w-0 tw-flex-1">
						<div class="stat-label"><?= e($k[0]) ?></div>
						<div class="stat-value tw-mt-1 tw-whitespace-nowrap !tw-text-[1.2rem] tw-leading-tight sm:!tw-text-[1.6rem]"><?= $k[1] ?></div>
					</div>
					<div class="stat-icon <?= $k[3] ?> d-none d-sm-grid"><i class="bi bi-<?= $k[2] ?>"></i></div>
				</div>
				<div class="tw-mt-2 tw-text-xs"><?= $k[4] ?></div>
			</div>
		</div>
	</div>
	<?php endforeach; ?>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
	<?php if (isset($trend)): ?>
	<div class="col-xl-8">
		<div class="card h-100">
			<div class="card-header d-flex align-items-center">
				<span>Pendapatan 14 hari terakhir</span>
				<span class="ms-auto small fw-normal text-muted">Total <strong class="text-body"><?= rupiah(array_sum(array_column($trend, 1))) ?></strong></span>
			</div>
			<div class="card-body"><div id="c-trend"></div>
				<details class="small mt-2"><summary class="text-muted">Lihat tabel</summary>
					<table class="table table-sm mt-2 mb-0"><thead><tr><th>Tanggal</th><th class="text-end">Pendapatan</th></tr></thead>
						<?php foreach (array_reverse($trend) as $t): ?><tr><td><?= e($t[0]) ?></td><td class="text-end"><?= rupiah($t[1]) ?></td></tr><?php endforeach; ?></table>
				</details>
			</div>
		</div>
	</div>
	<?php endif; ?>
	<div class="<?= isset($trend) ? 'col-xl-4 d-flex flex-column gap-3' : 'col-12' ?>">
		<div class="card<?= isset($trend) ? '' : ' h-100' ?>">
			<div class="card-header d-flex align-items-center">
				<span>Perlu tindakan</span>
				<?php if ($todo): ?><span class="badge text-bg-danger ms-2 rounded-pill"><?= count($todo) ?></span><?php endif; ?>
			</div>
			<?php if ( ! $todo): ?>
				<div class="card-body d-flex flex-column align-items-center justify-content-center text-center py-4">
					<div class="stat-icon tw-mb-3 tw-h-14 tw-w-14 tw-rounded-2xl tw-bg-emerald-50 tw-text-2xl tw-text-emerald-600"><i class="bi bi-check2-circle"></i></div>
					<div class="fw-semibold">Semua beres</div>
					<div class="small text-muted">Tidak ada yang menunggu tindakan Anda.</div>
				</div>
			<?php else: ?>
				<div class="list-group list-group-flush todo-list">
					<?php foreach ($todo as $t): ?>
						<a class="list-group-item list-group-item-action d-flex align-items-center gap-3" href="<?= site_url($t[2]) ?>">
							<span class="todo-icon text-<?= $t[4] ?> bg-<?= $t[4] ?>-subtle"><i class="bi bi-<?= $t[3] ?>"></i></span>
							<span class="flex-fill small fw-medium"><?= e($t[0]) ?></span>
							<span class="badge rounded-pill text-bg-<?= $t[4] ?>"><?= (int) $t[1] ?></span>
							<i class="bi bi-chevron-right text-muted small"></i>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php if (isset($sales_today)): ?>
		<div class="card flex-fill">
		<div class="card-header">Metode bayar & tipe pesanan</div>
		<div class="card-body">
			<?php if ( ! $pay_today): ?>
				<div class="text-muted small text-center py-4"><i class="bi bi-credit-card fs-3 d-block mb-1"></i> Belum ada pembayaran hari ini.</div>
			<?php else: $sum = array_sum(array_column($pay_today, 'v')) ?: 1; ?>
				<?php foreach ($pay_today as $p): ?>
					<div class="mb-2">
						<div class="d-flex small"><span class="fw-medium"><?= e(isset($pay_labels[$p['k']]) ? $pay_labels[$p['k']] : $p['k']) ?></span>
							<span class="ms-auto text-muted"><?= (int) $p['n'] ?>× · <strong class="text-body"><?= rupiah($p['v']) ?></strong></span></div>
						<div class="meter" title="<?= round($p['v'] / $sum * 100) ?>%"><span style="width: <?= round($p['v'] / $sum * 100) ?>%"></span></div>
					</div>
				<?php endforeach; ?>
				<hr class="my-3">
				<div class="d-flex gap-2">
					<?php foreach ($type_today as $t): ?>
						<div class="type-chip flex-fill">
							<i class="bi bi-<?= isset($type_icons[$t['k']]) ? $type_icons[$t['k']] : 'receipt' ?>"></i>
							<div><div class="small text-muted"><?= e(isset($type_labels[$t['k']]) ? $type_labels[$t['k']] : $t['k']) ?></div><div class="fw-semibold"><?= (int) $t['n'] ?> <span class="small text-muted fw-normal">pesanan</span></div></div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
		<?php endif; ?>
	</div>
</div>

<?php if (isset($sales_today)): ?>
<div class="row g-3 mb-4">
	<div class="col-lg-6">
		<div class="card h-100">
			<div class="card-header">Menu terlaris hari ini</div>
			<div class="card-body">
				<?php if ( ! $top_today): ?>
					<div class="text-muted small text-center py-4"><i class="bi bi-cup-hot fs-3 d-block mb-1"></i> Belum ada penjualan hari ini.</div>
				<?php else: $max = max(array_map(function ($t) { return (float) $t['qty']; }, $top_today)); foreach ($top_today as $i => $t): ?>
					<div class="rank-row">
						<span class="rank-no<?= $i === 0 ? ' is-first' : '' ?>"><?= $i + 1 ?></span>
						<div class="flex-fill tw-min-w-0">
							<div class="d-flex small"><span class="text-truncate fw-medium"><?= e($t['name']) ?></span><span class="ms-auto text-muted text-nowrap ps-2"><?= (float) $t['qty'] ?> porsi</span></div>
							<div class="meter"><span style="width: <?= round($t['qty'] / $max * 100) ?>%"></span></div>
						</div>
					</div>
				<?php endforeach; endif; ?>
			</div>
		</div>
	</div>
	<div class="col-lg-6">
		<div class="card h-100">
			<div class="card-header d-flex align-items-center">Transaksi terakhir
				<?php if ($can_orders): ?><a class="ms-auto small fw-normal" href="<?= site_url('sales/orders') ?>">Semua</a><?php endif; ?></div>
			<?php if ( ! $recent_orders): ?>
				<div class="card-body text-muted small text-center py-4"><i class="bi bi-receipt fs-3 d-block mb-1"></i> Belum ada transaksi.</div>
			<?php else: ?>
				<div class="list-group list-group-flush">
					<?php foreach ($recent_orders as $o):
						$who = $o['table_name'] ? 'Meja ' . $o['table_name'] : ($o['customer_name'] ?: (isset($type_labels[$o['order_type']]) ? $type_labels[$o['order_type']] : ''));
						$tag = $can_orders ? 'a' : 'div'; ?>
						<<?= $tag ?> class="list-group-item<?= $can_orders ? ' list-group-item-action' : '' ?> d-flex align-items-center gap-3"<?= $can_orders ? ' href="' . site_url('sales/orders/show/' . $o['id']) . '"' : '' ?>>
							<span class="todo-icon tw-bg-slate-100 tw-text-slate-500"><i class="bi bi-<?= isset($type_icons[$o['order_type']]) ? $type_icons[$o['order_type']] : 'receipt' ?>"></i></span>
							<div class="flex-fill tw-min-w-0 lh-sm">
								<div class="small fw-medium text-truncate"><?= e($o['order_number']) ?></div>
								<div class="small text-muted text-truncate"><?= e($who) ?> · <?= date('Y-m-d', strtotime($o['paid_at'])) === date('Y-m-d') ? date('H:i', strtotime($o['paid_at'])) : date('j/n H:i', strtotime($o['paid_at'])) ?></div>
							</div>
							<div class="text-end lh-sm">
								<div class="small fw-semibold"><?= rupiah($o['total']) ?></div>
								<div class="small text-muted"><?= e(isset($pay_labels[(string) $o['payment_method']]) ? $pay_labels[(string) $o['payment_method']] : '') ?></div>
							</div>
						</<?= $tag ?>>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
<?php endif; ?>

<?php
$mini = array();
if (isset($stock_totals))
{
	$mini[] = array('Bahan baku aktif', number_format($stock_totals['items'], 0, ',', '.'), 'box-seam', 'inventory/ingredients', (int) $stock_totals['empty'] ? (int) $stock_totals['empty'] . ' stok habis' : 'tidak ada stok habis');
	if (can('inventory.view_cost')) $mini[] = array('Nilai persediaan', rupiah($stock_totals['value']), 'boxes', 'inventory/reports', 'harga pokok FIFO');
}
if (isset($purchase))
{
	$mini[] = array('Hutang supplier', rupiah($purchase['outstanding']), 'bank', 'purchase/invoices?payment=outstanding', $purchase['overdue'] ? $purchase['overdue'] . ' invoice lewat jatuh tempo' : 'tidak ada tunggakan');
}
if (isset($stats))
{
	$mini[] = array('User aktif', number_format($stats['users_active'], 0, ',', '.'), 'people', 'admin/users', $stats['roles'] . ' role');
	$mini[] = array('Login hari ini', number_format($stats['logins_today'], 0, ',', '.'), 'box-arrow-in-right', 'admin/audit?action=login&result=success', '');
	$mini[] = array('Akses ditolak / gagal', number_format($stats['denied_today'], 0, ',', '.'), 'shield-exclamation', 'admin/audit?result=denied', 'hari ini');
}
?>
<?php if ($mini): ?>
<h3 class="tw-mb-3 tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[.12em] tw-text-slate-500">Ringkasan lainnya</h3>
<div class="row g-3">
	<?php foreach ($mini as $m): ?>
	<div class="col-6 col-md-4 col-xl-2">
		<a class="card stat-card h-100 text-decoration-none" href="<?= site_url($m[3]) ?>">
			<div class="card-body">
				<div class="stat-icon tw-mb-3 tw-h-9 tw-w-9 tw-bg-slate-100 tw-text-base tw-text-slate-600"><i class="bi bi-<?= $m[2] ?>"></i></div>
				<div class="stat-label"><?= e($m[0]) ?></div>
				<div class="fw-semibold fs-5 text-truncate tw-text-slate-900"><?= $m[1] ?></div>
				<?php if ($m[4] !== ''): ?><div class="small text-muted text-truncate"><?= e($m[4]) ?></div><?php endif; ?>
			</div>
		</a>
	</div>
	<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (isset($trend)): ?>
<script src="<?= asset_v('assets/js/charts.js') ?>"></script>
<script>
(function () {
	var t = <?= json_encode($trend) ?>;
	Charts.line(document.getElementById('c-trend'), { labels: t.map(function (x) { return x[0]; }), values: t.map(function (x) { return x[1]; }), format: 'rp', name: 'Pendapatan', height: 220 });
})();
</script>
<?php endif; ?>
<script>
(function () {
	var c = document.getElementById('dash-clock');
	if (c) setInterval(function () { var d = new Date(); c.textContent = ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); }, 15000);
})();
</script>
