<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?><!doctype html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title>Login · Resto Moiz</title>
	<link rel="stylesheet" href="<?= base_url('assets/vendor/inter/inter.css') ?>">
	<link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/bootstrap.min.css') ?>">
	<link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
	<link rel="stylesheet" href="<?= asset_v('assets/css/app.css') ?>">
</head>
<body class="login-page">
	<div class="tw-w-full tw-max-w-[420px]">
	<div class="login-card card">
		<div class="card-body p-4 p-sm-5">
			<div class="text-center mb-4">
				<div class="login-logo"><i class="bi bi-shop"></i></div>
				<h1 class="h4 mb-1 fw-semibold">Selamat datang</h1>
				<p class="text-muted small mb-0">Masuk ke <strong class="tw-text-slate-700">Resto Moiz</strong> · Sistem Manajemen Restoran</p>
			</div>

			<?php if ($error): ?>
				<div class="alert alert-danger py-2 small"><?= e($error) ?></div>
			<?php endif; ?>

			<?= form_open('login') ?>
				<input type="hidden" name="next" value="<?= e($next) ?>">
				<div class="mb-3">
					<label class="form-label" for="username">Username</label>
					<div class="input-group"><span class="input-group-text tw-bg-slate-50 tw-text-slate-400"><i class="bi bi-person"></i></span><input class="form-control" id="username" name="username" value="<?= e($username) ?>" autocomplete="username" required autofocus></div>
				</div>
				<div class="mb-4">
					<label class="form-label" for="password">Password</label>
					<div class="input-group"><span class="input-group-text tw-bg-slate-50 tw-text-slate-400"><i class="bi bi-lock"></i></span><input class="form-control" id="password" type="password" name="password" autocomplete="current-password" required>
						<button class="btn btn-outline-secondary tw-border-slate-300" type="button" id="pw-toggle" aria-label="Tampilkan password" tabindex="-1"><i class="bi bi-eye"></i></button></div>
				</div>
				<button class="btn btn-primary btn-lg w-100 tw-text-base" type="submit">Masuk <i class="bi bi-arrow-right"></i></button>
			<?= form_close() ?>
		</div>
	</div>
	<p class="tw-mt-6 tw-text-center tw-text-xs tw-text-slate-400">&copy; <?= date('Y') ?> Resto Moiz</p>
	</div>
	<script>
	document.getElementById('pw-toggle').addEventListener('click', function () {
		var i = document.getElementById('password'), show = i.type === 'password';
		i.type = show ? 'text' : 'password';
		this.innerHTML = show ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
	});
	</script>
</body>
</html>
