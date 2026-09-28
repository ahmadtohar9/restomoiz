<?php defined('BASEPATH') OR exit('No direct script access allowed');
$flash = $this->session->flashdata('flash');
$nav = array(
	array('label' => 'Dashboard', 'icon' => 'speedometer2', 'url' => 'dashboard', 'perm' => NULL),
);
$admin_nav = array(
	array('label' => 'User', 'icon' => 'people', 'url' => 'admin/users', 'perm' => 'admin.users'),
	array('label' => 'Role', 'icon' => 'person-badge', 'url' => 'admin/roles', 'perm' => 'admin.roles'),
	array('label' => 'Permission Matrix', 'icon' => 'grid-3x3', 'url' => 'admin/roles/matrix', 'perm' => 'admin.roles'),
	array('label' => 'Audit Log', 'icon' => 'journal-text', 'url' => 'admin/audit', 'perm' => 'admin.audit'),
	array('label' => 'Pengaturan', 'icon' => 'gear', 'url' => 'admin/settings', 'perm' => 'admin.settings'),
);
$uri = uri_string();
$active = function ($url) use ($uri) {
	if ($url === 'admin/roles')
	{
		return ($uri === 'admin/roles' OR preg_match('#^admin/roles/(create|edit)#', $uri)) ? 'active' : '';
	}
	return is_active_nav($url);
};
?><!doctype html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?= e(isset($title) ? $title . ' · ' : '') ?>Resto Moiz</title>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
	<link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body>
<div class="app">
	<aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar">
		<div class="sidebar-brand">
			<i class="bi bi-shop"></i> <span>Resto Moiz</span>
			<button type="button" class="btn-close btn-close-white d-lg-none ms-auto" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Tutup"></button>
		</div>
		<nav class="sidebar-nav">
			<?php foreach ($nav as $item): ?>
				<a class="nav-link <?= $active($item['url']) ?>" href="<?= site_url($item['url']) ?>"><i class="bi bi-<?= $item['icon'] ?>"></i> <?= e($item['label']) ?></a>
			<?php endforeach; ?>

			<div class="nav-section">Operasional</div>
			<?php foreach (array('Inventory' => 'box-seam', 'Pembelian' => 'cart3', 'Menu' => 'journal-richtext', 'POS & Penjualan' => 'cash-coin', 'Laporan' => 'bar-chart') as $label => $icon): ?>
				<span class="nav-link disabled" title="Dikerjakan di fase berikutnya"><i class="bi bi-<?= $icon ?>"></i> <?= e($label) ?> <small class="badge text-bg-secondary ms-auto">segera</small></span>
			<?php endforeach; ?>

			<?php $visible_admin = array_filter($admin_nav, function ($i) { return can($i['perm']); }); ?>
			<?php if ($visible_admin): ?>
				<div class="nav-section">Administrasi</div>
				<?php foreach ($visible_admin as $item): ?>
					<a class="nav-link <?= $active($item['url']) ?>" href="<?= site_url($item['url']) ?>"><i class="bi bi-<?= $item['icon'] ?>"></i> <?= e($item['label']) ?></a>
				<?php endforeach; ?>
			<?php endif; ?>
		</nav>
	</aside>

	<div class="main">
		<header class="topbar">
			<button class="btn btn-outline-secondary btn-sm d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="Menu"><i class="bi bi-list"></i></button>
			<h1 class="page-title"><?= e(isset($title) ? $title : '') ?></h1>
			<div class="dropdown ms-auto">
				<button class="btn btn-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">
					<i class="bi bi-person-circle"></i>
					<span class="d-none d-sm-inline"><?= e($current_user['name']) ?></span>
				</button>
				<ul class="dropdown-menu dropdown-menu-end">
					<li><span class="dropdown-item-text small text-muted"><?= e(implode(', ', $current_user['role_names'])) ?: 'Tanpa role' ?></span></li>
					<li><hr class="dropdown-divider"></li>
					<li><a class="dropdown-item" href="<?= site_url('profile') ?>"><i class="bi bi-person"></i> Profil</a></li>
					<li><a class="dropdown-item" href="<?= site_url('profile/password') ?>"><i class="bi bi-key"></i> Ganti Password</a></li>
					<li><a class="dropdown-item text-danger" href="<?= site_url('logout') ?>"><i class="bi bi-box-arrow-right"></i> Keluar</a></li>
				</ul>
			</div>
		</header>

		<main class="content">
			<?php if ($flash): ?>
				<div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show">
					<?= e($flash['message']) ?>
					<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
				</div>
			<?php endif; ?>
			<?php if ( ! empty($errors)): ?>
				<div class="alert alert-danger">
					<ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
				</div>
			<?php endif; ?>

			<?php $this->load->view($content_view); ?>
		</main>
	</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
</body>
</html>
