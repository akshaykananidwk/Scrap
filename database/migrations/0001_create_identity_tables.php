<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Migration;

return new class extends Migration {
    public function version(): string
    {
        return '0001';
    }

    public function description(): string
    {
        return 'Identity: roles, permissions, users, tokens, OTP, audit, settings';
    }

    public function up(Database $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS `roles` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `slug` VARCHAR(50) NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `description` VARCHAR(255) NULL,
            `is_staff` TINYINT(1) NOT NULL DEFAULT 0,
            `is_system` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_roles_slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `permissions` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `slug` VARCHAR(80) NOT NULL,
            `name` VARCHAR(120) NOT NULL,
            `module` VARCHAR(50) NOT NULL DEFAULT 'general',
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_permissions_slug` (`slug`),
            KEY `idx_permissions_module` (`module`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `role_permissions` (
            `role_id` INT UNSIGNED NOT NULL,
            `permission_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`role_id`, `permission_id`),
            KEY `idx_rp_permission` (`permission_id`),
            CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `users` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `uuid` CHAR(36) NOT NULL,
            `full_name` VARCHAR(150) NOT NULL,
            `email` VARCHAR(190) NULL,
            `mobile` VARCHAR(15) NOT NULL,
            `alt_mobile` VARCHAR(15) NULL,
            `password_hash` VARCHAR(255) NOT NULL,
            `account_type` ENUM('buyer','seller','both') NOT NULL DEFAULT 'both',
            `status` ENUM('pending','active','suspended','rejected','blocked') NOT NULL DEFAULT 'pending',
            `kyc_status` ENUM('none','pending','verified','rejected','expired') NOT NULL DEFAULT 'none',
            `avatar` VARCHAR(255) NULL,
            `designation` VARCHAR(100) NULL,
            `email_verified_at` DATETIME NULL,
            `mobile_verified_at` DATETIME NULL,
            `approved_at` DATETIME NULL,
            `approved_by` INT UNSIGNED NULL,
            `rejection_reason` VARCHAR(255) NULL,
            `last_login_at` DATETIME NULL,
            `last_active_at` DATETIME NULL,
            `preferred_language` VARCHAR(5) NOT NULL DEFAULT 'en',
            `notify_email` TINYINT(1) NOT NULL DEFAULT 1,
            `notify_sms` TINYINT(1) NOT NULL DEFAULT 1,
            `notify_whatsapp` TINYINT(1) NOT NULL DEFAULT 1,
            `risk_score` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            `is_demo` TINYINT(1) NOT NULL DEFAULT 0,
            `created_ip` VARCHAR(45) NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            `deleted_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_users_uuid` (`uuid`),
            UNIQUE KEY `uniq_users_mobile` (`mobile`),
            UNIQUE KEY `uniq_users_email` (`email`),
            KEY `idx_users_status` (`status`),
            KEY `idx_users_account_type` (`account_type`),
            KEY `idx_users_kyc_status` (`kyc_status`),
            KEY `idx_users_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `user_roles` (
            `user_id` INT UNSIGNED NOT NULL,
            `role_id` INT UNSIGNED NOT NULL,
            `assigned_at` DATETIME NOT NULL,
            PRIMARY KEY (`user_id`, `role_id`),
            KEY `idx_ur_role` (`role_id`),
            CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `api_tokens` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(60) NOT NULL DEFAULT 'api',
            `token_hash` CHAR(64) NOT NULL,
            `abilities` VARCHAR(255) NOT NULL DEFAULT '*',
            `last_used_at` DATETIME NULL,
            `expires_at` DATETIME NULL,
            `revoked_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_api_token_hash` (`token_hash`),
            KEY `idx_api_tokens_user` (`user_id`),
            CONSTRAINT `fk_api_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `otp_codes` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NULL,
            `channel` ENUM('sms','email','whatsapp') NOT NULL DEFAULT 'sms',
            `destination` VARCHAR(190) NOT NULL,
            `purpose` VARCHAR(40) NOT NULL DEFAULT 'verify_mobile',
            `code_hash` CHAR(64) NOT NULL,
            `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `verified_at` DATETIME NULL,
            `expires_at` DATETIME NOT NULL,
            `ip` VARCHAR(45) NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_otp_destination` (`destination`, `purpose`),
            KEY `idx_otp_expires` (`expires_at`),
            CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `password_resets` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `token_hash` CHAR(64) NOT NULL,
            `used_at` DATETIME NULL,
            `expires_at` DATETIME NOT NULL,
            `ip` VARCHAR(45) NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_reset_token` (`token_hash`),
            KEY `idx_reset_user` (`user_id`),
            CONSTRAINT `fk_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `login_history` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NULL,
            `identifier` VARCHAR(190) NOT NULL,
            `successful` TINYINT(1) NOT NULL DEFAULT 0,
            `ip` VARCHAR(45) NULL,
            `user_agent` VARCHAR(255) NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_login_user` (`user_id`),
            KEY `idx_login_ip` (`ip`),
            KEY `idx_login_created` (`created_at`),
            CONSTRAINT `fk_login_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `rate_limits` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `bucket_key` CHAR(64) NOT NULL,
            `window_start` BIGINT UNSIGNED NOT NULL,
            `hits` INT UNSIGNED NOT NULL DEFAULT 0,
            `expires_at` DATETIME NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_rate_bucket` (`bucket_key`, `window_start`),
            KEY `idx_rate_expires` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `audit_logs` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NULL,
            `action` VARCHAR(60) NOT NULL,
            `entity_type` VARCHAR(60) NOT NULL DEFAULT '',
            `entity_id` BIGINT UNSIGNED NULL,
            `description` VARCHAR(255) NULL,
            `before_data` MEDIUMTEXT NULL,
            `after_data` MEDIUMTEXT NULL,
            `ip` VARCHAR(45) NULL,
            `user_agent` VARCHAR(255) NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_audit_user` (`user_id`),
            KEY `idx_audit_entity` (`entity_type`, `entity_id`),
            KEY `idx_audit_action` (`action`),
            KEY `idx_audit_created` (`created_at`),
            CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `settings` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `group_name` VARCHAR(40) NOT NULL DEFAULT 'general',
            `key_name` VARCHAR(80) NOT NULL,
            `value` TEXT NULL,
            `type` ENUM('string','integer','decimal','boolean','json','secret','text') NOT NULL DEFAULT 'string',
            `label` VARCHAR(150) NULL,
            `description` VARCHAR(255) NULL,
            `is_public` TINYINT(1) NOT NULL DEFAULT 0,
            `updated_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_settings_key` (`key_name`),
            KEY `idx_settings_group` (`group_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(Database $db): void
    {
        $this->dropIfExists(
            $db,
            'settings',
            'audit_logs',
            'rate_limits',
            'login_history',
            'password_resets',
            'otp_codes',
            'api_tokens',
            'user_roles',
            'users',
            'role_permissions',
            'permissions',
            'roles'
        );
    }
};
