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

<div class="card">
	<div class="card-header">Progres pengembangan (PRD bagian 5)</div>
	<ul class="list-group list-group-flush">
		<?php foreach (array(
			array('Fase 1', 'Fondasi & RBAC: login, user, role, permission, audit log', TRUE),
			array('Fase 2', 'Inventory: bahan baku, supplier, stok', FALSE),
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
