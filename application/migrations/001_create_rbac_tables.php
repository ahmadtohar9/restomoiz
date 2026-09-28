<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Fase 1: fondasi RBAC (PRD bagian 3 & 4.1).
 *
 * Beda dengan ERD di PRD: role user disimpan di tabel user_roles (bukan
 * users.role_id) supaya satu user bisa punya beberapa role sesuai PRD 3.1.
 */
class Migration_Create_rbac_tables extends CI_Migration {

	public function up()
	{
		$engine = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

		$this->db->query("CREATE TABLE IF NOT EXISTS `roles` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`code` VARCHAR(50) NOT NULL,
			`name` VARCHAR(100) NOT NULL,
			`description` VARCHAR(255) NULL,
			`is_super` TINYINT(1) NOT NULL DEFAULT 0,
			`is_system` TINYINT(1) NOT NULL DEFAULT 0,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_roles_code` (`code`),
			UNIQUE KEY `uq_roles_name` (`name`)
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `permissions` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`name` VARCHAR(100) NOT NULL,
			`module` VARCHAR(50) NOT NULL,
			`description` VARCHAR(255) NULL,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_permissions_name` (`name`),
			KEY `idx_permissions_module` (`module`)
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `role_permissions` (
			`role_id` INT UNSIGNED NOT NULL,
			`permission_id` INT UNSIGNED NOT NULL,
			PRIMARY KEY (`role_id`, `permission_id`),
			CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
			CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `users` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`username` VARCHAR(50) NOT NULL,
			`name` VARCHAR(100) NOT NULL,
			`email` VARCHAR(150) NULL,
			`password_hash` VARCHAR(255) NOT NULL,
			`is_active` TINYINT(1) NOT NULL DEFAULT 1,
			`must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
			`last_login` DATETIME NULL,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_users_username` (`username`),
			UNIQUE KEY `uq_users_email` (`email`)
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `user_roles` (
			`user_id` INT UNSIGNED NOT NULL,
			`role_id` INT UNSIGNED NOT NULL,
			`is_primary` TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`user_id`, `role_id`),
			KEY `idx_ur_role` (`role_id`),
			CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
			CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `user_permissions` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`user_id` INT UNSIGNED NOT NULL,
			`permission_id` INT UNSIGNED NOT NULL,
			`expiry_date` DATE NULL,
			`reason` VARCHAR(255) NULL,
			`granted_by` INT UNSIGNED NULL,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_up_user_perm` (`user_id`, `permission_id`),
			CONSTRAINT `fk_up_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
			CONSTRAINT `fk_up_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `audit_log` (
			`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`user_id` INT UNSIGNED NULL,
			`username` VARCHAR(50) NULL,
			`action` VARCHAR(100) NOT NULL,
			`result` ENUM('success','denied','failed') NOT NULL DEFAULT 'success',
			`table_name` VARCHAR(64) NULL,
			`record_id` VARCHAR(64) NULL,
			`detail` TEXT NULL,
			`ip_address` VARCHAR(45) NULL,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			KEY `idx_audit_created` (`created_at`),
			KEY `idx_audit_user` (`user_id`),
			KEY `idx_audit_action` (`action`)
		) $engine");

		$this->db->query("CREATE TABLE IF NOT EXISTS `app_settings` (
			`key` VARCHAR(100) NOT NULL,
			`value` TEXT NULL,
			`description` VARCHAR(255) NULL,
			`updated_by` INT UNSIGNED NULL,
			`updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`key`)
		) $engine");
	}

	public function down()
	{
		foreach (array('app_settings', 'audit_log', 'user_permissions', 'user_roles', 'users', 'role_permissions', 'permissions', 'roles') as $table)
		{
			$this->db->query("DROP TABLE IF EXISTS `$table`");
		}
	}
}
