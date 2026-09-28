<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="d-flex mb-3">
	<p class="text-muted small mb-0 align-self-center">Urutan kategori mengikuti kolom "urutan" (angka kecil tampil lebih dulu di POS & menu online).</p>
	<?php if (can('menu.create')): ?><a class="btn btn-primary ms-auto" href="<?= site_url('menu/categories/create') ?>"><i class="bi bi-plus-lg"></i> Kategori Baru</a><?php endif; ?>
</div>
<div class="card">
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0">
			<thead><tr><th>Kategori</th><th class="text-center">Urutan</th><th class="text-center">Menu</th><th class="text-end">Aksi</th></tr></thead>
			<tbody>
			<?php if (empty($rows)): ?><tr><td colspan="4" class="text-center text-muted py-4">Belum ada kategori menu.</td></tr><?php endif; ?>
			<?php foreach ($rows as $c): ?>
				<tr class="<?= $c['is_active'] ? '' : 'text-muted' ?>">
					<td style="padding-left: <?= 0.75 + $c['depth'] * 1.5 ?>rem">
						<div class="d-flex align-items-center gap-2">
							<?= $c['depth'] ? '<span class="text-muted">└</span>' : '' ?>
							<?php if ($c['image_path']): ?><img src="<?= base_url($c['image_path']) ?>" alt="" class="rounded" style="width:28px;height:28px;object-fit:cover"><?php endif; ?>
							<span class="<?= $c['depth'] ? '' : 'fw-medium' ?>"><?= e($c['name']) ?></span>
							<?php if ($c['is_hidden']): ?><span class="badge text-bg-warning" title="Tidak tampil ke pelanggan">internal</span><?php endif; ?>
							<?php if ( ! $c['is_active']): ?><span class="badge text-bg-secondary">nonaktif</span><?php endif; ?>
						</div>
						<?php if ($c['description']): ?><div class="small text-muted"><?= e($c['description']) ?></div><?php endif; ?>
					</td>
					<td class="text-center small"><?= (int) $c['sort_order'] ?></td>
					<td class="text-center"><a href="<?= site_url('menu/items?category_id=' . $c['id']) ?>"><?= (int) $c['ingredient_count'] ?></a></td>
					<td class="text-end text-nowrap">
						<?php if (can('menu.create')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('menu/categories/create?parent_id=' . $c['id']) ?>" title="Sub-kategori"><i class="bi bi-diagram-3"></i></a><?php endif; ?>
						<?php if (can('menu.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= site_url('menu/categories/edit/' . $c['id']) ?>" title="Edit"><i class="bi bi-pencil"></i></a><?php endif; ?>
						<?php if (can('menu.delete')): ?>
							<?= form_open('menu/categories/delete/' . $c['id'], array('class' => 'd-inline', 'data-confirm' => 'Hapus kategori ' . $c['name'] . '?')) ?><button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button><?= form_close() ?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
