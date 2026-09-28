<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Fase 3: Pembelian & pengeluaran (PRD 2.2).
 *
 * Alur: PO (draft -> submitted -> approved) -> Goods Receipt (stok masuk FIFO)
 *       -> Invoice supplier (3-way match) -> Pembayaran (verifikasi).
 *
 * Qty disimpan dua kali: sesuai satuan input (qty, unit) dan dalam satuan
 * standar bahan (qty_std) karena stok selalu dalam satuan standar.
 */
class Migration_Create_purchase_tables extends CI_Migration {

	public function up()
	{
		$engine = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

		$this->db->query("CREATE TABLE IF NOT EXISTS `purchase_orders` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`po_number` VARCHAR(30) NOT NULL,
			`supplier_id` INT UNSIGNED NOT NULL,
			`po_date` DATE NOT NULL,
			`expected_delivery` DATE NULL,
			`delivery_address` VARCHAR(255) NULL,
			`payment_terms` VARCHAR(20) NOT NULL DEFAULT 'COD',
			`notes` TEXT NULL,
			`status` ENUM('draft','submitted','approved','partial','received','closed','cancelled') NOT NULL DEFAULT 'draft',
			`subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`discount_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`tax_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`approval_level` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = auto, 1 = manajer, 2 = manajer + owner',
			`created_by` INT UNSIGNED NULL,
			`submitted_at` DATETIME NULL,
			`approved1_by` INT UNSIGNED NULL,
			`approved1_at` DATETIME NULL,
			`approved2_by` INT UNSIGNED NULL,
			`approved2_at` DATETIME NULL,
			`closed_by` INT UNSIGNED NULL,
			`closed_at` DATETIME NULL,
			`close_reason` VARCHAR(255) NULL,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_po_number` (`po_number`),
			KEY `idx_po_status` (`status`),
			KEY `idx_po_supplier` (`supplier_id`, `po_date`),
			CONSTRAINT `fk_po_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `purchase_order_items` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`po_id` INT UNSIGNED NOT NULL,
			`ingredient_id` INT UNSIGNED NOT NULL,
			`qty` DECIMAL(15,3) NOT NULL,
			`unit` VARCHAR(20) NOT NULL,
			`factor` DECIMAL(18,6) NOT NULL DEFAULT 1,
			`qty_std` DECIMAL(15,3) NOT NULL,
			`unit_price` DECIMAL(15,2) NOT NULL COMMENT 'per satuan input',
			`subtotal` DECIMAL(15,2) NOT NULL,
			`qty_received_std` DECIMAL(15,3) NOT NULL DEFAULT 0,
			`notes` VARCHAR(255) NULL,
			PRIMARY KEY (`id`),
			KEY `idx_poi_po` (`po_id`),
			KEY `idx_poi_ingredient` (`ingredient_id`),
			CONSTRAINT `fk_poi_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
			CONSTRAINT `fk_poi_ingredient` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE RESTRICT
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `po_history` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`po_id` INT UNSIGNED NOT NULL,
			`action` VARCHAR(30) NOT NULL,
			`note` VARCHAR(255) NULL,
			`user_id` INT UNSIGNED NULL,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			KEY `idx_poh_po` (`po_id`),
			CONSTRAINT `fk_poh_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `goods_receipts` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`gr_number` VARCHAR(30) NOT NULL,
			`po_id` INT UNSIGNED NOT NULL,
			`received_date` DATE NOT NULL,
			`delivery_note_no` VARCHAR(50) NULL,
			`notes` VARCHAR(255) NULL,
			`attachment_path` VARCHAR(255) NULL,
			`movement_no` VARCHAR(30) NULL,
			`amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`received_by` INT UNSIGNED NULL,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_gr_number` (`gr_number`),
			KEY `idx_gr_po` (`po_id`),
			CONSTRAINT `fk_gr_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE RESTRICT
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `goods_receipt_items` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`gr_id` INT UNSIGNED NOT NULL,
			`po_item_id` INT UNSIGNED NOT NULL,
			`qty_received` DECIMAL(15,3) NOT NULL COMMENT 'dalam satuan PO',
			`qty_received_std` DECIMAL(15,3) NOT NULL,
			`qty_expected_std` DECIMAL(15,3) NOT NULL COMMENT 'sisa PO saat diterima',
			`variance_reason` VARCHAR(255) NULL,
			`expiry_date` DATE NULL,
			`amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`movement_id` BIGINT UNSIGNED NULL,
			PRIMARY KEY (`id`),
			KEY `idx_gri_gr` (`gr_id`),
			CONSTRAINT `fk_gri_gr` FOREIGN KEY (`gr_id`) REFERENCES `goods_receipts` (`id`) ON DELETE CASCADE,
			CONSTRAINT `fk_gri_poi` FOREIGN KEY (`po_item_id`) REFERENCES `purchase_order_items` (`id`) ON DELETE RESTRICT
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `supplier_invoices` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`invoice_number` VARCHAR(50) NOT NULL,
			`supplier_id` INT UNSIGNED NOT NULL,
			`po_id` INT UNSIGNED NOT NULL,
			`invoice_date` DATE NOT NULL,
			`due_date` DATE NOT NULL,
			`amount` DECIMAL(15,2) NOT NULL,
			`attachment_path` VARCHAR(255) NULL,
			`status` ENUM('pending','approved','on_hold','rejected') NOT NULL DEFAULT 'pending',
			`match_status` ENUM('matched','mismatch') NOT NULL,
			`po_amount` DECIMAL(15,2) NOT NULL COMMENT 'snapshot saat invoice dicatat',
			`gr_amount` DECIMAL(15,2) NOT NULL COMMENT 'snapshot nilai barang diterima',
			`invoiced_before` DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'total invoice lain untuk PO ini',
			`notes` VARCHAR(255) NULL,
			`review_note` VARCHAR(255) NULL,
			`paid_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`payment_status` ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
			`created_by` INT UNSIGNED NULL,
			`reviewed_by` INT UNSIGNED NULL,
			`reviewed_at` DATETIME NULL,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_supplier_invoice` (`supplier_id`, `invoice_number`),
			KEY `idx_inv_po` (`po_id`),
			KEY `idx_inv_status` (`status`, `payment_status`),
			KEY `idx_inv_due` (`due_date`),
			CONSTRAINT `fk_inv_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
			CONSTRAINT `fk_inv_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE RESTRICT
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `supplier_payments` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`payment_number` VARCHAR(30) NOT NULL,
			`invoice_id` INT UNSIGNED NOT NULL,
			`payment_date` DATE NOT NULL,
			`amount` DECIMAL(15,2) NOT NULL,
			`method` ENUM('cash','transfer','credit_card','check') NOT NULL,
			`bank_name` VARCHAR(50) NULL,
			`account_no` VARCHAR(50) NULL,
			`reference_no` VARCHAR(100) NULL,
			`card_last4` CHAR(4) NULL,
			`auth_code` VARCHAR(20) NULL,
			`check_no` VARCHAR(50) NULL,
			`check_date` DATE NULL,
			`proof_path` VARCHAR(255) NULL,
			`notes` VARCHAR(255) NULL,
			`status` ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
			`reject_reason` VARCHAR(255) NULL,
			`created_by` INT UNSIGNED NULL,
			`verified_by` INT UNSIGNED NULL,
			`verified_at` DATETIME NULL,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_payment_number` (`payment_number`),
			KEY `idx_pay_invoice` (`invoice_id`),
			KEY `idx_pay_status` (`status`),
			CONSTRAINT `fk_pay_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `supplier_invoices` (`id`) ON DELETE RESTRICT
		) $engine");
	}

	public function down()
	{
		foreach (array('supplier_payments', 'supplier_invoices', 'goods_receipt_items', 'goods_receipts', 'po_history', 'purchase_order_items', 'purchase_orders') as $table)
		{
			$this->db->query("DROP TABLE IF EXISTS `$table`");
		}
	}
}
