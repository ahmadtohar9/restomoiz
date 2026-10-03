<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * HPP manual per varian menu: bila diisi, dipakai menggantikan HPP dari resep
 * (analisis margin & COGS transaksi). Stok tetap dipotong sesuai resep.
 */
class Migration_Add_manual_cogs extends CI_Migration {

	public function up()
	{
		$cols = array_column($this->db->query("SHOW COLUMNS FROM `menu_variants`")->result_array(), 'Field');
		if ( ! in_array('cogs_manual', $cols, TRUE))
		{
			$this->db->query("ALTER TABLE `menu_variants`
				ADD COLUMN `cogs_manual` DECIMAL(15,2) NULL AFTER `is_active`,
				ADD COLUMN `cogs_manual_note` VARCHAR(255) NULL AFTER `cogs_manual`,
				ADD COLUMN `cogs_manual_by` INT UNSIGNED NULL AFTER `cogs_manual_note`,
				ADD COLUMN `cogs_manual_at` DATETIME NULL AFTER `cogs_manual_by`");
		}
	}

	public function down()
	{
		$this->db->query("ALTER TABLE `menu_variants` DROP COLUMN `cogs_manual`, DROP COLUMN `cogs_manual_note`, DROP COLUMN `cogs_manual_by`, DROP COLUMN `cogs_manual_at`");
	}
}
