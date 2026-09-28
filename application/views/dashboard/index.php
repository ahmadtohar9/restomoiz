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

<div class="card">
	<div class="card-header">Progres pengembangan (PRD bagian 5)</div>
	<ul class="list-group list-group-flush">
		<?php foreach (array(
			array('Fase 1', 'Fondasi & RBAC: login, user, role, permission, audit log', TRUE),
			array('Fase 2', 'Inventory: bahan baku, supplier, stok FIFO, opname, peringatan, laporan', TRUE),
			array('Fase 3', 'Pembelian: PO, penerimaan barang, pembayaran supplier', FALSE),
			array('Fase 4', 'Menu: resep, COGS, barcode', FALSE),
			array('Fase 5', 'POS & order: dine-in, takeaway, delivery, shift', FALSE),
			array('Fase 6', 'Refund & settlement', FALSE),
			array('Fase 7', 'Laporan: P&L, pendapatan, dashboard analitik', FALSE),
		) as $f): ?>
		<li class="list-group-item d-flex align-items-center gap-3">
			<i class="bi <?= $f[2] ? 'bi-check-circle-fill text-success' : 'bi-circle text-secondary' ?>"></i>
			<div><strong><?= e($f[0]) ?></strong> <span class="text-muted">— <?= e($f[1]) ?></span></div>
		</li>
		<?php endforeach; ?>
	</ul>
</div>
