<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Go-Live Checklist (PRD bagian 6): mengecek otomatis hal yang bisa dicek
 * sistem, dan menampilkan sisanya sebagai daftar manual.
 */
class Golive extends MY_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->require_permission('admin.settings');
	}

	public function index()
	{
		$db = $this->db;
		$count = function ($table, $where = NULL) use ($db) {
			if ($where) { $db->where($where, NULL, FALSE); }
			return (int) $db->count_all_results($table);
		};
		$this->config->load('migration', TRUE);
		$target = (int) $this->config->item('migration_version', 'migration');
		$version = (int) $db->select('version')->get('migrations')->row()->version;
		$last_backup = setting('last_backup_at');
		$role_users = array_column($db->query(
			"SELECT r.code, COUNT(DISTINCT u.id) AS n FROM roles r LEFT JOIN user_roles ur ON ur.role_id = r.id
			 LEFT JOIN users u ON u.id = ur.user_id AND u.is_active = 1 GROUP BY r.code"
		)->result_array(), 'n', 'code');
		$menus_active = $count('menus', "status != 'inactive'");
		$no_recipe = (int) $db->query(
			"SELECT COUNT(*) AS n FROM menu_variants v JOIN menus m ON m.id = v.menu_id
			 WHERE v.is_active = 1 AND m.status != 'inactive' AND NOT EXISTS (SELECT 1 FROM menu_recipes r WHERE r.variant_id = v.id)"
		)->row()->n;
		$weak_admins = (int) $db->query(
			"SELECT COUNT(DISTINCT u.id) AS n FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id
			 WHERE r.is_super = 1 AND u.is_active = 1 AND u.must_change_password = 1"
		)->row()->n;

		$groups = array(
			'Database & infrastruktur' => array(
				array($version === $target, 'Semua migration database sudah dijalankan', "versi $version dari $target", NULL),
				array(strpos((string) base_url(), 'https://') === 0 && config_item('cookie_secure'), 'SSL aktif & cookie aman', base_url(), NULL),
				array($last_backup && strtotime($last_backup) > time() - 26 * 3600, 'Backup database otomatis berjalan (≤ 26 jam lalu)',
					$last_backup ? 'terakhir ' . tgl($last_backup) : 'belum pernah — jadwalkan: php public/index.php cli backup', NULL),
			),
			'Pengaturan aplikasi' => array(
				array($count('roles') >= 8 && $count('permissions') > 0, 'Role & permission default sudah di-seed', $count('roles') . ' role, ' . $count('permissions') . ' permission', 'admin/roles'),
				array($weak_admins === 0, 'Password admin sudah diganti dari password sementara', $weak_admins ? "$weak_admins admin masih wajib ganti password" : 'OK', 'admin/users'),
				array(setting('resto_address', '') !== '' && setting('resto_phone', '') !== '', 'Profil resto (nama, alamat, telepon) untuk struk', '', 'admin/settings'),
				array(TRUE, 'PPN & service charge', 'PPN ' . (setting('tax_enabled', '1') === '1' ? setting('tax_rate') . '%' : 'nonaktif') . ', service ' . setting('service_charge_rate', 0) . '%', 'admin/settings'),
				array(TRUE, 'Batas refund & approval PO', 'refund kasir < ' . rupiah(setting('refund_auto_limit')) . ', PO otomatis < ' . rupiah(setting('po_auto_limit')), 'admin/settings'),
			),
			'Data master' => array(
				array($count('ingredients', "status = 'active'") > 0, 'Bahan baku terdaftar', $count('ingredients', "status = 'active'") . ' bahan aktif', 'inventory/ingredients'),
				array($count('stock_movements') > 0, 'Stok awal sudah diinput (saldo awal / penerimaan)', $count('stock_movements') . ' pergerakan stok', 'inventory/stock/in'),
				array($count('suppliers', 'is_active = 1') > 0, 'Supplier terdaftar', $count('suppliers', 'is_active = 1') . ' supplier', 'inventory/suppliers'),
				array($menus_active > 0, 'Menu aktif dengan harga', "$menus_active menu", 'menu/items'),
				array($menus_active > 0 && $no_recipe === 0, 'Semua varian menu punya resep (COGS & stok otomatis)', $no_recipe ? "$no_recipe varian belum ada resep" : 'OK', 'menu/analysis'),
				array($count('dining_tables', 'is_active = 1') > 0, 'Meja untuk dine-in', $count('dining_tables', 'is_active = 1') . ' meja', 'sales/tables'),
			),
			'User & tim' => array(
				array( ! empty($role_users['kasir']), 'Ada user Kasir', (int) (isset($role_users['kasir']) ? $role_users['kasir'] : 0) . ' user', 'admin/users'),
				array( ! empty($role_users['dapur']), 'Ada user Staff Dapur', (int) (isset($role_users['dapur']) ? $role_users['dapur'] : 0) . ' user', 'admin/users'),
				array( ! empty($role_users['manajer_ops']) OR ! empty($role_users['owner']), 'Ada approver (Manajer / Owner) untuk refund, PO, selisih kas', (int) (isset($role_users['manajer_ops']) ? $role_users['manajer_ops'] : 0) . ' manajer, ' . (int) (isset($role_users['owner']) ? $role_users['owner'] : 0) . ' owner', 'admin/users'),
				array( ! empty($role_users['accounting']), 'Ada user Accounting (settlement & pembayaran)', (int) (isset($role_users['accounting']) ? $role_users['accounting'] : 0) . ' user', 'admin/users'),
			),
			'Uji coba alur' => array(
				array($count('purchase_orders', "status IN ('received','closed')") > 0, 'PO → penerimaan barang sudah dicoba', $count('purchase_orders') . ' PO', 'purchase/orders'),
				array($count('supplier_payments', "status = 'verified'") > 0, 'Invoice → pembayaran supplier sudah dicoba', $count('supplier_payments') . ' pembayaran', 'purchase/payments'),
				array($count('orders', "status = 'paid'") > 0, 'Transaksi POS sudah dicoba', $count('orders', "status = 'paid'") . ' transaksi lunas', 'pos'),
				array($count('order_refunds') > 0, 'Alur refund sudah dicoba', $count('order_refunds') . ' refund', 'sales/refunds'),
				array($count('shifts', "status = 'closed'") > 0, 'Buka-tutup shift kasir sudah dicoba', $count('shifts', "status = 'closed'") . ' shift ditutup', 'sales/shifts'),
				array($count('stock_opnames', "status = 'posted'") > 0, 'Stock opname sudah dicoba', $count('stock_opnames', "status = 'posted'") . ' opname', 'inventory/opname'),
			),
		);
		// Profil resto: tampilkan nama & alamat apa adanya.
		$groups['Pengaturan aplikasi'][2][2] = setting('resto_name', '-') . ' · ' . (setting('resto_address', '') ?: 'alamat belum diisi') . ' · ' . (setting('resto_phone', '') ?: 'telepon belum diisi');

		$this->render('admin/golive/index', array(
			'title'  => 'Go-Live Checklist',
			'groups' => $groups,
			'manual' => array(
				'Terminal POS (tablet/PC) sudah dites membuka ' . site_url('pos'),
				'Scanner barcode dites: scan label dari menu Barcode & Label ke kolom cari POS',
				'Printer struk (58/80 mm) & printer dapur sudah dites cetak',
				'Koneksi internet kasir & dapur stabil (layar dapur refresh otomatis)',
				'Kredensial user dibagikan secara aman; setiap orang punya akun sendiri',
				'Pelatihan staf (kasir, dapur, gudang, accounting) sudah dilakukan',
				'Persetujuan Owner, Finance, dan Manajer Operasional',
			),
		));
	}
}
