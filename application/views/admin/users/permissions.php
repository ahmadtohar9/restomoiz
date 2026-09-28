<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<p><a href="<?= site_url('admin/users') ?>"><i class="bi bi-arrow-left"></i> Kembali ke daftar user</a></p>

<div class="row g-3">
	<div class="col-lg-5">
		<div class="card">
			<div class="card-header">Beri permission tambahan</div>
			<div class="card-body">
				<p class="small text-muted">Untuk kebutuhan khusus, mis. kasir menggantikan manajer yang cuti. Gunakan tanggal kedaluwarsa supaya akses otomatis berakhir.</p>
				<?= form_open('admin/users/permissions/' . $user['id']) ?>
					<div class="mb-3">
						<label class="form-label" for="permission_id">Permission</label>
						<select class="form-select" id="permission_id" name="permission_id" required>
							<option value="">— pilih —</option>
							<?php foreach ($groups as $g): ?>
								<optgroup label="<?= e($g['label']) ?>">
									<?php foreach ($g['permissions'] as $p): ?>
										<option value="<?= $p['id'] ?>"><?= e($p['name'] . ' — ' . $p['description']) ?></option>
									<?php endforeach; ?>
								</optgroup>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="mb-3">
						<label class="form-label" for="expiry_date">Berlaku sampai <span class="text-muted small">(kosong = permanen)</span></label>
						<input class="form-control" type="date" id="expiry_date" name="expiry_date" min="<?= date('Y-m-d') ?>">
					</div>
					<div class="mb-3">
						<label class="form-label" for="reason">Alasan</label>
						<input class="form-control" id="reason" name="reason" maxlength="255" required placeholder="mis. Menggantikan manajer selama cuti">
					</div>
					<button class="btn btn-primary" type="submit">Berikan</button>
				<?= form_close() ?>
			</div>
		</div>
	</div>

	<div class="col-lg-7">
		<div class="card">
			<div class="card-header">Custom permission aktif — <?= e($user['name']) ?> (<code><?= e($user['username']) ?></code>)</div>
			<?php if (empty($custom)): ?>
				<div class="card-body text-muted">Belum ada custom permission. Hak akses user ini murni dari role-nya.</div>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table align-middle mb-0">
						<thead><tr><th>Permission</th><th>Berlaku s/d</th><th>Alasan</th><th></th></tr></thead>
						<tbody>
						<?php foreach ($custom as $c): $expired = $c['expiry_date'] && $c['expiry_date'] < date('Y-m-d'); ?>
							<tr class="<?= $expired ? 'text-muted' : '' ?>">
								<td><code><?= e($c['permission_name']) ?></code></td>
								<td><?= $c['expiry_date'] ? tgl($c['expiry_date'], FALSE) : 'Permanen' ?>
									<?= $expired ? '<span class="badge text-bg-secondary">kedaluwarsa</span>' : '' ?></td>
								<td class="small"><?= e($c['reason']) ?><div class="text-muted">oleh <?= e($c['granted_by_name'] ?: '-') ?>, <?= tgl($c['created_at']) ?></div></td>
								<td class="text-end">
									<?= form_open('admin/users/revoke/' . $user['id'] . '/' . $c['id'], array('data-confirm' => 'Cabut permission ' . $c['permission_name'] . '?')) ?>
										<button class="btn btn-sm btn-outline-danger">Cabut</button>
									<?= form_close() ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
