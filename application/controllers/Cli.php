<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Perintah command line. Hanya bisa dijalankan dari CLI:
 *
 *   php public/index.php cli migrate                     Jalankan migration ke versi terbaru
 *   php public/index.php cli seed                        Sinkron permission, role default, settings
 *   php public/index.php cli setup                       migrate + seed
 *   php public/index.php cli backup [hari_simpan]           Backup database (gzip) ke storage/backups,
 *                                                        hapus backup lebih lama dari N hari (default 14)
 *   php public/index.php cli create_admin <username> [password]
 *                                                        Buat/reset user Admin. Tanpa password:
 *                                                        dibuatkan acak & wajib ganti saat login.
 */
class Cli extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		if ( ! is_cli())
		{
			show_404();
		}
	}

	public function index()
	{
		echo "Perintah: migrate | seed | setup | backup [hari] | create_admin <username> [password]\n";
	}

	public function setup()
	{
		$this->migrate();
		$this->seed();
	}

	public function migrate()
	{
		$this->load->library('migration');
		if ($this->migration->latest() === FALSE)
		{
			$this->_fail($this->migration->error_string());
		}
		echo "Migration OK\n";
	}

	public function seed()
	{
		$this->config->load('rbac', TRUE);
		$permissions = $this->config->item('rbac_permissions', 'rbac');
		$roles = $this->config->item('rbac_default_roles', 'rbac');

		$this->db->trans_start();

		// 1. Permission: tambah yang baru, perbarui deskripsi/modul.
		$existing = array_column($this->db->select('name')->get('permissions')->result_array(), 'name');
		$new_permissions = array_diff(array_keys($permissions), $existing);
		foreach ($permissions as $name => $meta)
		{
			$this->db->query(
				'INSERT INTO permissions (name, module, description) VALUES (?, ?, ?)
				 ON DUPLICATE KEY UPDATE module = VALUES(module), description = VALUES(description)',
				array($name, $meta[0], $meta[1])
			);
		}
		$perm_ids = array();
		foreach ($this->db->select('id, name')->get('permissions')->result_array() as $row)
		{
			$perm_ids[$row['name']] = (int) $row['id'];
		}

		// 2. Role default: hanya dibuat kalau belum ada. Permission role yang
		//    sudah ada tidak ditimpa supaya pengaturan admin tetap aman.
		$created = 0;
		foreach ($roles as $code => $role)
		{
			$existing_role = $this->db->select('id')->where('code', $code)->get('roles')->row_array();
			if ($existing_role)
			{
				// Permission yang BARU ditambahkan ke katalog ikut diberikan ke role
				// default yang mencantumkannya. Permission lama tidak disentuh.
				foreach (array_intersect($role['permissions'], $new_permissions) as $perm)
				{
					$this->db->query('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)', array($existing_role['id'], $perm_ids[$perm]));
				}
				continue;
			}
			$this->db->insert('roles', array(
				'code'        => $code,
				'name'        => $role['name'],
				'description' => $role['description'],
				'is_super'    => $role['is_super'] ? 1 : 0,
				'is_system'   => 1,
			));
			$role_id = (int) $this->db->insert_id();
			foreach ($role['permissions'] as $perm)
			{
				if ( ! isset($perm_ids[$perm]))
				{
					$this->_fail("Permission '$perm' di role '$code' tidak ada di rbac_permissions");
				}
				$this->db->insert('role_permissions', array('role_id' => $role_id, 'permission_id' => $perm_ids[$perm]));
			}
			$created++;
		}

		// 3. Pengaturan default (PRD: pajak & limit refund harus bisa diatur).
		$settings = array(
			'resto_name'          => array('Resto Moiz', 'Nama restoran (tampil di header & struk)'),
			'tax_rate'            => array('11', 'Tarif PPN dalam persen'),
			'tax_enabled'         => array('1', 'Kenakan PPN pada transaksi (1 = ya, 0 = tidak)'),
			'service_charge_rate' => array('0', 'Service charge dalam persen'),
			'refund_auto_limit'   => array('500000', 'Refund di bawah nilai ini boleh langsung oleh kasir'),
			'refund_auto_minutes' => array('5', 'Batas menit sejak transaksi untuk refund langsung kasir'),
			'refund_owner_limit'  => array('2000000', 'Refund di atas nilai ini butuh approval Owner'),
			'po_auto_limit'       => array('5000000', 'PO di bawah nilai ini auto-approved'),
			'po_owner_limit'      => array('20000000', 'PO di atas nilai ini butuh approval Owner'),
			'cash_variance_limit' => array('10000', 'Selisih kas shift yang masih boleh ditutup tanpa approval'),
			'expiry_alert_days'   => array('7', 'Peringatan bahan mendekati kedaluwarsa (hari sebelum)'),
			'slow_moving_days'    => array('30', 'Bahan dianggap slow-moving jika tidak keluar selama (hari)'),
			'dead_stock_days'     => array('60', 'Bahan dianggap dead stock jika tidak keluar selama (hari)'),
			'invoice_match_tolerance' => array('1', 'Toleransi selisih invoice vs barang diterima (persen)'),
			'po_variance_flag'    => array('5', 'Tandai PO jika nilai aktual berbeda dari PO lebih dari (persen)'),
			'menu_auto_oos'       => array('1', 'Menu otomatis "habis" jika stok bahan resep tidak cukup untuk 1 porsi (1 = ya)'),
			'menu_margin_warning' => array('40', 'Tandai menu dengan margin kotor di bawah (persen)'),
			'public_menu_enabled' => array('1', 'Aktifkan menu online publik untuk QR pelanggan (1 = ya)'),
			'resto_address'       => array('', 'Alamat resto (tampil di struk)'),
			'resto_phone'         => array('', 'Telepon resto (tampil di struk)'),
			'receipt_footer'      => array('Terima kasih atas kunjungan Anda!', 'Pesan di bawah struk'),
			'receipt_paper'       => array('80', 'Lebar kertas struk dalam mm (58 atau 80)'),
			'pos_block_insufficient_stock' => array('1', 'Tolak penjualan jika stok bahan di sistem tidak cukup (0 = tetap jual & catat kekurangan)'),
			'kitchen_refresh_seconds' => array('10', 'Interval refresh layar dapur (detik)'),
		);
		foreach ($settings as $key => $s)
		{
			$this->db->query(
				'INSERT IGNORE INTO app_settings (`key`, `value`, `description`) VALUES (?, ?, ?)',
				array($key, $s[0], $s[1])
			);
		}

		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE)
		{
			$this->_fail('Seed gagal, transaksi dibatalkan.');
		}
		echo 'Seed OK: ' . count($permissions) . ' permission (' . count($new_permissions) . " baru), $created role baru\n";
	}

	/**
	 * Backup database memakai utilitas CodeIgniter (PHP murni, tidak butuh
	 * exec/mysqldump yang biasanya dimatikan di aaPanel).
	 */
	public function backup($keep_days = 14)
	{
		$keep_days = max(1, (int) $keep_days);
		$dir = rtrim(dirname(FCPATH), '/') . '/storage/backups/';
		if ( ! is_dir($dir) && ! @mkdir($dir, 0750, TRUE))
		{
			$this->_fail('Folder backup tidak bisa dibuat: ' . $dir);
		}
		$this->load->dbutil();
		$started = microtime(TRUE);
		$sql = $this->dbutil->backup(array(
			'format'     => 'txt',
			'add_drop'   => TRUE,
			'add_insert' => TRUE,
			'newline'    => "\n",
			'foreign_key_checks' => FALSE,
		));
		$file = $dir . $this->db->database . '-' . date('Ymd-His') . '.sql.gz';
		if (file_put_contents($file, gzencode("-- Backup " . $this->db->database . ' ' . date('c') . "\n" . $sql, 9)) === FALSE)
		{
			$this->_fail('Gagal menulis file backup.');
		}
		@chmod($file, 0640);

		$removed = 0;
		foreach (glob($dir . '*.sql.gz') as $old)
		{
			if (filemtime($old) < time() - $keep_days * 86400)
			{
				@unlink($old);
				$removed++;
			}
		}
		$this->db->query("INSERT INTO app_settings (`key`, `value`, `description`) VALUES ('last_backup_at', ?, 'Waktu backup database terakhir (diisi otomatis)')
			ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)", array(date('Y-m-d H:i:s')));
		printf("Backup OK: %s (%s KB, %.1f detik), %d backup lama dihapus\n", $file, number_format(filesize($file) / 1024, 1), microtime(TRUE) - $started, $removed);
	}

	public function create_admin($username = NULL, $password = NULL)
	{
		if ( ! $username OR ! preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username))
		{
			$this->_fail('Username wajib (3-50 karakter: huruf, angka, titik, strip, underscore).');
		}

		$generated = FALSE;
		if ($password === NULL)
		{
			$password = substr(str_replace(array('+', '/', '='), '', base64_encode(random_bytes(18))), 0, 14);
			$generated = TRUE;
		}
		elseif (strlen($password) < 8)
		{
			$this->_fail('Password minimal 8 karakter.');
		}

		$role = $this->db->where('is_super', 1)->order_by('id')->get('roles')->row_array();
		if ( ! $role)
		{
			$this->_fail('Role Admin belum ada. Jalankan: php public/index.php cli setup');
		}

		$this->load->model('User_model');
		$this->load->library('audit_logger', NULL, 'audit');
		$existing = $this->User_model->find_by_username($username);

		if ($existing)
		{
			$this->User_model->set_password($existing['id'], $password, $generated);
			$this->db->where('id', $existing['id'])->update('users', array('is_active' => 1));
			$this->db->query('INSERT IGNORE INTO user_roles (user_id, role_id, is_primary) VALUES (?, ?, 0)', array($existing['id'], $role['id']));
			$user_id = (int) $existing['id'];
			$action = 'direset';
		}
		else
		{
			$user_id = $this->User_model->create(array(
				'username'             => $username,
				'name'                 => 'Administrator',
				'email'                => '',
				'password'             => $password,
				'is_active'            => 1,
				'must_change_password' => $generated,
			), $role['id']);
			$action = 'dibuat';
		}

		$this->audit->log('cli_create_admin', array(
			'user_id' => NULL, 'username' => 'cli', 'table_name' => 'users', 'record_id' => $user_id,
			'detail' => "Admin '$username' $action lewat CLI",
		));

		echo "Admin '$username' $action.\n";
		if ($generated)
		{
			echo "Password sementara: $password\n(wajib diganti saat login pertama)\n";
		}
	}

	protected function _fail($message)
	{
		fwrite(STDERR, "ERROR: $message\n");
		exit(1);
	}
}
