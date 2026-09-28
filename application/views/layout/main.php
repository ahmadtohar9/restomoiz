<?php defined('BASEPATH') OR exit('No direct script access allowed');
$flash = $this->session->flashdata('flash');
$nav = array(
	array('label' => 'Dashboard', 'icon' => 'speedometer2', 'url' => 'dashboard', 'perm' => NULL),
);
$inventory_nav = array(
	array('label' => 'Bahan Baku', 'icon' => 'box-seam', 'url' => 'inventory/ingredients', 'perm' => NULL),
	array('label' => 'Stok Masuk', 'icon' => 'box-arrow-in-down', 'url' => 'inventory/stock/in', 'perm' => 'inventory.adjust'),
	array('label' => 'Stok Keluar', 'icon' => 'box-arrow-up', 'url' => 'inventory/stock/out', 'perm' => 'inventory.adjust'),
	array('label' => 'Stock Opname', 'icon' => 'clipboard-check', 'url' => 'inventory/opname', 'perm' => NULL),
	array('label' => 'Riwayat Stok', 'icon' => 'clock-history', 'url' => 'inventory/stock/movements', 'perm' => NULL),
	array('label' => 'Peringatan Stok', 'icon' => 'exclamation-triangle', 'url' => 'inventory/stock/alerts', 'perm' => NULL),
	array('label' => 'Supplier', 'icon' => 'truck', 'url' => 'inventory/suppliers', 'perm' => NULL),
	array('label' => 'Kategori Bahan', 'icon' => 'diagram-3', 'url' => 'inventory/categories', 'perm' => NULL),
	array('label' => 'Laporan Inventory', 'icon' => 'bar-chart-line', 'url' => 'inventory/reports', 'perm' => 'report.inventory'),
);
$purchase_nav = array(
	array('label' => 'Purchase Order', 'icon' => 'cart3', 'url' => 'purchase/orders', 'perm' => NULL),
	array('label' => 'Penerimaan Barang', 'icon' => 'truck', 'url' => 'purchase/receipts', 'perm' => NULL),
	array('label' => 'Invoice Supplier', 'icon' => 'receipt', 'url' => 'purchase/invoices', 'perm' => NULL),
	array('label' => 'Pembayaran', 'icon' => 'credit-card', 'url' => 'purchase/payments', 'perm' => NULL),
	array('label' => 'Laporan Pembelian', 'icon' => 'graph-up', 'url' => 'purchase/reports', 'perm' => 'report.inventory'),
);
$menu_nav = array(
	array('label' => 'Daftar Menu', 'icon' => 'journal-richtext', 'url' => 'menu/items', 'perm' => NULL),
	array('label' => 'Kategori Menu', 'icon' => 'diagram-3', 'url' => 'menu/categories', 'perm' => NULL),
	array('label' => 'Promo', 'icon' => 'percent', 'url' => 'menu/promos', 'perm' => 'menu.view'),
	array('label' => 'Simulator Promo', 'icon' => 'calculator', 'url' => 'menu/promos/simulator', 'perm' => 'menu.view'),
	array('label' => 'Tambahan & Topping', 'icon' => 'plus-circle', 'url' => 'menu/modifiers', 'perm' => 'menu.view'),
	array('label' => 'Barcode & Label', 'icon' => 'upc-scan', 'url' => 'menu/labels', 'perm' => 'menu.view'),
	array('label' => 'Analisis Margin', 'icon' => 'graph-up-arrow', 'url' => 'menu/analysis', 'perm' => 'menu.view_cogs'),
);
$sales_nav = array(
	array('label' => 'POS Kasir', 'icon' => 'cash-coin', 'url' => 'pos', 'any' => array('sales.process', 'sales.order')),
	array('label' => 'Dapur', 'icon' => 'fire', 'url' => 'sales/kitchen', 'any' => array('sales.kitchen', 'sales.order', 'sales.process')),
	array('label' => 'Transaksi', 'icon' => 'receipt', 'url' => 'sales/orders', 'any' => array('sales.process', 'sales.order', 'sales.edit_order', 'report.operational')),
	array('label' => 'Meja', 'icon' => 'grid-3x3-gap', 'url' => 'sales/tables', 'any' => array('sales.process', 'sales.order', 'sales.edit_order')),
	array('label' => 'Refund', 'icon' => 'arrow-counterclockwise', 'url' => 'sales/refunds', 'any' => array('sales.refund', 'sales.refund_approve', 'sales.refund_owner', 'report.operational', 'report.financial')),
	array('label' => 'Shift Kasir', 'icon' => 'clock-history', 'url' => 'sales/shifts', 'any' => array('sales.shift', 'sales.shift_approve', 'report.operational', 'report.own_shift')),
	array('label' => 'Pelanggan', 'icon' => 'person-vcard', 'url' => 'sales/customers', 'any' => array('sales.process', 'sales.order', 'sales.edit_order')),
);
$admin_nav = array(
	array('label' => 'User', 'icon' => 'people', 'url' => 'admin/users', 'perm' => 'admin.users'),
	array('label' => 'Role', 'icon' => 'person-badge', 'url' => 'admin/roles', 'perm' => 'admin.roles'),
	array('label' => 'Permission Matrix', 'icon' => 'grid-3x3', 'url' => 'admin/roles/matrix', 'perm' => 'admin.roles'),
	array('label' => 'Audit Log', 'icon' => 'journal-text', 'url' => 'admin/audit', 'perm' => 'admin.audit'),
	array('label' => 'Pengaturan', 'icon' => 'gear', 'url' => 'admin/settings', 'perm' => 'admin.settings'),
	array('label' => 'Go-Live Checklist', 'icon' => 'rocket-takeoff', 'url' => 'admin/golive', 'perm' => 'admin.settings'),
);
$finance_nav = array(
	array('label' => 'Settlement Harian', 'icon' => 'journal-check', 'url' => 'finance/settlements', 'perm' => 'payment.reconcile'),
	array('label' => 'Rekonsiliasi Bank', 'icon' => 'bank', 'url' => 'finance/reconciliations', 'perm' => 'payment.reconcile'),
	array('label' => 'Pengeluaran', 'icon' => 'wallet2', 'url' => 'finance/expenses', 'perm' => 'finance.expense'),
);
$report_nav = array(
	array('label' => 'Dashboard Eksekutif', 'icon' => 'speedometer', 'url' => 'reports', 'perm' => NULL, 'match' => '#^reports$#'),
	array('label' => 'Penjualan', 'icon' => 'bar-chart', 'url' => 'reports/sales', 'perm' => NULL),
	array('label' => 'Laba Rugi', 'icon' => 'file-earmark-bar-graph', 'url' => 'reports/pnl', 'perm' => 'report.financial'),
	array('label' => 'Kinerja Kasir', 'icon' => 'person-badge', 'url' => 'reports/cashiers', 'perm' => NULL),
	array('label' => 'Analisis Refund', 'icon' => 'arrow-counterclockwise', 'url' => 'reports/refunds', 'perm' => NULL),
);
// Pencocokan khusus agar sub-halaman yang punya menu sendiri tidak ikut aktif.
foreach ($menu_nav as &$item) { if ($item['url'] === 'menu/promos') $item['match'] = '#^menu/promos($|/(create|edit))#'; }
foreach ($admin_nav as &$item) { if ($item['url'] === 'admin/roles') $item['match'] = '#^admin/roles($|/(create|edit))#'; }
unset($item);
if (can_any(array('menu.view', 'menu.view_recipe')) && setting('public_menu_enabled', '1') === '1')
{
	$menu_nav[] = array('label' => 'Menu Online', 'icon' => 'qr-code', 'url' => 'menu-online', 'perm' => NULL, 'external' => TRUE);
}

