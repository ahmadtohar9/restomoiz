<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?><!doctype html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title>Login · Resto Moiz</title>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
	<link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body class="login-page">
	<div class="login-card card shadow-sm">
		<div class="card-body p-4 p-sm-5">
			<div class="text-center mb-4">
				<div class="login-logo"><i class="bi bi-shop"></i></div>
				<h1 class="h4 mb-1">Resto Moiz</h1>
				<p class="text-muted small mb-0">Sistem Manajemen Restoran</p>
			</div>

			<?php if ($error): ?>
				<div class="alert alert-danger py-2 small"><?= e($error) ?></div>
			<?php endif; ?>

			<?= form_open('login') ?>
				<input type="hidden" name="next" value="<?= e($next) ?>">
				<div class="mb-3">
					<label class="form-label" for="username">Username</label>
					<input class="form-control" id="username" name="username" value="<?= e($username) ?>" autocomplete="username" required autofocus>
				</div>
				<div class="mb-4">
					<label class="form-label" for="password">Password</label>
					<input class="form-control" id="password" type="password" name="password" autocomplete="current-password" required>
				</div>
				<button class="btn btn-primary w-100" type="submit">Masuk</button>
			<?= form_close() ?>
		</div>
	</div>
</body>
</html>
