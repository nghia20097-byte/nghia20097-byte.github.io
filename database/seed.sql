-- ============================================================
-- NChanger Server Seed Data (v1.1.0)
-- Target: Testing & Initial Setup
-- ============================================================

-- 1. Default Super Admin User
INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role`, `status`)
VALUES (
  1,
  'admin',
  'admin@nchanger.local',
  '$2y$10$e8wF5q3a5sHh0F0Gk9a1.OHj5e7D8lU1Gf0oZzW4sYV6d2F0tqTKa',
  'super_admin',
  'active'
) ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);

-- 2. Brands
INSERT INTO `brands` (`id`, `name`, `status`)
VALUES
  (1, 'Samsung', 'active'),
  (2, 'Google', 'active'),
  (3, 'Xiaomi', 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 3. Hardware Model: Samsung Galaxy S9+ (star2lte)
INSERT INTO `models` (
  `id`, `brand_id`, `model_code`, `marketing_name`, `codename`, `soc`, `architecture`, `android_min`, `android_max`, `status`
) VALUES (
  1,
  1,
  'SM-G965F',
  'Galaxy S9+',
  'star2lte',
  'Exynos9810',
  'arm64',
  '12',
  '12.1',
  'active'
) ON DUPLICATE KEY UPDATE `model_code` = VALUES(`model_code`);

-- 4. ROM Profile: NChangerOS PixelExperience A12 baseline
INSERT INTO `rom_profiles` (
  `id`, `model_id`, `profile_code`, `name`, `version`, `android_version`, `api_level`, `build_id`, `security_patch`, `system_layout`, `vendor_layout`, `recovery_type`, `nchanger_api`, `manifest_json`, `status`
) VALUES (
  1,
  1,
  'rom_star2lte_a12',
  'NChangerOS PixelExperience A12 baseline',
  '1.0.0',
  '12.1',
  32,
  'SQ3A.220705.004',
  '2022-11-05',
  'raw/system',
  'raw/vendor',
  'recovery',
  1,
  JSON_OBJECT(
    'base', 'PixelExperience Plus 12.1',
    'kernel', '4.9.x',
    'selinux', 'enforcing',
    'fingerprint', 'samsung/star2ltexx/star2lte:10/QP1A.190711.020/G965FXXUHFVG4:user/release-keys'
  ),
  'pending_validation'
) ON DUPLICATE KEY UPDATE `profile_code` = VALUES(`profile_code`);

-- 5. Capability Profile for rom_star2lte_a12
INSERT INTO `capability_profiles` (`id`, `rom_id`, `capabilities_json`)
VALUES (
  1,
  1,
  JSON_OBJECT(
    'backup', true,
    'restore', false,
    'recovery', true,
    'diagnostics', true,
    'app_install', true,
    'safe_mode_fallback', true
  )
) ON DUPLICATE KEY UPDATE `capabilities_json` = VALUES(`capabilities_json`);

-- 6. Change Profile with Workflow & Policy
INSERT INTO `change_profiles` (
  `id`, `model_id`, `rom_profile_id`, `name`, `version`, `status`, `workflow_code`, `policy_json`
) VALUES (
  1,
  1,
  1,
  'NChangerOS Star2LTE A12',
  '1.0.0',
  'active',
  'NCHANGER_STANDARD_V1',
  JSON_OBJECT(
    'backup', JSON_OBJECT('enabled', true, 'required_before_workflow', true),
    'restore', JSON_OBJECT('enabled', true),
    'diagnostics', JSON_OBJECT('enabled', true),
    'recovery', JSON_OBJECT('enabled', true),
    'app_install', JSON_OBJECT('enabled', true),
    'rom_check', JSON_OBJECT('required', true),
    'safe_mode', JSON_OBJECT('enabled', true),
    'workflow', JSON_OBJECT('timeout', 1800, 'retry', 2)
  )
) ON DUPLICATE KEY UPDATE `workflow_code` = VALUES(`workflow_code`), `policy_json` = VALUES(`policy_json`);

-- 7. Sample Licenses
INSERT INTO `licenses` (`id`, `license_key`, `user_id`, `plan`, `status`, `max_devices`, `expires_at`, `notes`)
VALUES (
  1,
  'NC-TEST-8888-9999',
  1,
  'pro',
  'active',
  3,
  '2027-01-01 00:00:00',
  'Development & testing key'
) ON DUPLICATE KEY UPDATE `license_key` = VALUES(`license_key`);

-- 8. Sample Connected Device
INSERT INTO `devices` (`id`, `device_uuid`, `license_id`, `model_id`, `rom_id`, `client_version`, `platform`, `status`, `last_ip`, `last_seen_at`)
VALUES (
  1,
  'ncd_8f92a10b4c8e71234567890abcdef1',
  1,
  1,
  1,
  '1.0.0',
  'windows',
  'online',
  '127.0.0.1',
  NOW()
) ON DUPLICATE KEY UPDATE `device_uuid` = VALUES(`device_uuid`);

-- 9. Sample Backups
INSERT INTO `backups` (`id`, `backup_code`, `device_id`, `model_id`, `rom_id`, `backup_type`, `size_bytes`, `sha256`, `status`)
VALUES (
  1,
  'bkp_01h89fa12b3c4d5e',
  1,
  1,
  1,
  'user_data',
  256901120,
  'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
  'verified'
) ON DUPLICATE KEY UPDATE `backup_code` = VALUES(`backup_code`);

-- 10. System Settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`)
VALUES
  ('api_version', '1.0.0', 'Current API release version'),
  ('min_client_version', '1.0.0', 'Minimum supported NChanger.exe client version'),
  ('latest_client_version', '1.0.0', 'Latest available NChanger.exe version'),
  ('client_download_url', 'https://domain.com/downloads/NChanger-v1.0.0.zip', 'Download URL for latest Windows client'),
  ('client_changelog', 'Initial release with Galaxy S9+ (star2lte) support and Safe Mode.', 'Release notes for update modal'),
  ('maintenance_mode', 'false', 'Toggle API maintenance mode (true/false)'),
  ('safe_mode_enforce', 'true', 'Enforce safe mode on unvalidated ROMs')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
