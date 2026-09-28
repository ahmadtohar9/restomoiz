<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Fase 4: Manajemen menu (PRD 2.3).
 *
 * Setiap menu punya minimal satu varian (menu tanpa varian = satu varian
 * "Reguler"). Harga, resep, barcode, dan COGS melekat pada varian, karena
 * PRD 2.3.2 mencontohkan resep & COGS berbeda per varian (Teh Hot/Iced).
 *
 * Harga disimpan sebagai riwayat (menu_variant_prices): harga yang berlaku =
 * baris dengan effective_from terbaru yang <= sekarang. Dengan begitu
 * perubahan harga terjadwal dan riwayat perubahan (PRD 2.3.3) tercatat alami.
 */
class Migration_Create_menu_tables extends CI_Migration {

	public function up()
	{
		$engine = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

		$this->db->query("CREATE TABLE IF NOT EXISTS `menu_categories` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`parent_id` INT UNSIGNED NULL,
			`name` VARCHAR(100) NOT NULL,
			`description` VARCHAR(255) NULL,
			`image_path` VARCHAR(255) NULL,
			`sort_order` INT NOT NULL DEFAULT 0,
			`is_hidden` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'kategori internal, tidak tampil di menu pelanggan',
			`is_active` TINYINT(1) NOT NULL DEFAULT 1,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_mcat_parent_name` (`parent_id`, `name`),
			CONSTRAINT `fk_mcat_parent` FOREIGN KEY (`parent_id`) REFERENCES `menu_categories` (`id`) ON DELETE RESTRICT
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `menus` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`code` VARCHAR(30) NOT NULL,
			`name` VARCHAR(150) NOT NULL,
			`description` VARCHAR(500) NULL,
			`category_id` INT UNSIGNED NULL,
			`image_path` VARCHAR(255) NULL,
			`status` ENUM('active','inactive','out_of_stock') NOT NULL DEFAULT 'active',
			`prep_minutes` SMALLINT UNSIGNED NULL,
			`allergens` VARCHAR(255) NULL,
			`spicy_level` TINYINT UNSIGNED NULL,
			`sort_order` INT NOT NULL DEFAULT 0,
			`created_by` INT UNSIGNED NULL,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_menus_code` (`code`),
			KEY `idx_menus_category` (`category_id`, `sort_order`),
			CONSTRAINT `fk_menu_category` FOREIGN KEY (`category_id`) REFERENCES `menu_categories` (`id`) ON DELETE SET NULL
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `menu_variants` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`menu_id` INT UNSIGNED NOT NULL,
			`name` VARCHAR(100) NOT NULL,
			`barcode` VARCHAR(50) NULL,
			`sort_order` INT NOT NULL DEFAULT 0,
			`is_active` TINYINT(1) NOT NULL DEFAULT 1,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_variant_barcode` (`barcode`),
			UNIQUE KEY `uq_variant_name` (`menu_id`, `name`),
			CONSTRAINT `fk_variant_menu` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `menu_variant_prices` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`variant_id` INT UNSIGNED NOT NULL,
			`price` DECIMAL(15,2) NOT NULL,
			`member_price` DECIMAL(15,2) NULL,
			`bulk_min_qty` SMALLINT UNSIGNED NULL,
			`bulk_price` DECIMAL(15,2) NULL,
			`effective_from` DATETIME NOT NULL,
			`reason` VARCHAR(255) NULL,
			`created_by` INT UNSIGNED NULL,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			KEY `idx_mvp_effective` (`variant_id`, `effective_from`),
			CONSTRAINT `fk_mvp_variant` FOREIGN KEY (`variant_id`) REFERENCES `menu_variants` (`id`) ON DELETE CASCADE
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `menu_recipes` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`variant_id` INT UNSIGNED NOT NULL,
			`ingredient_id` INT UNSIGNED NOT NULL,
			`qty` DECIMAL(15,3) NOT NULL,
			`unit` VARCHAR(20) NOT NULL,
			`factor` DECIMAL(18,6) NOT NULL DEFAULT 1,
			`qty_std` DECIMAL(15,4) NOT NULL COMMENT 'per porsi, satuan standar bahan',
			`notes` VARCHAR(255) NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_recipe` (`variant_id`, `ingredient_id`),
			KEY `idx_recipe_ingredient` (`ingredient_id`),
			CONSTRAINT `fk_recipe_variant` FOREIGN KEY (`variant_id`) REFERENCES `menu_variants` (`id`) ON DELETE CASCADE,
			CONSTRAINT `fk_recipe_ingredient` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE RESTRICT
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `menu_cogs_history` (
			`variant_id` INT UNSIGNED NOT NULL,
			`recorded_on` DATE NOT NULL,
			`cogs` DECIMAL(15,2) NOT NULL,
			`price` DECIMAL(15,2) NOT NULL,
			PRIMARY KEY (`variant_id`, `recorded_on`),
			CONSTRAINT `fk_cogs_variant` FOREIGN KEY (`variant_id`) REFERENCES `menu_variants` (`id`) ON DELETE CASCADE
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `promos` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`name` VARCHAR(150) NOT NULL,
			`description` VARCHAR(255) NULL,
			`promo_code` VARCHAR(30) NULL COMMENT 'kosong = otomatis; diisi = harus diinput kasir',
			`type` ENUM('fixed','percent','buy_get','bundle') NOT NULL,
			`value` DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'nominal (fixed) atau persen (percent)',
			`buy_qty` SMALLINT UNSIGNED NULL,
			`get_qty` SMALLINT UNSIGNED NULL,
			`bundle_price` DECIMAL(15,2) NULL,
			`scope` ENUM('all','category','menu') NOT NULL DEFAULT 'all',
			`min_purchase` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`min_qty` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			`payment_methods` VARCHAR(100) NULL COMMENT 'kosong = semua; contoh: cash,debit',
			`member_only` TINYINT(1) NOT NULL DEFAULT 0,
			`max_discount` DECIMAL(15,2) NULL COMMENT 'maks. diskon per transaksi',
			`stackable` TINYINT(1) NOT NULL DEFAULT 0,
			`max_usage_total` INT UNSIGNED NULL,
			`max_usage_per_customer` INT UNSIGNED NULL,
			`usage_count` INT UNSIGNED NOT NULL DEFAULT 0,
			`start_at` DATETIME NOT NULL,
			`end_at` DATETIME NULL,
			`days_of_week` VARCHAR(20) NULL COMMENT '1=Senin..7=Minggu, kosong = setiap hari',
			`time_start` TIME NULL,
			`time_end` TIME NULL,
			`is_active` TINYINT(1) NOT NULL DEFAULT 1,
			`created_by` INT UNSIGNED NULL,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_promo_code` (`promo_code`),
			KEY `idx_promo_active` (`is_active`, `start_at`, `end_at`)
		) $engine");

		// Target promo: kategori / menu (scope), atau varian + qty (isi paket bundling).
		$this->db->query("CREATE TABLE IF NOT EXISTS `promo_items` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`promo_id` INT UNSIGNED NOT NULL,
			`item_type` ENUM('category','menu','variant') NOT NULL,
			`item_id` INT UNSIGNED NOT NULL,
			`qty` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			PRIMARY KEY (`id`),
			KEY `idx_promo_items` (`promo_id`),
			CONSTRAINT `fk_pi_promo` FOREIGN KEY (`promo_id`) REFERENCES `promos` (`id`) ON DELETE CASCADE
		) $engine");
	}

	public function down()
	{
		foreach (array('promo_items', 'promos', 'menu_cogs_history', 'menu_recipes', 'menu_variant_prices', 'menu_variants', 'menus', 'menu_categories') as $table)
		{
			$this->db->query("DROP TABLE IF EXISTS `$table`");
		}
	}
}
