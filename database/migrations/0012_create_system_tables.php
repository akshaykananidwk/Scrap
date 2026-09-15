<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Migration;

return new class extends Migration {
    public function version(): string
    {
        return '0012';
    }

    public function description(): string
    {
        return 'System: updates, update files, backups, cron jobs and runs, saved searches';
    }

    public function up(Database $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS `system_updates` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `update_uid` CHAR(32) NOT NULL,
            `from_version` VARCHAR(20) NOT NULL,
            `to_version` VARCHAR(20) NOT NULL,
            `commit_sha` VARCHAR(40) NULL,
            `commit_message` VARCHAR(500) NULL,
            `commit_author` VARCHAR(120) NULL,
            `commit_date` DATETIME NULL,
            `channel` ENUM('branch','release') NOT NULL DEFAULT 'branch',
            `started_at` DATETIME NOT NULL,
            `finished_at` DATETIME NULL,
            `duration_ms` INT UNSIGNED NOT NULL DEFAULT 0,
            `triggered_by` INT UNSIGNED NULL,
            `files_added` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `files_updated` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `files_skipped` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `download_bytes` INT UNSIGNED NOT NULL DEFAULT 0,
            `backup_id` INT UNSIGNED NULL,
            `migration_status` ENUM('not_run','success','failed','rolled_back') NOT NULL DEFAULT 'not_run',
            `migrations_applied` VARCHAR(500) NULL,
            `cache_status` ENUM('not_run','cleared','failed') NOT NULL DEFAULT 'not_run',
            `health_status` ENUM('not_run','passed','failed') NOT NULL DEFAULT 'not_run',
            `health_report` TEXT NULL,
            `status` ENUM('running','success','failed','rolled_back') NOT NULL DEFAULT 'running',
            `current_step` VARCHAR(60) NULL,
            `error_message` TEXT NULL,
            `log` MEDIUMTEXT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_update_uid` (`update_uid`),
            KEY `idx_update_status` (`status`),
            KEY `idx_update_started` (`started_at`),
            CONSTRAINT `fk_update_user` FOREIGN KEY (`triggered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `system_update_files` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `update_id` INT UNSIGNED NOT NULL,
            `path` VARCHAR(400) NOT NULL,
            `action` ENUM('added','updated','skipped_protected','skipped_identical','failed','deleted') NOT NULL,
            `size_bytes` INT UNSIGNED NOT NULL DEFAULT 0,
            `checksum` CHAR(40) NULL,
            `note` VARCHAR(255) NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_updatefile_update` (`update_id`),
            KEY `idx_updatefile_action` (`action`),
            CONSTRAINT `fk_updatefile_update` FOREIGN KEY (`update_id`) REFERENCES `system_updates` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `system_backups` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `backup_uid` CHAR(32) NOT NULL,
            `filename` VARCHAR(190) NOT NULL,
            `path` VARCHAR(400) NOT NULL,
            `backup_type` ENUM('full','database','files') NOT NULL DEFAULT 'full',
            `trigger_type` ENUM('manual','pre_update','scheduled') NOT NULL DEFAULT 'manual',
            `app_version` VARCHAR(20) NULL,
            `schema_version` VARCHAR(20) NULL,
            `size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `table_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `file_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `status` ENUM('running','completed','failed','restored','deleted') NOT NULL DEFAULT 'running',
            `error_message` VARCHAR(500) NULL,
            `created_by` INT UNSIGNED NULL,
            `restored_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            `completed_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_backup_uid` (`backup_uid`),
            KEY `idx_backup_status` (`status`),
            KEY `idx_backup_created` (`created_at`),
            CONSTRAINT `fk_backup_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `cron_jobs` (
            `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `job_key` VARCHAR(60) NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `description` VARCHAR(255) NULL,
            `interval_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 5,
            `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
            `last_run_at` DATETIME NULL,
            `last_status` ENUM('never','success','failed','running') NOT NULL DEFAULT 'never',
            `last_duration_ms` INT UNSIGNED NOT NULL DEFAULT 0,
            `last_message` VARCHAR(500) NULL,
            `run_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `fail_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_cron_key` (`job_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `cron_runs` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `job_key` VARCHAR(60) NOT NULL,
            `status` ENUM('success','failed') NOT NULL,
            `duration_ms` INT UNSIGNED NOT NULL DEFAULT 0,
            `affected` INT UNSIGNED NOT NULL DEFAULT 0,
            `message` VARCHAR(500) NULL,
            `trigger_source` ENUM('cron','web','manual') NOT NULL DEFAULT 'cron',
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_cronrun_job` (`job_key`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `saved_searches` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(120) NOT NULL,
            `query_string` VARCHAR(500) NOT NULL,
            `alert_enabled` TINYINT(1) NOT NULL DEFAULT 0,
            `last_alert_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_saved_user` (`user_id`),
            CONSTRAINT `fk_saved_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->addForeignKeyIfMissing(
            $db,
            'system_updates',
            'fk_update_backup',
            'FOREIGN KEY (`backup_id`) REFERENCES `system_backups` (`id`) ON DELETE SET NULL'
        );
    }

    public function down(Database $db): void
    {
        $this->dropIfExists($db, 'saved_searches', 'cron_runs', 'cron_jobs', 'system_update_files', 'system_updates', 'system_backups');
    }
};
