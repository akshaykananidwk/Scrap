<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Migration;

return new class extends Migration {
    public function version(): string
    {
        return '0004';
    }

    public function description(): string
    {
        return 'Catalog: categories, materials, grades, units, HSN codes';
    }

    public function up(Database $db): void
    {
        // Self-referencing tree: a subcategory is a category with a parent_id.
        $db->exec("CREATE TABLE IF NOT EXISTS `categories` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `parent_id` INT UNSIGNED NULL,
            `name` VARCHAR(120) NOT NULL,
            `slug` VARCHAR(140) NOT NULL,
            `icon` VARCHAR(60) NULL,
            `image` VARCHAR(255) NULL,
            `description` TEXT NULL,
            `meta_title` VARCHAR(190) NULL,
            `meta_description` VARCHAR(300) NULL,
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
            `listing_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_category_slug` (`slug`),
            KEY `idx_category_parent` (`parent_id`),
            KEY `idx_category_active` (`is_active`),
            CONSTRAINT `fk_category_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `units` (
            `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `code` VARCHAR(12) NOT NULL,
            `name` VARCHAR(60) NOT NULL,
            `kg_factor` DECIMAL(16,6) NULL,
            `is_weight` TINYINT(1) NOT NULL DEFAULT 1,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_unit_code` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `hsn_codes` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `code` VARCHAR(12) NOT NULL,
            `description` VARCHAR(255) NOT NULL,
            `gst_rate` DECIMAL(5,2) NOT NULL DEFAULT 18.00,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_hsn_code` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `materials` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `category_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(140) NOT NULL,
            `slug` VARCHAR(160) NOT NULL,
            `description` TEXT NULL,
            `image` VARCHAR(255) NULL,
            `default_unit_id` SMALLINT UNSIGNED NULL,
            `hsn_id` INT UNSIGNED NULL,
            `default_gst_rate` DECIMAL(5,2) NOT NULL DEFAULT 18.00,
            `meta_title` VARCHAR(190) NULL,
            `meta_description` VARCHAR(300) NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `listing_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_material_slug` (`slug`),
            KEY `idx_material_category` (`category_id`),
            KEY `idx_material_active` (`is_active`),
            CONSTRAINT `fk_material_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_material_unit` FOREIGN KEY (`default_unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_material_hsn` FOREIGN KEY (`hsn_id`) REFERENCES `hsn_codes` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `material_grades` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `material_id` INT UNSIGNED NULL,
            `name` VARCHAR(120) NOT NULL,
            `slug` VARCHAR(140) NOT NULL,
            `description` VARCHAR(255) NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_grade_material` (`material_id`),
            KEY `idx_grade_slug` (`slug`),
            CONSTRAINT `fk_grade_material` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(Database $db): void
    {
        $this->dropIfExists($db, 'material_grades', 'materials', 'hsn_codes', 'units', 'categories');
    }
};
