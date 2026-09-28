<?php defined('BASEPATH') OR exit('No direct script access allowed');
$is_edit = (bool) $user;
?>
<?= form_open($is_edit ? 'admin/users/edit/' . $user['id'] : 'admin/users/create', array('autocomplete' => 'off')) ?>
<div class="row g-3">
	<div class="col-lg-6">
		<div class="card h-100">
			<div class="card-header">Data user</div>
			<div class="card-body">
				<div class="mb-3">
					<label class="form-label" for="name">Nama lengkap</label>
					<input class="form-control" id="name" name="name" value="<?= e($input['name']) ?>" required maxlength="100">
				</div>
				<div class="mb-3">
					<label class="form-label" for="username">Username</label>
					<input class="form-control" id="username" name="username" value="<?= e($input['username']) ?>" required pattern="[a-zA-Z0-9._\-]{3,50}">
				</div>
				<div class="mb-3">
					<label class="form-label" for="email">Email <span class="text-muted small">(opsional)</span></label>
					<input class="form-control" type="email" id="email" name="email" value="<?= e($input['email']) ?>">
				</div>
				<div class="row g-2">
					<div class="col-sm-6">
						<label class="form-label" for="password">Password <?= $is_edit ? '<span class="text-muted small">(kosongkan jika tetap)</span>' : '' ?></label>
						<input class="form-control" type="password" id="password" name="password" autocomplete="new-password" <?= $is_edit ? '' : 'required' ?> minlength="8">
					</div>
					<div class="col-sm-6">
						<label class="form-label" for="password_confirm">Ulangi password</label>
						<input class="form-control" type="password" id="password_confirm" name="password_confirm" autocomplete="new-password">
					</div>
				</div>
				<div class="form-text mb-3">Minimal 8 karakter, kombinasi huruf dan angka.</div>
				<div class="form-check">
					<input class="form-check-input" type="checkbox" id="must_change_password" name="must_change_password" value="1" <?= $input['must_change_password'] ? 'checked' : '' ?>>
					<label class="form-check-label" for="must_change_password">Wajib ganti password saat login berikutnya <?= $is_edit ? '(jika password diisi)' : '' ?></label>
				</div>
				<div class="form-check">
					<input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= $input['is_active'] ? 'checked' : '' ?>>
					<label class="form-check-label" for="is_active">Akun aktif</label>
				</div>
			</div>
		</div>
	</div>

	<div class="col-lg-6">
		<div class="card h-100">
			<div class="card-header">Role</div>
			<div class="card-body">
				<div class="mb-3">
					<label class="form-label" for="primary_role">Role utama</label>
					<select class="form-select" id="primary_role" name="primary_role" required>
						<option value="">— pilih role —</option>
						<?php foreach ($roles as $r): ?>
							<option value="<?= $r['id'] ?>" <?= (int) $input['primary_role'] === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<label class="form-label">Role tambahan <span class="text-muted small">(opsional, untuk tugas lintas fungsi)</span></label>
				<?php foreach ($roles as $r): ?>
					<div class="form-check">
						<input class="form-check-input" type="checkbox" name="secondary_roles[]" id="role_<?= $r['id'] ?>" value="<?= $r['id'] ?>"
							<?= in_array((int) $r['id'], $input['secondary_roles'], TRUE) ? 'checked' : '' ?>>
						<label class="form-check-label" for="role_<?= $r['id'] ?>"><?= e($r['name']) ?>
							<span class="text-muted small">— <?= e($r['description']) ?></span></label>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</div>

<div class="mt-3 d-flex gap-2">
	<button class="btn btn-primary" type="submit">Simpan</button>
	<a class="btn btn-outline-secondary" href="<?= site_url('admin/users') ?>">Batal</a>
</div>
<?= form_close() ?>
