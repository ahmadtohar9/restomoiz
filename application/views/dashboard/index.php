<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<p class="text-muted">Selamat datang, <strong><?= e($current_user['name']) ?></strong>.</p>

<?php if ( ! empty($stats)): ?>
<div class="row g-3 mb-4">
	<?php foreach (array(
		array('User aktif', $stats['users_active'], 'people', 'admin/users'),
		array('Role', $stats['roles'], 'person-badge', 'admin/roles'),
		array('Login hari ini', $stats['logins_today'], 'box-arrow-in-right', 'admin/audit?action=login&result=success'),
		array('Ditolak / gagal hari ini', $stats['denied_today'], 'shield-exclamation', 'admin/audit?result=denied'),
	) as $s): ?>
	<div class="col-6 col-xl-3">
		<a class="stat-card card h-100 text-decoration-none" href="<?= site_url($s[3]) ?>">
			<div class="card-body">
				<div class="stat-label"><i class="bi bi-<?= $s[2] ?>"></i> <?= e($s[0]) ?></div>
				<div class="stat-value"><?= number_format($s[1], 0, ',', '.') ?></div>
			</div>
		</a>
	</div>
	<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (isset($sales_today)): $st = $sales_today; ?>
<div class="card mb-4">
	<div class="card-header d-flex align-items-center"><span><i class="bi bi-cash-coin"></i> Penjualan hari ini</span>
		<a class="ms-auto small" href="<?= can_any(array('report.operational', 'report.financial')) ? site_url('reports') : site_url('sales/orders') ?>"><?= can_any(array('report.operational', 'report.financial')) ? 'Dashboard eksekutif' : 'Transaksi' ?></a></div>
	<div class="card-body">
		<div class="row g-3">
			<div class="col-6 col-md-3"><div class="stat-label">Pendapatan</div><div class="stat-value fs-4"><?= rupiah($st['revenue']) ?></div></div>
			<div class="col-6 col-md-3"><div class="stat-label">Transaksi</div><div class="stat-value fs-4"><?= (int) $st['tx'] ?></div></div>
			<div class="col-6 col-md-3"><div class="stat-label">Rata-rata bill</div><div class="stat-value fs-4"><?= rupiah($st['tx'] ? $st['revenue'] / $st['tx'] : 0) ?></div></div>
			<?php if (can('menu.view_cogs')): ?><div class="col-6 col-md-3"><div class="stat-label">Laba kotor (sblm pajak)</div><div class="stat-value fs-4"><?= rupiah($st['revenue'] - $st['cogs']) ?></div></div><?php endif; ?>
		</div>
		<div class="d-flex flex-wrap gap-3 mt-3 small">
			<?php if ($st['open']): ?><a href="<?= site_url('sales/orders?status=open') ?>"><span class="badge text-bg-warning"><?= (int) $st['open'] ?></span> pesanan belum dibayar</a><?php endif; ?>
			<?php if ($st['pending_shifts']): ?><a href="<?= site_url('sales/shifts') ?>"><span class="badge text-bg-danger"><?= (int) $st['pending_shifts'] ?></span> shift menunggu approval selisih kas</a><?php endif; ?>
			<?php if ($st['refunds_pending']): ?><a href="<?= site_url('sales/refunds?status=pending_approval') ?>"><span class="badge text-bg-danger"><?= (int) $st['refunds_pending'] ?></span> refund menunggu approval</a><?php endif; ?>
			<?php if ($st['refunds_to_pay']): ?><a href="<?= site_url('sales/refunds?status=approved') ?>"><span class="badge text-bg-info"><?= (int) $st['refunds_to_pay'] ?></span> refund disetujui, uang belum dikembalikan</a><?php endif; ?>
			<?php if ($st['refund_total'] > 0): ?><span class="text-muted">Refund hari ini <?= rupiah($st['refund_total']) ?></span><?php endif; ?>
			<?php if ($st['unsettled']): ?><a href="<?= site_url('finance/settlements') ?>"><span class="badge text-bg-warning"><?= (int) $st['unsettled'] ?></span> hari belum di-settle</a><?php endif; ?>
			<?php if ($top_today): ?><span class="text-muted">Terlaris: <?= e(implode(', ', array_map(function ($t) { return $t['name'] . ' (' . (int) $t['qty'] . ')'; }, $top_today))) ?></span><?php endif; ?>
		</div>
	</div>
</div>
<?php endif; ?>

