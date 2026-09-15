<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Migration;

return new class extends Migration {
    public function version(): string
    {
        return '0005';
    }

    public function description(): string
    {
        return 'Listings, media, views and favorites';
    }

    public function up(Database $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS `listings` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `reference` VARCHAR(20) NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `business_id` INT UNSIGNED NULL,
            `title` VARCHAR(190) NOT NULL,
            `slug` VARCHAR(220) NOT NULL,
            `category_id` INT UNSIGNED NOT NULL,
            `subcategory_id` INT UNSIGNED NULL,
            `material_id` INT UNSIGNED NULL,
            `grade_id` INT UNSIGNED NULL,
            `grade_text` VARCHAR(120) NULL,
            `description` TEXT NULL,
            `listing_type` ENUM('fixed','negotiable','auction','rfq','wanted','tender','make_offer') NOT NULL DEFAULT 'fixed',
            `quantity` DECIMAL(15,3) NOT NULL DEFAULT 0.000,
            `unit_id` SMALLINT UNSIGNED NOT NULL,
            `min_order_quantity` DECIMAL(15,3) NULL,
            `estimated_weight_kg` DECIMAL(15,3) NULL,
            `actual_weight_kg` DECIMAL(15,3) NULL,
            `price` DECIMAL(15,2) NULL,
            `price_per_kg` DECIMAL(15,4) NULL,
            `price_per_mt` DECIMAL(15,2) NULL,
            `total_price` DECIMAL(15,2) NULL,
            `is_negotiable` TINYINT(1) NOT NULL DEFAULT 0,
            `show_price` TINYINT(1) NOT NULL DEFAULT 1,
            `gst_applicable` TINYINT(1) NOT NULL DEFAULT 1,
            `gst_rate` DECIMAL(5,2) NOT NULL DEFAULT 18.00,
            `hsn_code` VARCHAR(12) NULL,
            `material_condition` ENUM('loose','baled','bundled','sorted','unsorted','shredded','processed','as_is') NOT NULL DEFAULT 'as_is',
            `material_source` ENUM('industrial','commercial','domestic','demolition','manufacturing','import','other') NOT NULL DEFAULT 'industrial',
            `pickup_address` VARCHAR(255) NULL,
            `city_id` MEDIUMINT UNSIGNED NULL,
            `state_id` SMALLINT UNSIGNED NULL,
            `city_name` VARCHAR(100) NULL,
            `state_name` VARCHAR(100) NULL,
            `pincode` CHAR(6) NULL,
            `latitude` DECIMAL(10,7) NULL,
            `longitude` DECIMAL(10,7) NULL,
            `delivery_available` TINYINT(1) NOT NULL DEFAULT 0,
            `pickup_available` TINYINT(1) NOT NULL DEFAULT 1,
            `loading_by` ENUM('seller','buyer','shared','not_applicable') NOT NULL DEFAULT 'buyer',
            `transport_by` ENUM('seller','buyer','shared','not_applicable') NOT NULL DEFAULT 'buyer',
            `payment_terms` ENUM('advance','partial_advance','on_delivery','after_weighment','credit_7','credit_15','credit_30','negotiable') NOT NULL DEFAULT 'advance',
            `inspection_available` TINYINT(1) NOT NULL DEFAULT 1,
            `inspection_notes` VARCHAR(255) NULL,
            `status` ENUM('draft','pending','active','paused','sold','expired','rejected','archived') NOT NULL DEFAULT 'pending',
            `rejection_reason` VARCHAR(255) NULL,
            `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
            `featured_until` DATETIME NULL,
            `promotion_tier` ENUM('none','featured','premium','sponsored') NOT NULL DEFAULT 'none',
            `view_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `enquiry_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `offer_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `favorite_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `meta_title` VARCHAR(190) NULL,
            `meta_description` VARCHAR(300) NULL,
            `published_at` DATETIME NULL,
            `expires_at` DATETIME NULL,
            `sold_at` DATETIME NULL,
            `is_demo` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            `deleted_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_listing_reference` (`reference`),
            UNIQUE KEY `uniq_listing_slug` (`slug`),
            KEY `idx_listing_user` (`user_id`),
            KEY `idx_listing_business` (`business_id`),
            KEY `idx_listing_category` (`category_id`),
            KEY `idx_listing_material` (`material_id`),
            KEY `idx_listing_status_type` (`status`, `listing_type`),
            KEY `idx_listing_city` (`city_id`),
            KEY `idx_listing_state` (`state_id`),
            KEY `idx_listing_published` (`published_at`),
            KEY `idx_listing_expires` (`expires_at`),
            KEY `idx_listing_featured` (`is_featured`, `promotion_tier`),
            KEY `idx_listing_price` (`price_per_kg`),
            FULLTEXT KEY `ft_listing_search` (`title`, `description`, `grade_text`),
            CONSTRAINT `fk_listing_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_listing_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_listing_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `fk_listing_subcategory` FOREIGN KEY (`subcategory_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_listing_material` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_listing_grade` FOREIGN KEY (`grade_id`) REFERENCES `material_grades` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_listing_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `fk_listing_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_listing_state` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `listing_images` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `listing_id` INT UNSIGNED NOT NULL,
            `file_path` VARCHAR(255) NOT NULL,
            `caption` VARCHAR(190) NULL,
            `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `file_size` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_limg_listing` (`listing_id`, `sort_order`),
            CONSTRAINT `fk_limg_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `listing_videos` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `listing_id` INT UNSIGNED NOT NULL,
            `file_path` VARCHAR(255) NULL,
            `external_url` VARCHAR(255) NULL,
            `caption` VARCHAR(190) NULL,
            `file_size` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_lvid_listing` (`listing_id`),
            CONSTRAINT `fk_lvid_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `listing_documents` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `listing_id` INT UNSIGNED NOT NULL,
            `file_path` VARCHAR(255) NOT NULL,
            `title` VARCHAR(190) NULL,
            `doc_type` VARCHAR(40) NOT NULL DEFAULT 'other',
            `file_size` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_ldoc_listing` (`listing_id`),
            CONSTRAINT `fk_ldoc_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `listing_views` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `listing_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NULL,
            `ip_hash` CHAR(64) NOT NULL,
            `viewed_on` DATE NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_view_per_day` (`listing_id`, `ip_hash`, `viewed_on`),
            KEY `idx_view_listing` (`listing_id`),
            KEY `idx_view_date` (`viewed_on`),
            CONSTRAINT `fk_view_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_view_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `favorites` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `favoritable_type` ENUM('listing','auction','requirement','rfq') NOT NULL DEFAULT 'listing',
            `favoritable_id` INT UNSIGNED NOT NULL,
            `notes` VARCHAR(255) NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_favorite` (`user_id`, `favoritable_type`, `favoritable_id`),
            KEY `idx_favorite_target` (`favoritable_type`, `favoritable_id`),
            CONSTRAINT `fk_favorite_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(Database $db): void
    {
        $this->dropIfExists($db, 'favorites', 'listing_views', 'listing_documents', 'listing_videos', 'listing_images', 'listings');
    }
};
