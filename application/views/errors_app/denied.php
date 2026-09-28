<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="card border-danger-subtle">
	<div class="card-body text-center py-5">
		<i class="bi bi-shield-lock display-4 text-danger"></i>
		<h2 class="h4 mt-3">Anda tidak memiliki akses</h2>
		<p class="text-muted mb-1">Halaman ini membutuhkan permission <code><?= e($permission) ?></code>.</p>
		<p class="text-muted small">Percobaan akses ini tercatat di audit log. Hubungi Admin jika Anda memerlukan akses.</p>
		<a class="btn btn-outline-secondary" href="<?= site_url('dashboard') ?>">Kembali ke Dashboard</a>
	</div>
</div>
