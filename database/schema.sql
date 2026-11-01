-- ============================================================
-- NChanger Server Database Schema (v1.1.0)
-- Target: MySQL 8.0+ / MariaDB 10.4+
-- Engine: InnoDB | Charset: utf8mb4_unicode_ci
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- 1. USERS & ROLES
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('super_admin', 'admin', 'support', 'readonly') NOT NULL DEFAULT 'readonly',
  `status` ENUM('active', 'suspended', 'pending') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. ACCESS TOKENS & SESSIONS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `access_tokens`;
CREATE TABLE `access_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token_hash` VARCHAR(64) NOT NULL UNIQUE,
  `type` ENUM('access', 'refresh') NOT NULL DEFAULT 'access',
  `expires_at` TIMESTAMP NOT NULL,
  `revoked` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_tokens_lookup` (`token_hash`, `revoked`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. LICENSES
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `licenses`;
CREATE TABLE `licenses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `license_key` VARCHAR(64) NOT NULL UNIQUE,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `plan` VARCHAR(50) NOT NULL DEFAULT 'standard',
  `status` ENUM('active', 'expired', 'disabled', 'pending') NOT NULL DEFAULT 'pending',
  `max_devices` INT UNSIGNED NOT NULL DEFAULT 1,
  `activated_at` TIMESTAMP NULL DEFAULT NULL,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_licenses_key_status` (`license_key`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. BRANDS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `brands`;
CREATE TABLE `brands` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL UNIQUE,
  `status` ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. HARDWARE MODELS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `models`;
CREATE TABLE `models` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `brand_id` INT UNSIGNED NOT NULL,
  `model_code` VARCHAR(120) NOT NULL,
  `marketing_name` VARCHAR(160) NOT NULL,
  `codename` VARCHAR(120) DEFAULT NULL,
  `soc` VARCHAR(120) DEFAULT NULL,
  `architecture` VARCHAR(30) DEFAULT NULL,
  `android_min` VARCHAR(20) DEFAULT NULL,
  `android_max` VARCHAR(20) DEFAULT NULL,
  `status` ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_brand_model` (`brand_id`, `model_code`),
  INDEX `idx_codename` (`codename`),
  INDEX `idx_status` (`status`),
  FOREIGN KEY (`brand_id`) REFERENCES `brands`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. ROM PROFILES
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `rom_profiles`;
CREATE TABLE `rom_profiles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `model_id` INT UNSIGNED NOT NULL,
  `profile_code` VARCHAR(64) NOT NULL UNIQUE,
  `name` VARCHAR(160) NOT NULL,
  `version` VARCHAR(50) NOT NULL DEFAULT '1.0.0',
  `android_version` VARCHAR(20) NOT NULL,
  `api_level` INT UNSIGNED NOT NULL,
  `build_id` VARCHAR(100) DEFAULT NULL,
  `security_patch` VARCHAR(30) DEFAULT NULL,
  `system_layout` VARCHAR(50) DEFAULT 'raw/system',
  `vendor_layout` VARCHAR(50) DEFAULT 'raw/vendor',
  `recovery_type` VARCHAR(50) DEFAULT 'recovery',
  `nchanger_api` INT UNSIGNED NOT NULL DEFAULT 1,
  `manifest_json` JSON DEFAULT NULL,
  `status` ENUM('active', 'pending_validation', 'disabled') NOT NULL DEFAULT 'pending_validation',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`model_id`) REFERENCES `models`(`id`) ON DELETE RESTRICT,
  INDEX `idx_rom_model_id` (`model_id`),
  INDEX `idx_rom_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. CAPABILITY PROFILES
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `capability_profiles`;
CREATE TABLE `capability_profiles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rom_id` INT UNSIGNED NOT NULL,
  `capabilities_json` JSON NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_rom_capability` (`rom_id`),
  FOREIGN KEY (`rom_id`) REFERENCES `rom_profiles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 8. CHANGE PROFILES (Operational workflows & Policies)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `change_profiles`;
CREATE TABLE `change_profiles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `model_id` INT UNSIGNED NOT NULL,
  `rom_profile_id` INT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(160) NOT NULL,
  `version` VARCHAR(40) NOT NULL DEFAULT '1.0.0',
  `status` ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
  `workflow_code` VARCHAR(80) NOT NULL,
  `policy_json` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_model` (`model_id`),
  INDEX `idx_rom` (`rom_profile_id`),
  INDEX `idx_workflow` (`workflow_code`),
  FOREIGN KEY (`model_id`) REFERENCES `models`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`rom_profile_id`) REFERENCES `rom_profiles`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 9. DEVICES & SESSIONS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `devices`;
CREATE TABLE `devices` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `device_uuid` VARCHAR(64) NOT NULL UNIQUE,
  `license_id` INT UNSIGNED DEFAULT NULL,
  `model_id` INT UNSIGNED DEFAULT NULL,
  `rom_id` INT UNSIGNED DEFAULT NULL,
  `client_version` VARCHAR(30) NOT NULL,
  `platform` VARCHAR(30) NOT NULL DEFAULT 'windows',
  `status` ENUM('online', 'offline', 'blocked') NOT NULL DEFAULT 'offline',
  `last_ip` VARCHAR(45) DEFAULT NULL,
  `last_seen_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`license_id`) REFERENCES `licenses`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`model_id`) REFERENCES `models`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`rom_id`) REFERENCES `rom_profiles`(`id`) ON DELETE SET NULL,
  INDEX `idx_device_uuid` (`device_uuid`),
  INDEX `idx_device_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 10. BACKUPS & RESTORES (Metadata only)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `backups`;
CREATE TABLE `backups` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `backup_code` VARCHAR(64) NOT NULL UNIQUE,
  `device_id` INT UNSIGNED NOT NULL,
  `model_id` INT UNSIGNED NOT NULL,
  `rom_id` INT UNSIGNED NOT NULL,
  `backup_type` VARCHAR(50) NOT NULL DEFAULT 'user_data',
  `size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `sha256` VARCHAR(64) DEFAULT NULL,
  `storage_path` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending', 'verified', 'corrupt', 'deleted') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`model_id`) REFERENCES `models`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`rom_id`) REFERENCES `rom_profiles`(`id`) ON DELETE RESTRICT,
  INDEX `idx_backups_device` (`device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `restore_jobs`;
CREATE TABLE `restore_jobs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_code` VARCHAR(64) NOT NULL UNIQUE,
  `device_id` INT UNSIGNED NOT NULL,
  `backup_id` INT UNSIGNED NOT NULL,
  `status` ENUM('prepared', 'in_progress', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'prepared',
  `error_message` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`backup_id`) REFERENCES `backups`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 11. AUDIT & LOGGING
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `device_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `target_type` VARCHAR(50) DEFAULT NULL,
  `target_id` VARCHAR(64) DEFAULT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `metadata_json` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_audit_action` (`action`),
  INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 12. SYSTEM SETTINGS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
