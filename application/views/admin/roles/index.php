<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="d-flex flex-wrap gap-2 mb-3">
	<a class="btn btn-outline-secondary" href="<?= site_url('admin/roles/matrix') ?>"><i class="bi bi-grid-3x3"></i> Permission Matrix</a>
	<a class="btn btn-primary ms-auto" href="<?= site_url('admin/roles/create') ?>"><i class="bi bi-plus-lg"></i> Role Baru</a>
</div>

<div class="card">
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0">
			<thead><tr><th>Role</th><th>Deskripsi</th><th class="text-center">User</th><th class="text-end">Aksi</th></tr></thead>
			<tbody>
			<?php foreach ($roles as $r): ?>
				<tr>
					<td class="fw-medium"><?= e($r['name']) ?>
						<?php if ($r['is_super']): ?><span class="badge text-bg-warning">semua akses</span><?php endif; ?>
						<?php if ($r['is_system']): ?><span class="badge text-bg-light border">default</span><?php endif; ?>
					</td>
					<td class="text-muted"><?= e($r['description']) ?></td>
					<td class="text-center"><?= (int) $r['user_count'] ?></td>
					<td class="text-end text-nowrap">
						<a class="btn btn-sm btn-outline-secondary" href="<?= site_url('admin/roles/edit/' . $r['id']) ?>" title="Edit & permission"><i class="bi bi-pencil"></i></a>
						<?php if ( ! $r['is_super']): ?>
							<?= form_open('admin/roles/clone_role/' . $r['id'], array('class' => 'd-inline')) ?>
								<button class="btn btn-sm btn-outline-secondary" title="Clone role"><i class="bi bi-copy"></i></button>
							<?= form_close() ?>
							<?php if ((int) $r['user_count'] === 0): ?>
								<?= form_open('admin/roles/delete/' . $r['id'], array('class' => 'd-inline', 'data-confirm' => 'Hapus role ' . $r['name'] . '?')) ?>
									<button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
								<?= form_close() ?>
							<?php endif; ?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
