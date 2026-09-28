<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Fase 5: Penjualan & POS (PRD 2.4).
 *
 * Harga, nama, dan topping disimpan sebagai snapshot di order_items supaya
 * transaksi lama tidak berubah saat master menu/harga diubah.
 * Stok bahan dikurangi saat item dikirim ke dapur (movement ref_type
 * 'order_item'), dan dikembalikan kalau item dibatalkan sebelum dimasak.
 */
class Migration_Create_sales_tables extends CI_Migration {

	public function up()
	{
		$engine = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

		$this->db->query("CREATE TABLE IF NOT EXISTS `dining_tables` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`name` VARCHAR(30) NOT NULL,
			`area` VARCHAR(50) NULL,
			`capacity` SMALLINT UNSIGNED NOT NULL DEFAULT 4,
			`sort_order` INT NOT NULL DEFAULT 0,
			`is_active` TINYINT(1) NOT NULL DEFAULT 1,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_table_name` (`name`)
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `customers` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`name` VARCHAR(100) NOT NULL,
			`phone` VARCHAR(30) NULL,
			`email` VARCHAR(150) NULL,
			`address` VARCHAR(255) NULL,
			`is_member` TINYINT(1) NOT NULL DEFAULT 0,
			`member_since` DATE NULL,
			`notes` VARCHAR(255) NULL,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_customer_phone` (`phone`),
			KEY `idx_customer_name` (`name`)
		) $engine");

		// Topping / tambahan berbayar (PRD 2.4.2 Step 2: "Topping Telur +3K").
		$this->db->query("CREATE TABLE IF NOT EXISTS `modifiers` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`name` VARCHAR(100) NOT NULL,
			`price` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`ingredient_id` INT UNSIGNED NULL,
			`qty_std` DECIMAL(15,4) NULL COMMENT 'pemakaian bahan per 1 tambahan',
			`sort_order` INT NOT NULL DEFAULT 0,
			`is_active` TINYINT(1) NOT NULL DEFAULT 1,
			PRIMARY KEY (`id`),
			CONSTRAINT `fk_mod_ingredient` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE SET NULL
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `shift_templates` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`name` VARCHAR(50) NOT NULL,
			`start_time` TIME NOT NULL,
			`end_time` TIME NOT NULL,
			PRIMARY KEY (`id`)
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `shifts` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`user_id` INT UNSIGNED NOT NULL,
			`register_name` VARCHAR(50) NOT NULL DEFAULT 'Kasir 1',
			`shift_name` VARCHAR(50) NULL,
			`opened_at` DATETIME NOT NULL,
			`opening_balance` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`closed_at` DATETIME NULL,
			`closing_balance` DECIMAL(15,2) NULL,
			`expected_cash` DECIMAL(15,2) NULL,
			`variance` DECIMAL(15,2) NULL,
			`status` ENUM('open','pending_approval','closed') NOT NULL DEFAULT 'open',
			`close_note` VARCHAR(255) NULL,
			`approved_by` INT UNSIGNED NULL,
			`approved_at` DATETIME NULL,
			`approval_note` VARCHAR(255) NULL,
			PRIMARY KEY (`id`),
			KEY `idx_shift_user` (`user_id`, `status`),
			KEY `idx_shift_opened` (`opened_at`)
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `orders` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`order_number` VARCHAR(30) NOT NULL,
			`order_type` ENUM('dine_in','takeaway','delivery') NOT NULL,
			`table_id` INT UNSIGNED NULL,
			`guest_count` SMALLINT UNSIGNED NULL,
			`customer_id` INT UNSIGNED NULL,
			`customer_name` VARCHAR(100) NULL,
			`customer_phone` VARCHAR(30) NULL,
			`delivery_address` VARCHAR(255) NULL,
			`delivery_time` DATETIME NULL,
			`delivered_at` DATETIME NULL,
			`is_member` TINYINT(1) NOT NULL DEFAULT 0,
			`status` ENUM('open','paid','void') NOT NULL DEFAULT 'open',
			`notes` VARCHAR(255) NULL,
			`promo_codes` VARCHAR(100) NULL,
			`subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`discount_total` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`service_charge` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`tax` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`total` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`cogs_total` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`payment_method` ENUM('cash','debit','credit','ewallet') NULL,
			`paid_amount` DECIMAL(15,2) NULL,
			`change_amount` DECIMAL(15,2) NULL,
			`card_type` VARCHAR(20) NULL,
			`card_last4` CHAR(4) NULL,
			`approval_code` VARCHAR(30) NULL,
			`payment_ref` VARCHAR(100) NULL,
			`shift_id` INT UNSIGNED NULL,
			`created_by` INT UNSIGNED NULL,
			`paid_by` INT UNSIGNED NULL,
			`paid_at` DATETIME NULL,
			`void_by` INT UNSIGNED NULL,
			`void_at` DATETIME NULL,
			`void_reason` VARCHAR(255) NULL,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_order_number` (`order_number`),
			KEY `idx_order_status` (`status`, `created_at`),
			KEY `idx_order_paid` (`paid_at`),
			KEY `idx_order_shift` (`shift_id`),
			KEY `idx_order_table` (`table_id`, `status`),
			KEY `idx_order_customer` (`customer_id`),
			CONSTRAINT `fk_order_table` FOREIGN KEY (`table_id`) REFERENCES `dining_tables` (`id`) ON DELETE SET NULL,
			CONSTRAINT `fk_order_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
			CONSTRAINT `fk_order_shift` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE SET NULL
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `order_items` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`order_id` INT UNSIGNED NOT NULL,
			`variant_id` INT UNSIGNED NOT NULL,
			`menu_id` INT UNSIGNED NOT NULL,
			`name` VARCHAR(200) NOT NULL,
			`qty` SMALLINT UNSIGNED NOT NULL,
			`base_price` DECIMAL(15,2) NOT NULL COMMENT 'harga varian (normal/member/grosir)',
			`modifiers` TEXT NULL COMMENT 'JSON [{id,name,price}]',
			`modifiers_price` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`unit_price` DECIMAL(15,2) NOT NULL COMMENT 'base + tambahan',
			`line_total` DECIMAL(15,2) NOT NULL,
			`discount` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`notes` VARCHAR(255) NULL,
			`kitchen_status` ENUM('pending','preparing','ready','served','void') NOT NULL DEFAULT 'pending',
			`cogs` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`stock_shortage` TINYINT(1) NOT NULL DEFAULT 0,
			`void_reason` VARCHAR(255) NULL,
			`void_by` INT UNSIGNED NULL,
			`stock_returned` TINYINT(1) NOT NULL DEFAULT 0,
			`created_by` INT UNSIGNED NULL,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`preparing_at` DATETIME NULL,
			`ready_at` DATETIME NULL,
			`served_at` DATETIME NULL,
			PRIMARY KEY (`id`),
			KEY `idx_oi_order` (`order_id`),
			KEY `idx_oi_kitchen` (`kitchen_status`, `created_at`),
			KEY `idx_oi_variant` (`variant_id`),
			CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
			CONSTRAINT `fk_oi_variant` FOREIGN KEY (`variant_id`) REFERENCES `menu_variants` (`id`) ON DELETE RESTRICT
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `order_promos` (
			`order_id` INT UNSIGNED NOT NULL,
			`promo_id` INT UNSIGNED NOT NULL,
			`name` VARCHAR(150) NOT NULL,
			`discount` DECIMAL(15,2) NOT NULL,
			PRIMARY KEY (`order_id`, `promo_id`),
			KEY `idx_op_promo` (`promo_id`),
			CONSTRAINT `fk_op_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
		) $engine");

		$this->db->query("INSERT INTO shift_templates (name, start_time, end_time) VALUES
			('Pagi', '08:00:00', '16:00:00'), ('Siang', '12:00:00', '20:00:00'), ('Malam', '18:00:00', '02:00:00')");
	}

	public function down()
	{
		foreach (array('order_promos', 'order_items', 'orders', 'shifts', 'shift_templates', 'modifiers', 'customers', 'dining_tables') as $table)
		{
			$this->db->query("DROP TABLE IF EXISTS `$table`");
		}
	}
}
