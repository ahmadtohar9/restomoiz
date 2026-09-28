<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="card" style="max-width: 480px">
	<div class="card-body">
		<?php if ($forced): ?>
			<div class="alert alert-warning small">Demi keamanan, Anda wajib mengganti password sebelum melanjutkan.</div>
		<?php endif; ?>
		<?= form_open('profile/password') ?>
			<div class="mb-3">
				<label class="form-label" for="current_password">Password saat ini</label>
				<input class="form-control" type="password" id="current_password" name="current_password" autocomplete="current-password" required>
			</div>
			<div class="mb-3">
				<label class="form-label" for="new_password">Password baru</label>
				<input class="form-control" type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="8" required>
				<div class="form-text">Minimal 8 karakter, kombinasi huruf dan angka.</div>
			</div>
			<div class="mb-4">
				<label class="form-label" for="confirm_password">Ulangi password baru</label>
				<input class="form-control" type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
			</div>
			<button class="btn btn-primary" type="submit">Simpan Password</button>
			<?php if ($forced): ?>
				<a class="btn btn-link text-danger" href="<?= site_url('logout') ?>">Keluar</a>
			<?php endif; ?>
		<?= form_close() ?>
	</div>
</div>
