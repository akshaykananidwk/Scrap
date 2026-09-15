<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Migration;

return new class extends Migration {
    public function version(): string
    {
        return '0006';
    }

    public function description(): string
    {
        return 'Buyer requirements (wanted), seller responses and the RFQ workflow';
    }

    public function up(Database $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS `wanted_requirements` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `reference` VARCHAR(20) NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `business_id` INT UNSIGNED NULL,
            `title` VARCHAR(190) NOT NULL,
            `slug` VARCHAR(220) NOT NULL,
            `category_id` INT UNSIGNED NOT NULL,
            `material_id` INT UNSIGNED NULL,
            `grade_id` INT UNSIGNED NULL,
            `grade_text` VARCHAR(120) NULL,
            `description` TEXT NULL,
            `quantity` DECIMAL(15,3) NOT NULL DEFAULT 0.000,
            `unit_id` SMALLINT UNSIGNED NOT NULL,
            `min_quantity` DECIMAL(15,3) NULL,
            `max_quantity` DECIMAL(15,3) NULL,
            `target_price` DECIMAL(15,2) NULL,
            `price_basis` ENUM('per_kg','per_mt','per_unit','lot') NOT NULL DEFAULT 'per_mt',
            `frequency` ENUM('one_time','daily','weekly','fortnightly','monthly','quarterly') NOT NULL DEFAULT 'one_time',
            `delivery_required` TINYINT(1) NOT NULL DEFAULT 0,
            `delivery_address` VARCHAR(255) NULL,
            `city_id` MEDIUMINT UNSIGNED NULL,
            `state_id` SMALLINT UNSIGNED NULL,
            `city_name` VARCHAR(100) NULL,
            `state_name` VARCHAR(100) NULL,
            `pincode` CHAR(6) NULL,
            `required_by` DATE NULL,
            `payment_terms` ENUM('advance','partial_advance','on_delivery','after_weighment','credit_7','credit_15','credit_30','negotiable') NOT NULL DEFAULT 'on_delivery',
            `status` ENUM('draft','open','closed','fulfilled','expired','cancelled') NOT NULL DEFAULT 'open',
            `offer_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `view_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `expires_at` DATETIME NULL,
            `is_demo` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            `deleted_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_requirement_reference` (`reference`),
            UNIQUE KEY `uniq_requirement_slug` (`slug`),
            KEY `idx_req_user` (`user_id`),
            KEY `idx_req_category` (`category_id`),
            KEY `idx_req_material` (`material_id`),
            KEY `idx_req_status` (`status`),
            KEY `idx_req_city` (`city_id`),
            FULLTEXT KEY `ft_requirement_search` (`title`, `description`),
            CONSTRAINT `fk_req_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_req_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_req_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `fk_req_material` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_req_grade` FOREIGN KEY (`grade_id`) REFERENCES `material_grades` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_req_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `fk_req_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_req_state` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `requirement_documents` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `requirement_id` INT UNSIGNED NOT NULL,
            `file_path` VARCHAR(255) NOT NULL,
            `title` VARCHAR(190) NULL,
            `is_image` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_reqdoc_requirement` (`requirement_id`),
            CONSTRAINT `fk_reqdoc_requirement` FOREIGN KEY (`requirement_id`) REFERENCES `wanted_requirements` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `requirement_offers` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `requirement_id` INT UNSIGNED NOT NULL,
            `seller_id` INT UNSIGNED NOT NULL,
            `listing_id` INT UNSIGNED NULL,
            `available_quantity` DECIMAL(15,3) NOT NULL,
            `offered_price` DECIMAL(15,2) NOT NULL,
            `price_basis` ENUM('per_kg','per_mt','per_unit','lot') NOT NULL DEFAULT 'per_mt',
            `gst_included` TINYINT(1) NOT NULL DEFAULT 0,
            `delivery_offered` TINYINT(1) NOT NULL DEFAULT 0,
            `delivery_days` SMALLINT UNSIGNED NULL,
            `message` TEXT NULL,
            `status` ENUM('pending','shortlisted','accepted','rejected','withdrawn','expired') NOT NULL DEFAULT 'pending',
            `order_id` INT UNSIGNED NULL,
            `responded_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_requirement_seller` (`requirement_id`, `seller_id`),
            KEY `idx_reqoffer_seller` (`seller_id`),
            KEY `idx_reqoffer_status` (`status`),
            CONSTRAINT `fk_reqoffer_requirement` FOREIGN KEY (`requirement_id`) REFERENCES `wanted_requirements` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_reqoffer_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_reqoffer_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `rfqs` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `reference` VARCHAR(20) NOT NULL,
            `buyer_id` INT UNSIGNED NOT NULL,
            `business_id` INT UNSIGNED NULL,
            `title` VARCHAR(190) NOT NULL,
            `description` TEXT NULL,
            `category_id` INT UNSIGNED NULL,
            `delivery_address` VARCHAR(255) NULL,
            `city_id` MEDIUMINT UNSIGNED NULL,
            `state_id` SMALLINT UNSIGNED NULL,
            `city_name` VARCHAR(100) NULL,
            `payment_terms` ENUM('advance','partial_advance','on_delivery','after_weighment','credit_7','credit_15','credit_30','negotiable') NOT NULL DEFAULT 'on_delivery',
            `delivery_required_by` DATE NULL,
            `visibility` ENUM('public','invited') NOT NULL DEFAULT 'public',
            `status` ENUM('draft','open','closing_soon','closed','awarded','cancelled') NOT NULL DEFAULT 'draft',
            `closes_at` DATETIME NULL,
            `awarded_quote_id` INT UNSIGNED NULL,
            `awarded_at` DATETIME NULL,
            `quote_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `is_demo` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            `deleted_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_rfq_reference` (`reference`),
            KEY `idx_rfq_buyer` (`buyer_id`),
            KEY `idx_rfq_status` (`status`),
            KEY `idx_rfq_closes` (`closes_at`),
            CONSTRAINT `fk_rfq_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_rfq_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_rfq_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `rfq_items` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `rfq_id` INT UNSIGNED NOT NULL,
            `material_id` INT UNSIGNED NULL,
            `grade_id` INT UNSIGNED NULL,
            `item_name` VARCHAR(190) NOT NULL,
            `specification` TEXT NULL,
            `quantity` DECIMAL(15,3) NOT NULL,
            `unit_id` SMALLINT UNSIGNED NOT NULL,
            `target_price` DECIMAL(15,2) NULL,
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_rfqitem_rfq` (`rfq_id`),
            CONSTRAINT `fk_rfqitem_rfq` FOREIGN KEY (`rfq_id`) REFERENCES `rfqs` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_rfqitem_material` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_rfqitem_grade` FOREIGN KEY (`grade_id`) REFERENCES `material_grades` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_rfqitem_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `rfq_invites` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `rfq_id` INT UNSIGNED NOT NULL,
            `seller_id` INT UNSIGNED NOT NULL,
            `invited_by` INT UNSIGNED NULL,
            `status` ENUM('invited','viewed','quoted','declined') NOT NULL DEFAULT 'invited',
            `viewed_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_rfq_invite` (`rfq_id`, `seller_id`),
            KEY `idx_rfqinvite_seller` (`seller_id`),
            CONSTRAINT `fk_rfqinvite_rfq` FOREIGN KEY (`rfq_id`) REFERENCES `rfqs` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_rfqinvite_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_rfqinvite_inviter` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `rfq_quotes` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `rfq_id` INT UNSIGNED NOT NULL,
            `seller_id` INT UNSIGNED NOT NULL,
            `business_id` INT UNSIGNED NULL,
            `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `gst_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `grand_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `delivery_included` TINYINT(1) NOT NULL DEFAULT 0,
            `delivery_charges` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `delivery_days` SMALLINT UNSIGNED NULL,
            `payment_terms` VARCHAR(120) NULL,
            `validity_days` SMALLINT UNSIGNED NOT NULL DEFAULT 7,
            `notes` TEXT NULL,
            `attachment_path` VARCHAR(255) NULL,
            `status` ENUM('submitted','shortlisted','negotiating','awarded','rejected','withdrawn','expired') NOT NULL DEFAULT 'submitted',
            `order_id` INT UNSIGNED NULL,
            `expires_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_rfq_seller_quote` (`rfq_id`, `seller_id`),
            KEY `idx_rfqquote_seller` (`seller_id`),
            KEY `idx_rfqquote_status` (`status`),
            CONSTRAINT `fk_rfqquote_rfq` FOREIGN KEY (`rfq_id`) REFERENCES `rfqs` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_rfqquote_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_rfqquote_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `rfq_quote_items` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `quote_id` INT UNSIGNED NOT NULL,
            `rfq_item_id` INT UNSIGNED NOT NULL,
            `offered_quantity` DECIMAL(15,3) NOT NULL,
            `rate` DECIMAL(15,4) NOT NULL,
            `amount` DECIMAL(15,2) NOT NULL,
            `gst_rate` DECIMAL(5,2) NOT NULL DEFAULT 18.00,
            `remarks` VARCHAR(255) NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_rfqqitem_quote` (`quote_id`),
            KEY `idx_rfqqitem_item` (`rfq_item_id`),
            CONSTRAINT `fk_rfqqitem_quote` FOREIGN KEY (`quote_id`) REFERENCES `rfq_quotes` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_rfqqitem_item` FOREIGN KEY (`rfq_item_id`) REFERENCES `rfq_items` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(Database $db): void
    {
        $this->dropIfExists(
            $db,
            'rfq_quote_items',
            'rfq_quotes',
            'rfq_invites',
            'rfq_items',
            'rfqs',
            'requirement_offers',
            'requirement_documents',
            'wanted_requirements'
        );
    }
};