<?php if (isset($stock_alerts)): ?>
<div class="card mb-4">
	<div class="card-header d-flex align-items-center">
		<span><i class="bi bi-box-seam"></i> Inventory</span>
		<a class="ms-auto small" href="<?= site_url('inventory/stock/alerts') ?>">Lihat semua peringatan</a>
	</div>
	<div class="card-body">
		<div class="row g-3">
			<div class="col-6 col-md-3">
				<div class="stat-label">Bahan aktif</div>
				<div class="stat-value"><?= number_format($stock_totals['items'], 0, ',', '.') ?></div>
			</div>
			<?php if (can('inventory.view_cost')): ?>
			<div class="col-6 col-md-3">
				<div class="stat-label">Nilai stok</div>
				<div class="stat-value fs-4"><?= rupiah($stock_totals['value']) ?></div>
			</div>
			<?php endif; ?>
			<div class="col-12 col-md-6">
				<?php $labels = array(
					'low'      => array('Di bawah minimum', 'danger', 'inventory/ingredients?stock=low'),
					'reorder'  => array('Perlu reorder', 'warning', 'inventory/ingredients?stock=reorder'),
					'expiring' => array('Mendekati / lewat kedaluwarsa', 'danger', 'inventory/stock/alerts#expiring'),
					'dead'     => array('Dead stock', 'secondary', 'inventory/stock/alerts#dead'),
					'over'     => array('Di atas maksimum', 'info', 'inventory/ingredients?stock=over'),
				); ?>
				<?php $any = FALSE; foreach ($labels as $k => $l): if (empty($stock_alerts[$k])) continue; $any = TRUE; ?>
					<a class="d-flex align-items-center text-decoration-none py-1" href="<?= site_url($l[2]) ?>">
						<i class="bi bi-exclamation-triangle-fill text-<?= $l[1] ?> me-2"></i>
						<span class="text-body"><?= e($l[0]) ?></span>
						<span class="badge text-bg-<?= $l[1] ?> ms-auto"><?= (int) $stock_alerts[$k] ?></span>
					</a>
				<?php endforeach; ?>
				<?php if ( ! $any): ?>
					<div class="text-success"><i class="bi bi-check-circle"></i> Tidak ada peringatan stok.</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
<?php endif; ?>

<?php if (isset($purchase)):
	$tasks = array(
		array('PO menunggu approval Manajer', $purchase['approval_1'], 'purchase/orders?status=submitted', 'purchase.approve', 'warning'),
		array('PO menunggu approval Owner', $purchase['approval_2'], 'purchase/orders?status=submitted', 'purchase.approve_owner', 'warning'),
		array('PO menunggu barang datang', $purchase['to_receive'], 'purchase/receipts', 'purchase.receive', 'primary'),
		array('PO terlambat dari tanggal kirim', $purchase['late'], 'purchase/receipts', 'purchase.view', 'danger'),
		array('Invoice perlu direview', $purchase['invoices'], 'purchase/invoices?status=pending', 'purchase.invoice', 'warning'),
		array('Pembayaran perlu diverifikasi', $purchase['payments'], 'purchase/payments?status=pending', 'purchase.payment', 'warning'),
		array('Invoice lewat jatuh tempo', $purchase['overdue'], 'purchase/invoices?payment=outstanding', 'purchase.view', 'danger'),
	);
	$tasks = array_filter($tasks, function ($t) { return $t[1] > 0 && can($t[3]); });
?>
<div class="card mb-4">
	<div class="card-header d-flex align-items-center">
		<span><i class="bi bi-cart3"></i> Pembelian</span>
		<?php if ($purchase['outstanding'] > 0): ?><span class="ms-auto small fw-normal">Hutang supplier: <strong><?= rupiah($purchase['outstanding']) ?></strong></span><?php endif; ?>
	</div>
	<div class="card-body">
		<?php if ( ! $tasks): ?>
			<div class="text-success"><i class="bi bi-check-circle"></i> Tidak ada tugas pembelian yang menunggu.</div>
		<?php endif; ?>
		<?php foreach ($tasks as $t): ?>
			<a class="d-flex align-items-center text-decoration-none py-1" href="<?= site_url($t[2]) ?>">
				<i class="bi bi-dot text-<?= $t[4] ?> fs-4 lh-1"></i>
				<span class="text-body"><?= e($t[0]) ?></span>
				<span class="badge text-bg-<?= $t[4] ?> ms-auto"><?= (int) $t[1] ?></span>
			</a>
		<?php endforeach; ?>
	</div>
</div>
<?php endif; ?>

<div class="card">
	<div class="card-header">Progres pengembangan (PRD bagian 5)</div>
	<ul class="list-group list-group-flush">
		<?php foreach (array(
			array('Fase 1', 'Fondasi & RBAC: login, user, role, permission, audit log', TRUE),
			array('Fase 2', 'Inventory: bahan baku, supplier, stok FIFO, opname, peringatan, laporan', TRUE),
			array('Fase 3', 'Pembelian: PO dengan approval, penerimaan barang, invoice (3-way match), pembayaran supplier', TRUE),
			array('Fase 4', 'Menu: varian, resep & COGS, harga terjadwal, promo, barcode & menu online', TRUE),
			array('Fase 5', 'POS & order: dine-in, takeaway, delivery, dapur, shift kasir', TRUE),
			array('Fase 6', 'Refund dengan approval, settlement harian, rekonsiliasi bank', TRUE),
			array('Fase 7', 'Laporan: dashboard eksekutif, laba rugi, penjualan, kasir, refund, pengeluaran', TRUE),
		) as $f): ?>
		<li class="list-group-item d-flex align-items-center gap-3">
			<i class="bi <?= $f[2] ? 'bi-check-circle-fill text-success' : 'bi-circle text-secondary' ?>"></i>
			<div><strong><?= e($f[0]) ?></strong> <span class="text-muted">— <?= e($f[1]) ?></span></div>
		</li>
		<?php endforeach; ?>
	</ul>
</div>
