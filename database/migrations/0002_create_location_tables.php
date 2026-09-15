<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Migration;

return new class extends Migration {
    public function version(): string
    {
        return '0002';
    }

    public function description(): string
    {
        return 'Locations: countries, states, districts, cities, pincodes';
    }

    public function up(Database $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS `countries` (
            `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `iso2` CHAR(2) NOT NULL,
            `iso3` CHAR(3) NOT NULL,
            `phone_code` VARCHAR(8) NOT NULL DEFAULT '91',
            `currency` CHAR(3) NOT NULL DEFAULT 'INR',
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_country_iso2` (`iso2`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `states` (
            `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `country_id` SMALLINT UNSIGNED NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `slug` VARCHAR(120) NOT NULL,
            `code` VARCHAR(10) NULL,
            `gst_state_code` CHAR(2) NULL,
            `latitude` DECIMAL(10,7) NULL,
            `longitude` DECIMAL(10,7) NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_state_slug` (`slug`),
            KEY `idx_states_country` (`country_id`),
            CONSTRAINT `fk_states_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `districts` (
            `id` MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `state_id` SMALLINT UNSIGNED NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `slug` VARCHAR(120) NOT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_districts_state` (`state_id`),
            KEY `idx_districts_slug` (`slug`),
            CONSTRAINT `fk_districts_state` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `cities` (
            `id` MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `state_id` SMALLINT UNSIGNED NOT NULL,
            `district_id` MEDIUMINT UNSIGNED NULL,
            `name` VARCHAR(100) NOT NULL,
            `slug` VARCHAR(120) NOT NULL,
            `latitude` DECIMAL(10,7) NULL,
            `longitude` DECIMAL(10,7) NULL,
            `is_major` TINYINT(1) NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_cities_state` (`state_id`),
            KEY `idx_cities_district` (`district_id`),
            KEY `idx_cities_slug` (`slug`),
            KEY `idx_cities_name` (`name`),
            CONSTRAINT `fk_cities_state` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_cities_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `pincodes` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `pincode` CHAR(6) NOT NULL,
            `city_id` MEDIUMINT UNSIGNED NULL,
            `state_id` SMALLINT UNSIGNED NULL,
            `area` VARCHAR(120) NULL,
            `latitude` DECIMAL(10,7) NULL,
            `longitude` DECIMAL(10,7) NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_pincode` (`pincode`),
            KEY `idx_pincodes_city` (`city_id`),
            CONSTRAINT `fk_pincodes_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_pincodes_state` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(Database $db): void
    {
        $this->dropIfExists($db, 'pincodes', 'cities', 'districts', 'states', 'countries');
    }
};
