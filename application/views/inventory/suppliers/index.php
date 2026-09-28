<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<form class="d-flex flex-wrap gap-2 mb-3" method="get" action="<?= site_url('inventory/suppliers') ?>">
	<input class="form-control" style="max-width: 280px" name="q" value="<?= e($filters['q']) ?>" placeholder="Cari nama, kode, kota, kontak">
	<select class="form-select" style="max-width: 160px" name="active">
		<option value="1" <?= $filters['active'] === '1' ? 'selected' : '' ?>>Aktif</option>
		<option value="0" <?= $filters['active'] === '0' ? 'selected' : '' ?>>Nonaktif</option>
		<option value="" <?= $filters['active'] === '' ? 'selected' : '' ?>>Semua</option>
	</select>
	<button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
	<?php if (can('inventory.supplier')): ?>
		<a class="btn btn-primary ms-auto" href="<?= site_url('inventory/suppliers/create') ?>"><i class="bi bi-plus-lg"></i> Supplier Baru</a>
	<?php endif; ?>
</form>

<div class="card">
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0">
			<thead><tr><th>Supplier</th><th>Kontak</th><th>Kota</th><th>Termin</th><th class="text-center">Lead time</th><th class="text-center">Kualitas</th><th class="text-center">Bahan</th></tr></thead>
			<tbody>
			<?php if (empty($rows)): ?><tr><td colspan="7" class="text-center text-muted py-4">Belum ada supplier.</td></tr><?php endif; ?>
			<?php foreach ($rows as $s): ?>
				<tr class="<?= $s['is_active'] ? '' : 'text-muted' ?>">
					<td><a class="fw-medium" href="<?= site_url('inventory/suppliers/show/' . $s['id']) ?>"><?= e($s['name']) ?></a>
						<div class="small text-muted"><code><?= e($s['code']) ?></code> <?= $s['is_active'] ? '' : '<span class="badge text-bg-secondary">nonaktif</span>' ?></div></td>
					<td class="small"><?= e($s['contact_person'] ?: '-') ?><div class="text-muted"><?= e($s['phone']) ?></div></td>
					<td class="small"><?= e($s['city'] ?: '-') ?></td>
					<td class="small"><?= e($s['payment_terms']) ?></td>
					<td class="text-center small"><?= (int) $s['lead_time_days'] ?> hari</td>
					<td class="text-center small"><?= $s['quality_score'] ? str_repeat('★', (int) $s['quality_score']) . '<span class="text-muted">' . str_repeat('★', 5 - (int) $s['quality_score']) . '</span>' : '-' ?></td>
					<td class="text-center"><?= (int) $s['ingredient_count'] ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
