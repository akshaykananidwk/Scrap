<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Migration;

return new class extends Migration {
    public function version(): string
    {
        return '0008';
    }

    public function description(): string
    {
        return 'Offers/counter-offers, conversations, messages and blocks';
    }

    public function up(Database $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS `offers` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `reference` VARCHAR(20) NOT NULL,
            `listing_id` INT UNSIGNED NULL,
            `requirement_id` INT UNSIGNED NULL,
            `thread_id` INT UNSIGNED NULL,
            `parent_offer_id` INT UNSIGNED NULL,
            `buyer_id` INT UNSIGNED NOT NULL,
            `seller_id` INT UNSIGNED NOT NULL,
            `created_by` INT UNSIGNED NOT NULL,
            `direction` ENUM('buyer_to_seller','seller_to_buyer') NOT NULL DEFAULT 'buyer_to_seller',
            `amount` DECIMAL(15,2) NOT NULL,
            `price_basis` ENUM('per_kg','per_mt','per_unit','lot') NOT NULL DEFAULT 'per_mt',
            `quantity` DECIMAL(15,3) NOT NULL,
            `unit_id` SMALLINT UNSIGNED NOT NULL,
            `gst_included` TINYINT(1) NOT NULL DEFAULT 0,
            `transport_included` TINYINT(1) NOT NULL DEFAULT 0,
            `payment_terms` ENUM('advance','partial_advance','on_delivery','after_weighment','credit_7','credit_15','credit_30','negotiable') NOT NULL DEFAULT 'advance',
            `message` TEXT NULL,
            `status` ENUM('pending','accepted','rejected','countered','expired','cancelled') NOT NULL DEFAULT 'pending',
            `order_id` INT UNSIGNED NULL,
            `responded_at` DATETIME NULL,
            `responded_by` INT UNSIGNED NULL,
            `expires_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_offer_reference` (`reference`),
            KEY `idx_offer_listing` (`listing_id`),
            KEY `idx_offer_buyer` (`buyer_id`),
            KEY `idx_offer_seller` (`seller_id`),
            KEY `idx_offer_status` (`status`),
            KEY `idx_offer_thread` (`thread_id`),
            KEY `idx_offer_expires` (`expires_at`),
            CONSTRAINT `fk_offer_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_offer_requirement` FOREIGN KEY (`requirement_id`) REFERENCES `wanted_requirements` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_offer_parent` FOREIGN KEY (`parent_offer_id`) REFERENCES `offers` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_offer_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_offer_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_offer_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `conversations` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `subject` VARCHAR(190) NULL,
            `listing_id` INT UNSIGNED NULL,
            `requirement_id` INT UNSIGNED NULL,
            `rfq_id` INT UNSIGNED NULL,
            `order_id` INT UNSIGNED NULL,
            `auction_id` INT UNSIGNED NULL,
            `buyer_id` INT UNSIGNED NOT NULL,
            `seller_id` INT UNSIGNED NOT NULL,
            `last_message_at` DATETIME NULL,
            `last_message_preview` VARCHAR(190) NULL,
            `buyer_unread` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `seller_unread` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `buyer_archived` TINYINT(1) NOT NULL DEFAULT 0,
            `seller_archived` TINYINT(1) NOT NULL DEFAULT 0,
            `status` ENUM('open','closed','blocked') NOT NULL DEFAULT 'open',
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_conv_buyer` (`buyer_id`),
            KEY `idx_conv_seller` (`seller_id`),
            KEY `idx_conv_listing` (`listing_id`),
            KEY `idx_conv_last_message` (`last_message_at`),
            CONSTRAINT `fk_conv_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_conv_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_conv_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_conv_requirement` FOREIGN KEY (`requirement_id`) REFERENCES `wanted_requirements` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_conv_rfq` FOREIGN KEY (`rfq_id`) REFERENCES `rfqs` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_conv_auction` FOREIGN KEY (`auction_id`) REFERENCES `auctions` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `messages` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `conversation_id` INT UNSIGNED NOT NULL,
            `sender_id` INT UNSIGNED NOT NULL,
            `body` TEXT NULL,
            `message_type` ENUM('text','offer','system','attachment') NOT NULL DEFAULT 'text',
            `offer_id` INT UNSIGNED NULL,
            `order_id` INT UNSIGNED NULL,
            `read_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            `deleted_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_msg_conversation` (`conversation_id`, `id`),
            KEY `idx_msg_sender` (`sender_id`),
            CONSTRAINT `fk_msg_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_msg_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_msg_offer` FOREIGN KEY (`offer_id`) REFERENCES `offers` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `message_attachments` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `message_id` BIGINT UNSIGNED NOT NULL,
            `file_path` VARCHAR(255) NOT NULL,
            `original_name` VARCHAR(190) NULL,
            `mime_type` VARCHAR(80) NULL,
            `file_size` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_matt_message` (`message_id`),
            CONSTRAINT `fk_matt_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `blocked_users` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `blocked_user_id` INT UNSIGNED NOT NULL,
            `reason` VARCHAR(190) NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_block` (`user_id`, `blocked_user_id`),
            KEY `idx_block_target` (`blocked_user_id`),
            CONSTRAINT `fk_block_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_block_target` FOREIGN KEY (`blocked_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(Database $db): void
    {
        $this->dropIfExists($db, 'blocked_users', 'message_attachments', 'messages', 'conversations', 'offers');
    }
};
