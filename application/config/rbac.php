<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Katalog Permission & Role Default (PRD bagian 3)
|--------------------------------------------------------------------------
|
| Sumber kebenaran untuk permission. Jalankan `php public/index.php cli seed`
| setelah mengubah file ini: permission baru ditambahkan, permission yang
| sudah ada deskripsinya diperbarui. Permission TIDAK pernah dihapus otomatis
| supaya mapping role yang sudah diatur admin tidak hilang.
|
| Role dengan 'is_super' => TRUE otomatis memiliki SEMUA permission
| (tidak perlu dicentang di matrix).
*/

$config['rbac_modules'] = array(
	'inventory' => 'Inventory',
	'purchase'  => 'Pembelian',
	'menu'      => 'Menu',
	'sales'     => 'Penjualan & POS',
	'payment'   => 'Pembayaran',
	'report'    => 'Laporan',
	'admin'     => 'Administrasi',
);

$config['rbac_permissions'] = array(
	// Inventory
	'inventory.view'         => array('inventory', 'Lihat bahan baku & stok'),
	'inventory.create'       => array('inventory', 'Tambah bahan baku'),
	'inventory.edit'         => array('inventory', 'Ubah bahan baku'),
	'inventory.delete'       => array('inventory', 'Hapus bahan baku'),
	'inventory.adjust'       => array('inventory', 'Penyesuaian stok (stock adjustment)'),
	'inventory.supplier'     => array('inventory', 'Kelola supplier'),
	'inventory.view_cost'    => array('inventory', 'Lihat harga beli & nilai stok'),

	// Pembelian
	'purchase.view'          => array('purchase', 'Lihat PO, GR, invoice'),
	'purchase.create'        => array('purchase', 'Buat & submit PO'),
	'purchase.approve'       => array('purchase', 'Approve PO (level 1)'),
	'purchase.approve_owner' => array('purchase', 'Approve PO > 20 juta (level 2 / Owner)'),
	'purchase.receive'       => array('purchase', 'Penerimaan barang (GR)'),
	'purchase.invoice'       => array('purchase', 'Kelola & approve invoice supplier'),
	'purchase.payment'       => array('purchase', 'Input & verifikasi pembayaran supplier'),

	// Menu
	'menu.view'              => array('menu', 'Lihat menu & harga'),
	'menu.create'            => array('menu', 'Tambah menu'),
	'menu.edit'              => array('menu', 'Ubah menu & resep'),
	'menu.delete'            => array('menu', 'Hapus menu'),
	'menu.edit_price'        => array('menu', 'Ubah harga jual'),
	'menu.promo'             => array('menu', 'Kelola promo'),
	'menu.view_recipe'       => array('menu', 'Lihat resep'),
	'menu.view_cogs'         => array('menu', 'Lihat COGS & margin'),

	// Penjualan & POS
	'sales.process'          => array('sales', 'Proses transaksi POS'),
	'sales.order'            => array('sales', 'Buat order (waiter)'),
	'sales.edit_order'       => array('sales', 'Ubah order yang sudah dibuat'),
	'sales.kitchen'          => array('sales', 'Lihat order dapur & tandai siap'),
	'sales.refund'           => array('sales', 'Refund di bawah limit'),
	'sales.refund_approve'   => array('sales', 'Approve refund di atas limit (Manajer)'),
	'sales.refund_owner'     => array('sales', 'Approve refund > 2 juta (Owner)'),
	'sales.shift'            => array('sales', 'Buka/tutup shift kasir'),
	'sales.shift_approve'    => array('sales', 'Approve selisih kas shift'),

	// Pembayaran
	'payment.reconcile'      => array('payment', 'Rekonsiliasi & settlement'),

	// Laporan
	'report.own_shift'       => array('report', 'Lihat ringkasan shift sendiri'),
	'report.operational'     => array('report', 'Laporan operasional (penjualan, stok, kasir)'),
	'report.inventory'       => array('report', 'Laporan inventory & pembelian'),
	'report.financial'       => array('report', 'Laporan keuangan (P&L, expense)'),

	// Administrasi
	'admin.users'            => array('admin', 'Kelola user'),
	'admin.roles'            => array('admin', 'Kelola role & permission'),
	'admin.audit'            => array('admin', 'Lihat audit log'),
	'admin.settings'         => array('admin', 'Ubah pengaturan aplikasi'),
);

$config['rbac_default_roles'] = array(
	'admin' => array(
		'name'        => 'Admin',
		'description' => 'Super user - akses penuh',
		'is_super'    => TRUE,
		'permissions' => array(),
	),
	'owner' => array(
		'name'        => 'Owner',
		'description' => 'Pemilik / direktur - semua laporan & approval tertinggi',
		'is_super'    => FALSE,
		'permissions' => array(
			'inventory.view', 'inventory.view_cost', 'purchase.view', 'purchase.approve', 'purchase.approve_owner',
			'menu.view', 'menu.view_recipe', 'menu.view_cogs',
			'sales.refund_approve', 'sales.refund_owner', 'sales.shift_approve',
			'report.operational', 'report.inventory', 'report.financial',
			'admin.users', 'admin.audit',
		),
	),
	'manajer_ops' => array(
		'name'        => 'Manajer Operasional',
		'description' => 'Operasional harian, menu, promo, approval',
		'is_super'    => FALSE,
		'permissions' => array(
			'inventory.view', 'inventory.view_cost', 'purchase.view', 'purchase.approve',
			'menu.view', 'menu.create', 'menu.edit', 'menu.edit_price', 'menu.promo',
			'menu.view_recipe', 'menu.view_cogs',
			'sales.process', 'sales.order', 'sales.edit_order', 'sales.refund',
			'sales.refund_approve', 'sales.shift', 'sales.shift_approve',
			'report.own_shift', 'report.operational',
		),
	),
	'manajer_inv' => array(
		'name'        => 'Manajer Inventory',
		'description' => 'Supply chain, pembelian, stok',
		'is_super'    => FALSE,
		'permissions' => array(
			'inventory.view', 'inventory.create', 'inventory.edit', 'inventory.adjust',
			'inventory.supplier', 'inventory.view_cost', 'purchase.view', 'purchase.create', 'purchase.receive',
			'menu.view', 'menu.view_recipe', 'report.inventory',
		),
	),
	'kasir' => array(
		'name'        => 'Kasir',
		'description' => 'Operator POS',
		'is_super'    => FALSE,
		'permissions' => array(
			'menu.view', 'sales.process', 'sales.refund', 'sales.shift', 'report.own_shift',
		),
	),
	'accounting' => array(
		'name'        => 'Accounting',
		'description' => 'Keuangan, pembayaran, rekonsiliasi',
		'is_super'    => FALSE,
		'permissions' => array(
			'inventory.view', 'inventory.view_cost', 'purchase.view', 'purchase.invoice', 'purchase.payment',
			'menu.view', 'menu.view_cogs', 'payment.reconcile',
			'report.operational', 'report.inventory', 'report.financial',
		),
	),
	'dapur' => array(
		'name'        => 'Staff Dapur',
		'description' => 'Produksi makanan & minuman',
		'is_super'    => FALSE,
		'permissions' => array(
			'inventory.view', 'menu.view_recipe', 'sales.kitchen',
		),
	),
	'waiter' => array(
		'name'        => 'Waitstaff',
		'description' => 'Pelayanan & pencatatan order',
		'is_super'    => FALSE,
		'permissions' => array(
			'menu.view', 'sales.order',
		),
	),
);
