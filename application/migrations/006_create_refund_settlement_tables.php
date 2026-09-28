<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Fase 6: Refund & Settlement (PRD 2.4.4 - 2.4.5).
 */
class Migration_Create_refund_settlement_tables extends CI_Migration {

	public function up()
	{
		$engine = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

		$this->db->query("ALTER TABLE `orders`
			ADD COLUMN `refund_status` ENUM('none','partial','full') NOT NULL DEFAULT 'none' AFTER `status`,
			ADD COLUMN `refunded_amount` DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER `refund_status`");

		$this->db->query("CREATE TABLE IF NOT EXISTS `order_refunds` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`refund_number` VARCHAR(30) NOT NULL,
			`order_id` INT UNSIGNED NOT NULL,
			`refund_method` ENUM('cash','original') NOT NULL,
			`refund_amount` DECIMAL(15,2) NOT NULL,
			`base_amount` DECIMAL(15,2) NOT NULL COMMENT 'nilai item setelah diskon',
			`service_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`tax_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`reason_code` VARCHAR(20) NOT NULL,
			`reason` VARCHAR(255) NOT NULL,
			`restock` TINYINT(1) NOT NULL DEFAULT 1,
			`required_level` ENUM('cashier','manager','owner') NOT NULL,
			`status` ENUM('pending_approval','approved','completed','rejected') NOT NULL,
			`is_full` TINYINT(1) NOT NULL DEFAULT 0,
			`shift_id` INT UNSIGNED NULL COMMENT 'shift yang mengeluarkan uang tunai',
			`payment_ref` VARCHAR(100) NULL COMMENT 'referensi reversal kartu / e-wallet',
			`requested_by` INT UNSIGNED NOT NULL,
			`requested_at` DATETIME NOT NULL,
			`approved_by` INT UNSIGNED NULL,
			`approved_at` DATETIME NULL,
			`approval_note` VARCHAR(255) NULL,
			`completed_by` INT UNSIGNED NULL,
			`completed_at` DATETIME NULL,
			`cogs_reversed` DECIMAL(15,2) NOT NULL DEFAULT 0,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_refund_number` (`refund_number`),
			KEY `idx_refund_order` (`order_id`),
			KEY `idx_refund_status` (`status`, `requested_at`),
			KEY `idx_refund_shift` (`shift_id`),
			CONSTRAINT `fk_refund_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
			CONSTRAINT `fk_refund_shift` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE SET NULL
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `order_refund_items` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`refund_id` INT UNSIGNED NOT NULL,
			`order_item_id` INT UNSIGNED NOT NULL,
			`qty` SMALLINT UNSIGNED NOT NULL,
			`amount` DECIMAL(15,2) NOT NULL,
			`cogs_reversed` DECIMAL(15,2) NOT NULL DEFAULT 0,
			PRIMARY KEY (`id`),
			KEY `idx_ori_refund` (`refund_id`),
			KEY `idx_ori_item` (`order_item_id`),
			CONSTRAINT `fk_ori_refund` FOREIGN KEY (`refund_id`) REFERENCES `order_refunds` (`id`) ON DELETE CASCADE,
			CONSTRAINT `fk_ori_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE RESTRICT
		) $engine");

		// Settlement harian (PRD 2.4.5 "Post-Shift (Accounting - next day)").
		$this->db->query("CREATE TABLE IF NOT EXISTS `daily_settlements` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`settle_date` DATE NOT NULL,
			`expected_cash` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`expected_card` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`expected_ewallet` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`shift_variance` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`actual_cash_deposit` DECIMAL(15,2) NULL,
			`actual_card` DECIMAL(15,2) NULL,
			`actual_ewallet` DECIMAL(15,2) NULL,
			`fees` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`variance` DECIMAL(15,2) NULL,
			`notes` VARCHAR(255) NULL,
			`status` ENUM('draft','verified') NOT NULL DEFAULT 'draft',
			`created_by` INT UNSIGNED NULL,
			`verified_by` INT UNSIGNED NULL,
			`verified_at` DATETIME NULL,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_settle_date` (`settle_date`)
		) $engine");

		// Rekonsiliasi bank bulanan (PRD 2.4.5 "Monthly Bank Reconciliation").
		$this->db->query("CREATE TABLE IF NOT EXISTS `bank_reconciliations` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`period` CHAR(7) NOT NULL COMMENT 'YYYY-MM',
			`bank_name` VARCHAR(50) NULL,
			`expected_deposit` DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'setoran tunai + settlement kartu & e-wallet menurut POS',
			`bank_credits` DECIMAL(15,2) NULL COMMENT 'total mutasi masuk di rekening koran',
			`in_transit` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`fees` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`other_adjustment` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`variance` DECIMAL(15,2) NULL,
			`notes` TEXT NULL,
			`attachment_path` VARCHAR(255) NULL,
			`status` ENUM('draft','reconciled') NOT NULL DEFAULT 'draft',
			`created_by` INT UNSIGNED NULL,
			`reconciled_by` INT UNSIGNED NULL,
			`reconciled_at` DATETIME NULL,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_recon_period` (`period`)
		) $engine");
	}

	public function down()
	{
		$this->db->query('DROP TABLE IF EXISTS `bank_reconciliations`');
		$this->db->query('DROP TABLE IF EXISTS `daily_settlements`');
		$this->db->query('DROP TABLE IF EXISTS `order_refund_items`');
		$this->db->query('DROP TABLE IF EXISTS `order_refunds`');
		$this->db->query('ALTER TABLE `orders` DROP COLUMN `refund_status`, DROP COLUMN `refunded_amount`');
	}
}
