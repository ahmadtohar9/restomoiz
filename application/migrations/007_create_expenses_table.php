<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Fase 7: pengeluaran operasional untuk Laporan Laba Rugi (PRD 2.5.2
 * "Operating Expenses": payroll, rent/utility, marketing, maintenance,
 * supplies, depreciation, other + interest & other expense).
 */
class Migration_Create_expenses_table extends CI_Migration {

	public function up()
	{
		$this->db->query("CREATE TABLE IF NOT EXISTS `expenses` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`expense_date` DATE NOT NULL,
			`category` VARCHAR(30) NOT NULL,
			`description` VARCHAR(255) NOT NULL,
			`amount` DECIMAL(15,2) NOT NULL,
			`payment_method` VARCHAR(20) NULL,
			`attachment_path` VARCHAR(255) NULL,
			`created_by` INT UNSIGNED NULL,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			KEY `idx_expense_date` (`expense_date`, `category`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	}

	public function down()
	{
		$this->db->query('DROP TABLE IF EXISTS `expenses`');
	}
}
