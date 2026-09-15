<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Migration;

return new class extends Migration {
    public function version(): string
    {
        return '0007';
    }

    public function description(): string
    {
        return 'Auctions, eligible bidders, bids and auction event log';
    }

    public function up(Database $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS `auctions` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `reference` VARCHAR(20) NOT NULL,
            `listing_id` INT UNSIGNED NULL,
            `requirement_id` INT UNSIGNED NULL,
            `owner_id` INT UNSIGNED NOT NULL,
            `auction_type` ENUM('forward','reverse') NOT NULL DEFAULT 'forward',
            `title` VARCHAR(190) NOT NULL,
            `description` TEXT NULL,
            `quantity` DECIMAL(15,3) NOT NULL DEFAULT 0.000,
            `unit_id` SMALLINT UNSIGNED NOT NULL,
            `price_basis` ENUM('per_kg','per_mt','per_unit','lot') NOT NULL DEFAULT 'per_mt',
            `starting_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `reserve_price` DECIMAL(15,2) NULL,
            `bid_increment` DECIMAL(15,2) NOT NULL DEFAULT 100.00,
            `current_price` DECIMAL(15,2) NULL,
            `current_bid_id` INT UNSIGNED NULL,
            `winning_user_id` INT UNSIGNED NULL,
            `bid_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `bidder_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `view_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `starts_at` DATETIME NOT NULL,
            `ends_at` DATETIME NOT NULL,
            `original_ends_at` DATETIME NOT NULL,
            `extension_window_seconds` INT UNSIGNED NOT NULL DEFAULT 120,
            `extension_duration_seconds` INT UNSIGNED NOT NULL DEFAULT 120,
            `max_extensions` TINYINT UNSIGNED NOT NULL DEFAULT 5,
            `extension_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `max_bidders` SMALLINT UNSIGNED NULL,
            `requires_kyc` TINYINT(1) NOT NULL DEFAULT 1,
            `requires_approval` TINYINT(1) NOT NULL DEFAULT 0,
            `deposit_required` TINYINT(1) NOT NULL DEFAULT 0,
            `deposit_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `payment_terms` ENUM('advance','partial_advance','on_delivery','after_weighment','credit_7','credit_15','credit_30','negotiable') NOT NULL DEFAULT 'advance',
            `mask_bidders` TINYINT(1) NOT NULL DEFAULT 1,
            `status` ENUM('draft','scheduled','live','ended','awarded','cancelled','unsold') NOT NULL DEFAULT 'draft',
            `reserve_met` TINYINT(1) NOT NULL DEFAULT 0,
            `order_id` INT UNSIGNED NULL,
            `closed_at` DATETIME NULL,
            `awarded_at` DATETIME NULL,
            `cancel_reason` VARCHAR(255) NULL,
            `is_demo` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            `deleted_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_auction_reference` (`reference`),
            KEY `idx_auction_owner` (`owner_id`),
            KEY `idx_auction_listing` (`listing_id`),
            KEY `idx_auction_status` (`status`),
            KEY `idx_auction_ends` (`ends_at`),
            KEY `idx_auction_starts` (`starts_at`),
            KEY `idx_auction_type` (`auction_type`),
            CONSTRAINT `fk_auction_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_auction_requirement` FOREIGN KEY (`requirement_id`) REFERENCES `wanted_requirements` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_auction_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_auction_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `fk_auction_winner` FOREIGN KEY (`winning_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `auction_bidders` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `auction_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `bidder_number` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `status` ENUM('registered','approved','rejected','blocked') NOT NULL DEFAULT 'registered',
            `deposit_paid` TINYINT(1) NOT NULL DEFAULT 0,
            `deposit_reference` VARCHAR(80) NULL,
            `bid_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `last_bid_at` DATETIME NULL,
            `approved_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_auction_bidder` (`auction_id`, `user_id`),
            KEY `idx_abidder_user` (`user_id`),
            CONSTRAINT `fk_abidder_auction` FOREIGN KEY (`auction_id`) REFERENCES `auctions` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_abidder_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Bids are append-only. A losing bid is marked 'outbid', never deleted.
        $db->exec("CREATE TABLE IF NOT EXISTS `bids` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `bid_uid` CHAR(32) NOT NULL,
            `auction_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `amount` DECIMAL(15,2) NOT NULL,
            `quantity` DECIMAL(15,3) NULL,
            `is_auto` TINYINT(1) NOT NULL DEFAULT 0,
            `max_auto_amount` DECIMAL(15,2) NULL,
            `status` ENUM('active','outbid','winning','won','lost','retracted','invalid') NOT NULL DEFAULT 'active',
            `extended_auction` TINYINT(1) NOT NULL DEFAULT 0,
            `ip` VARCHAR(45) NULL,
            `user_agent` VARCHAR(255) NULL,
            `placed_at` DATETIME(3) NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_bid_uid` (`bid_uid`),
            KEY `idx_bid_auction_amount` (`auction_id`, `amount`),
            KEY `idx_bid_auction_time` (`auction_id`, `placed_at`),
            KEY `idx_bid_user` (`user_id`),
            KEY `idx_bid_status` (`status`),
            CONSTRAINT `fk_bid_auction` FOREIGN KEY (`auction_id`) REFERENCES `auctions` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_bid_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `auction_events` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `auction_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NULL,
            `event_type` ENUM('created','scheduled','started','bid_placed','bid_rejected','extended','ended','awarded','cancelled','reserve_met','unsold') NOT NULL,
            `details` VARCHAR(500) NULL,
            `ip` VARCHAR(45) NULL,
            `created_at` DATETIME(3) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_aevent_auction` (`auction_id`, `created_at`),
            KEY `idx_aevent_type` (`event_type`),
            CONSTRAINT `fk_aevent_auction` FOREIGN KEY (`auction_id`) REFERENCES `auctions` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_aevent_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(Database $db): void
    {
        $this->dropIfExists($db, 'auction_events', 'bids', 'auction_bidders', 'auctions');
    }
};
