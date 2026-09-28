<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<form class="d-flex flex-wrap gap-2 mb-3" method="get" action="<?= site_url('sales/customers') ?>">
	<input class="form-control" style="max-width: 300px" name="q" value="<?= e($q) ?>" placeholder="Cari nama atau no. HP">
	<button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
	<a class="btn btn-primary ms-auto" href="<?= site_url('sales/customers/form') ?>"><i class="bi bi-plus-lg"></i> Pelanggan Baru</a>
</form>
<div class="card">
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0">
			<thead><tr><th>Nama</th><th>HP</th><th>Alamat</th><th class="text-center">Kunjungan</th><th class="text-end">Total belanja</th><th>Terakhir</th><th></th></tr></thead>
			<tbody>
			<?php if (empty($rows)): ?><tr><td colspan="7" class="text-center text-muted py-4">Belum ada pelanggan.</td></tr><?php endif; ?>
			<?php foreach ($rows as $c): ?>
				<tr>
					<td><?= e($c['name']) ?> <?= $c['is_member'] ? '<span class="badge text-bg-warning">member</span>' : '' ?></td>
					<td class="small"><?= e($c['phone']) ?></td>
					<td class="small"><?= e($c['address']) ?></td>
					<td class="text-center"><?= (int) $c['visits'] ?></td>
					<td class="text-end"><?= rupiah($c['spent']) ?></td>
					<td class="small"><?= tgl($c['last_visit'], FALSE) ?></td>
					<td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('sales/customers/form/' . $c['id']) ?>"><i class="bi bi-pencil"></i></a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
