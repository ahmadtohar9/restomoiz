<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="d-flex mb-3">
	<p class="text-muted small mb-0 align-self-center">Kategori bisa bertingkat, mis. Makanan › Daging.</p>
	<?php if (can('inventory.create')): ?>
		<a class="btn btn-primary ms-auto" href="<?= site_url('inventory/categories/create') ?>"><i class="bi bi-plus-lg"></i> Kategori Baru</a>
	<?php endif; ?>
</div>

<div class="card">
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0">
			<thead><tr><th>Kategori</th><th>Deskripsi</th><th class="text-center">Bahan</th><th class="text-end">Aksi</th></tr></thead>
			<tbody>
			<?php if (empty($rows)): ?><tr><td colspan="4" class="text-center text-muted py-4">Belum ada kategori.</td></tr><?php endif; ?>
			<?php foreach ($rows as $c): ?>
				<tr class="<?= $c['is_active'] ? '' : 'text-muted' ?>">
					<td style="padding-left: <?= 0.75 + $c['depth'] * 1.5 ?>rem">
						<?= $c['depth'] ? '<span class="text-muted">└</span> ' : '' ?><span class="<?= $c['depth'] ? '' : 'fw-medium' ?>"><?= e($c['name']) ?></span>
						<?php if ( ! $c['is_active']): ?><span class="badge text-bg-secondary">nonaktif</span><?php endif; ?>
					</td>
					<td class="small text-muted"><?= e($c['description']) ?></td>
					<td class="text-center"><a href="<?= site_url('inventory/ingredients?status=&category_id=' . $c['id']) ?>"><?= (int) $c['ingredient_count'] ?></a></td>
					<td class="text-end text-nowrap">
						<?php if (can('inventory.create')): ?>
							<a class="btn btn-sm btn-outline-secondary" href="<?= site_url('inventory/categories/create?parent_id=' . $c['id']) ?>" title="Tambah sub-kategori"><i class="bi bi-diagram-3"></i></a>
						<?php endif; ?>
						<?php if (can('inventory.edit')): ?>
							<a class="btn btn-sm btn-outline-secondary" href="<?= site_url('inventory/categories/edit/' . $c['id']) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
						<?php endif; ?>
						<?php if (can('inventory.delete')): ?>
							<?= form_open('inventory/categories/delete/' . $c['id'], array('class' => 'd-inline', 'data-confirm' => 'Hapus kategori ' . $c['name'] . '?')) ?>
								<button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
							<?= form_close() ?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