$groups = array(
	array('key' => 'main', 'label' => 'Utama', 'show' => TRUE, 'items' => $nav),
	array('key' => 'sales', 'label' => 'Penjualan', 'show' => TRUE, 'items' => $sales_nav),
	array('key' => 'menu', 'label' => 'Menu', 'show' => can_any(array('menu.view', 'menu.view_recipe')), 'items' => $menu_nav),
	array('key' => 'inventory', 'label' => 'Inventory', 'show' => can('inventory.view'), 'items' => $inventory_nav),
	array('key' => 'purchase', 'label' => 'Pembelian', 'show' => can('purchase.view'), 'items' => $purchase_nav),
	array('key' => 'finance', 'label' => 'Keuangan', 'show' => TRUE, 'items' => $finance_nav),
	array('key' => 'reports', 'label' => 'Laporan', 'show' => can_any(array('report.operational', 'report.financial')), 'items' => $report_nav),
	array('key' => 'admin', 'label' => 'Administrasi', 'show' => TRUE, 'items' => $admin_nav),
);
$uri = uri_string();
$crumb = '';
foreach ($groups as $gi => &$group)
{
	$group['items'] = ! $group['show'] ? array() : array_values(array_filter($group['items'], function ($i) {
		if (isset($i['any'])) return can_any($i['any']);
		return empty($i['perm']) OR can($i['perm']);
	}));
	$group['open'] = FALSE;
	foreach ($group['items'] as &$item)
	{
		$item['active'] = empty($item['external']) && (isset($item['match']) ? (bool) preg_match($item['match'], $uri) : is_active_nav($item['url']) !== '');
		if ($item['active']) { $group['open'] = TRUE; $crumb = $group['label']; }
	}
	unset($item);
}
unset($group);
$initials = strtoupper(implode('', array_map(function ($w) { return mb_substr($w, 0, 1); }, array_slice(preg_split('/\s+/', trim($current_user['name'])), 0, 2))));
$role_label = implode(', ', $current_user['role_names']) ?: 'Tanpa role';
?><!doctype html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?= e(isset($title) ? $title . ' · ' : '') ?>Resto Moiz</title>
	<script>try { if (localStorage.getItem('rm.sbCollapsed') === '1') document.documentElement.classList.add('sb-collapsed'); } catch (e) {}</script>
	<link rel="stylesheet" href="<?= base_url('assets/vendor/inter/inter.css') ?>">
	<link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/bootstrap.min.css') ?>">
	<link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
	<link rel="stylesheet" href="<?= asset_v('assets/css/app.css') ?>">
