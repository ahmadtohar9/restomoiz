<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="d-flex flex-wrap gap-2 mb-3">
	<input type="search" class="form-control table-filter" data-target="#users-table" placeholder="Cari nama, username, role..." style="max-width: 320px">
	<a class="btn btn-primary ms-auto" href="<?= site_url('admin/users/create') ?>"><i class="bi bi-plus-lg"></i> User Baru</a>
</div>

<div class="card">
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0" id="users-table">
			<thead>
				<tr><th>Nama</th><th>Username</th><th>Role</th><th>Status</th><th>Login terakhir</th><th class="text-end">Aksi</th></tr>
			</thead>
			<tbody>
			<?php foreach ($users as $u): ?>
				<tr>
					<td>
						<div class="fw-medium"><?= e($u['name']) ?></div>
						<div class="small text-muted"><?= e($u['email'] ?: '') ?></div>
					</td>
					<td><code><?= e($u['username']) ?></code></td>
					<td>
						<?php foreach ($u['roles'] as $r): ?>
							<span class="badge <?= $r['is_primary'] ? 'text-bg-primary' : 'text-bg-light border' ?>"><?= e($r['name']) ?></span>
						<?php endforeach; ?>
					</td>
					<td>
						<?php if ($u['is_active']): ?>
							<span class="badge text-bg-success">Aktif</span>
						<?php else: ?>
							<span class="badge text-bg-secondary">Nonaktif</span>
						<?php endif; ?>
						<?php if ($u['must_change_password']): ?>
							<span class="badge text-bg-warning" title="Wajib ganti password saat login">ganti pw</span>
						<?php endif; ?>
					</td>
					<td class="small text-muted"><?= tgl($u['last_login']) ?></td>
					<td class="text-end text-nowrap">
						<a class="btn btn-sm btn-outline-secondary" href="<?= site_url('admin/users/edit/' . $u['id']) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
						<?php if (can('admin.roles')): ?>
							<a class="btn btn-sm btn-outline-secondary" href="<?= site_url('admin/users/permissions/' . $u['id']) ?>" title="Custom permission"><i class="bi bi-key"></i></a>
						<?php endif; ?>
						<?php if ((int) $u['id'] !== (int) $current_user['id']): ?>
							<?= form_open('admin/users/delete/' . $u['id'], array('class' => 'd-inline', 'data-confirm' => 'Hapus user ' . $u['username'] . '? Tindakan ini tidak bisa dibatalkan.')) ?>
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
