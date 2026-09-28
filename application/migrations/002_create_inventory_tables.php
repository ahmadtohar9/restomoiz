<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Fase 2: Inventory (PRD 2.1).
 *
 * Stok dinilai dengan FIFO: setiap stok masuk membuat batch
 * (ingredient_batches), stok keluar mengambil dari batch tertua dan
 * pemakaiannya dicatat per batch (stock_movement_batches) supaya nilai
 * COGS bisa ditelusuri dan dibalik (mis. saat refund di fase 6).
 *
 * ingredients.qty_on_hand & stock_value adalah cache dari total batch,
 * selalu diperbarui dalam transaksi yang sama dengan pergerakan stok.
 */
class Migration_Create_inventory_tables extends CI_Migration {

	public function up()
	{
		$engine = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

		$this->db->query("CREATE TABLE IF NOT EXISTS `doc_sequences` (
			`seq_key` VARCHAR(30) NOT NULL,
			`period` VARCHAR(10) NOT NULL DEFAULT '',
			`last_no` INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (`seq_key`, `period`)
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `ingredient_categories` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`parent_id` INT UNSIGNED NULL,
			`name` VARCHAR(100) NOT NULL,
			`description` VARCHAR(255) NULL,
			`is_active` TINYINT(1) NOT NULL DEFAULT 1,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_cat_parent_name` (`parent_id`, `name`),
			CONSTRAINT `fk_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `ingredient_categories` (`id`) ON DELETE RESTRICT
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `suppliers` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`code` VARCHAR(20) NOT NULL,
			`name` VARCHAR(150) NOT NULL,
			`contact_person` VARCHAR(100) NULL,
			`phone` VARCHAR(30) NULL,
			`email` VARCHAR(150) NULL,
			`address` VARCHAR(255) NULL,
			`city` VARCHAR(100) NULL,
			`payment_terms` VARCHAR(20) NOT NULL DEFAULT 'COD',
			`min_order_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`lead_time_days` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			`quality_score` TINYINT UNSIGNED NULL,
			`notes` TEXT NULL,
			`is_active` TINYINT(1) NOT NULL DEFAULT 1,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_suppliers_code` (`code`),
			KEY `idx_suppliers_name` (`name`)
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `ingredients` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`code` VARCHAR(30) NOT NULL,
			`name` VARCHAR(150) NOT NULL,
			`description` VARCHAR(255) NULL,
			`category_id` INT UNSIGNED NULL,
			`unit` VARCHAR(20) NOT NULL,
			`default_supplier_id` INT UNSIGNED NULL,
			`current_price` DECIMAL(15,4) NOT NULL DEFAULT 0 COMMENT 'harga per satuan standar, dari pembelian terakhir',
			`min_stock` DECIMAL(15,3) NOT NULL DEFAULT 0,
			`max_stock` DECIMAL(15,3) NOT NULL DEFAULT 0,
			`reorder_point` DECIMAL(15,3) NOT NULL DEFAULT 0,
			`reorder_qty` DECIMAL(15,3) NOT NULL DEFAULT 0,
			`location` VARCHAR(100) NULL,
			`status` ENUM('active','inactive','discontinued') NOT NULL DEFAULT 'active',
			`image_path` VARCHAR(255) NULL,
			`notes` TEXT NULL,
			`qty_on_hand` DECIMAL(15,3) NOT NULL DEFAULT 0,
			`stock_value` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`last_in_at` DATETIME NULL,
			`last_out_at` DATETIME NULL,
			`last_counted_at` DATETIME NULL,
			`created_by` INT UNSIGNED NULL,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_ingredients_code` (`code`),
			KEY `idx_ingredients_name` (`name`),
			KEY `idx_ingredients_status` (`status`),
			CONSTRAINT `fk_ing_category` FOREIGN KEY (`category_id`) REFERENCES `ingredient_categories` (`id`) ON DELETE SET NULL,
			CONSTRAINT `fk_ing_supplier` FOREIGN KEY (`default_supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
		) $engine");

		// Satuan alternatif: 1 [unit] = factor x satuan standar. Contoh standar kg: gram = 0.001, karung = 25.
		$this->db->query("CREATE TABLE IF NOT EXISTS `ingredient_units` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`ingredient_id` INT UNSIGNED NOT NULL,
			`unit` VARCHAR(20) NOT NULL,
			`factor` DECIMAL(18,6) NOT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_iu` (`ingredient_id`, `unit`),
			CONSTRAINT `fk_iu_ingredient` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE CASCADE
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `stock_movements` (
			`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`movement_no` VARCHAR(30) NOT NULL,
			`ingredient_id` INT UNSIGNED NOT NULL,
			`type` ENUM('IN','OUT','ADJ') NOT NULL,
			`reason` VARCHAR(30) NOT NULL,
			`qty` DECIMAL(15,3) NOT NULL COMMENT 'positif = masuk, negatif = keluar (satuan standar)',
			`unit_cost` DECIMAL(15,4) NOT NULL DEFAULT 0,
			`total_cost` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`qty_before` DECIMAL(15,3) NOT NULL,
			`qty_after` DECIMAL(15,3) NOT NULL,
			`input_qty` DECIMAL(15,3) NULL,
			`input_unit` VARCHAR(20) NULL,
			`supplier_id` INT UNSIGNED NULL,
			`ref_type` VARCHAR(30) NULL,
			`ref_id` VARCHAR(30) NULL,
			`notes` VARCHAR(255) NULL,
			`created_by` INT UNSIGNED NULL,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			KEY `idx_sm_no` (`movement_no`),
			KEY `idx_sm_ing_date` (`ingredient_id`, `created_at`),
			KEY `idx_sm_date` (`created_at`),
			KEY `idx_sm_ref` (`ref_type`, `ref_id`),
			CONSTRAINT `fk_sm_ingredient` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE RESTRICT,
			CONSTRAINT `fk_sm_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `ingredient_batches` (
			`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`ingredient_id` INT UNSIGNED NOT NULL,
			`movement_id` BIGINT UNSIGNED NULL,
			`received_at` DATETIME NOT NULL,
			`qty_in` DECIMAL(15,3) NOT NULL,
			`qty_remaining` DECIMAL(15,3) NOT NULL,
			`unit_cost` DECIMAL(15,4) NOT NULL,
			`expiry_date` DATE NULL,
			`supplier_id` INT UNSIGNED NULL,
			PRIMARY KEY (`id`),
			KEY `idx_batch_fifo` (`ingredient_id`, `qty_remaining`, `received_at`),
			KEY `idx_batch_expiry` (`expiry_date`),
			CONSTRAINT `fk_batch_ingredient` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE RESTRICT,
			CONSTRAINT `fk_batch_movement` FOREIGN KEY (`movement_id`) REFERENCES `stock_movements` (`id`) ON DELETE SET NULL,
			CONSTRAINT `fk_batch_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `stock_movement_batches` (
			`movement_id` BIGINT UNSIGNED NOT NULL,
			`batch_id` BIGINT UNSIGNED NOT NULL,
			`qty` DECIMAL(15,3) NOT NULL,
			`unit_cost` DECIMAL(15,4) NOT NULL,
			PRIMARY KEY (`movement_id`, `batch_id`),
			KEY `idx_smb_batch` (`batch_id`),
			CONSTRAINT `fk_smb_movement` FOREIGN KEY (`movement_id`) REFERENCES `stock_movements` (`id`) ON DELETE CASCADE,
			CONSTRAINT `fk_smb_batch` FOREIGN KEY (`batch_id`) REFERENCES `ingredient_batches` (`id`) ON DELETE RESTRICT
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `supplier_price_history` (
			`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`supplier_id` INT UNSIGNED NOT NULL,
			`ingredient_id` INT UNSIGNED NOT NULL,
			`price` DECIMAL(15,4) NOT NULL COMMENT 'per satuan standar',
			`recorded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`ref_type` VARCHAR(30) NULL,
			`ref_id` VARCHAR(30) NULL,
			PRIMARY KEY (`id`),
			KEY `idx_sph` (`supplier_id`, `ingredient_id`, `recorded_at`),
			CONSTRAINT `fk_sph_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
			CONSTRAINT `fk_sph_ingredient` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE CASCADE
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `stock_opnames` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`opname_no` VARCHAR(30) NOT NULL,
			`opname_date` DATE NOT NULL,
			`category_id` INT UNSIGNED NULL,
			`status` ENUM('draft','posted','cancelled') NOT NULL DEFAULT 'draft',
			`notes` VARCHAR(255) NULL,
			`created_by` INT UNSIGNED NULL,
			`posted_by` INT UNSIGNED NULL,
			`posted_at` DATETIME NULL,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_opname_no` (`opname_no`)
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `stock_opname_items` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`opname_id` INT UNSIGNED NOT NULL,
			`ingredient_id` INT UNSIGNED NOT NULL,
			`system_qty` DECIMAL(15,3) NOT NULL DEFAULT 0 COMMENT 'diisi saat posting',
			`counted_qty` DECIMAL(15,3) NULL,
			`variance` DECIMAL(15,3) NULL,
			`variance_value` DECIMAL(15,2) NULL,
			`notes` VARCHAR(255) NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_soi` (`opname_id`, `ingredient_id`),
			CONSTRAINT `fk_soi_opname` FOREIGN KEY (`opname_id`) REFERENCES `stock_opnames` (`id`) ON DELETE CASCADE,
			CONSTRAINT `fk_soi_ingredient` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE RESTRICT
		) $engine");
	}

	public function down()
	{
		foreach (array('stock_opname_items', 'stock_opnames', 'supplier_price_history', 'stock_movement_batches',
			'ingredient_batches', 'stock_movements', 'ingredient_units', 'ingredients', 'suppliers',
			'ingredient_categories', 'doc_sequences') as $table)
		{
			$this->db->query("DROP TABLE IF EXISTS `$table`");
		}
	}
}