</head>
<body>
<div class="app">
	<aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar" aria-label="Navigasi utama">
		<div class="sidebar-brand">
			<div class="brand-logo"><i class="bi bi-shop"></i></div>
			<div class="brand-text">
				<span class="brand-name"><?= e(setting('resto_name', 'Resto Moiz')) ?></span>
				<span class="brand-sub">Restaurant Suite</span>
			</div>
			<button type="button" class="btn-close btn-close-white d-lg-none ms-auto" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Tutup"></button>
		</div>
		<div class="sidebar-search">
			<i class="bi bi-search"></i>
			<input type="search" id="nav-search" placeholder="Cari menu…" autocomplete="off" aria-label="Cari menu navigasi">
			<kbd>/</kbd>
		</div>
		<nav class="sidebar-nav" id="sidebar-nav">
			<?php foreach ($groups as $group): if ( ! $group['items']) continue; ?>
				<div class="nav-group" data-group="<?= $group['key'] ?>"<?= $group['open'] ? ' data-has-active="1"' : '' ?>>
					<?php if ($group['key'] !== 'main'): ?>
						<button type="button" class="nav-group-toggle" aria-expanded="true"><?= e($group['label']) ?><i class="bi bi-chevron-down chev"></i></button>
					<?php endif; ?>
					<div class="nav-group-items"><div>
						<?php foreach ($group['items'] as $item): ?>
							<a class="nav-link<?= $item['active'] ? ' active' : '' ?>" href="<?= site_url($item['url']) ?>" data-label="<?= e($item['label']) ?>"<?= $item['active'] ? ' aria-current="page"' : '' ?><?= empty($item['external']) ? '' : ' target="_blank" rel="noopener"' ?>>
								<i class="bi bi-<?= $item['icon'] ?>"></i><span class="nav-label"><?= e($item['label']) ?></span><?= empty($item['external']) ? '' : '<i class="bi bi-box-arrow-up-right nav-ext"></i>' ?>
							</a>
						<?php endforeach; ?>
					</div></div>
				</div>
			<?php endforeach; ?>
			<div class="sidebar-empty" id="nav-empty">Menu tidak ditemukan.</div>
		</nav>
		<div class="sidebar-foot">
			<div class="avatar avatar-sm"><?= e($initials ?: '?') ?></div>
			<div class="who"><b><?= e($current_user['name']) ?></b><span><?= e($role_label) ?></span></div>
			<a href="<?= site_url('logout') ?>" title="Keluar" aria-label="Keluar"><i class="bi bi-box-arrow-right"></i></a>
		</div>
	</aside>

	<div class="main">
		<header class="topbar">
			<button class="icon-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="Buka menu"><i class="bi bi-list"></i></button>
			<button class="icon-btn d-none d-lg-grid" type="button" id="sb-toggle" aria-label="Ciutkan / lebarkan sidebar" title="Ciutkan / lebarkan sidebar"><i class="bi bi-layout-sidebar"></i></button>
			<div class="page-heading">
				<?php if ($crumb): ?><span class="page-crumb"><?= e($crumb) ?></span><?php endif; ?>
				<h1 class="page-title"><?= e(isset($title) ? $title : '') ?></h1>
			</div>
			<div class="ms-auto d-flex align-items-center gap-2">
				<span class="topbar-chip"><i class="bi bi-calendar3"></i> <?= tgl(date('Y-m-d'), FALSE) ?></span>
				<?php if (can_any(array('sales.process', 'sales.order')) && $uri !== 'pos'): ?>
					<a class="btn btn-primary btn-sm d-none d-sm-inline-flex" href="<?= site_url('pos') ?>"><i class="bi bi-cash-coin"></i> Buka POS</a>
				<?php endif; ?>
				<div class="dropdown">
					<button class="user-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
						<span class="avatar avatar-sm"><?= e($initials ?: '?') ?></span>
						<span class="d-none d-sm-inline"><?= e($current_user['name']) ?></span>
					</button>
					<ul class="dropdown-menu dropdown-menu-end mt-2" style="min-width: 220px">
						<li class="px-3 py-2">
							<div class="fw-semibold"><?= e($current_user['name']) ?></div>
							<div class="small text-muted"><?= e($role_label) ?></div>
						</li>
						<li><hr class="dropdown-divider"></li>
						<li><a class="dropdown-item" href="<?= site_url('profile') ?>"><i class="bi bi-person"></i> Profil</a></li>
						<li><a class="dropdown-item" href="<?= site_url('profile/password') ?>"><i class="bi bi-key"></i> Ganti Password</a></li>
						<li><hr class="dropdown-divider"></li>
						<li><a class="dropdown-item text-danger" href="<?= site_url('logout') ?>"><i class="bi bi-box-arrow-right text-danger"></i> Keluar</a></li>
					</ul>
				</div>
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
<script src="<?= base_url('assets/vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset_v('assets/js/app.js') ?>"></script>
</body>
</html>
