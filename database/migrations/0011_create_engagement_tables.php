<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Migration;

return new class extends Migration {
    public function version(): string
    {
        return '0011';
    }

    public function description(): string
    {
        return 'Reviews, disputes, notifications with queue, market rates, CMS pages, email templates';
    }

    public function up(Database $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS `reviews` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `order_id` INT UNSIGNED NOT NULL,
            `reviewer_id` INT UNSIGNED NOT NULL,
            `reviewee_id` INT UNSIGNED NOT NULL,
            `business_id` INT UNSIGNED NULL,
            `reviewer_role` ENUM('buyer','seller') NOT NULL,
            `overall_rating` TINYINT UNSIGNED NOT NULL,
            `communication_rating` TINYINT UNSIGNED NULL,
            `material_accuracy_rating` TINYINT UNSIGNED NULL,
            `payment_rating` TINYINT UNSIGNED NULL,
            `delivery_rating` TINYINT UNSIGNED NULL,
            `professionalism_rating` TINYINT UNSIGNED NULL,
            `title` VARCHAR(150) NULL,
            `comment` TEXT NULL,
            `seller_response` TEXT NULL,
            `responded_at` DATETIME NULL,
            `status` ENUM('published','pending','hidden','removed') NOT NULL DEFAULT 'published',
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_review_per_order_role` (`order_id`, `reviewer_id`),
            KEY `idx_review_reviewee` (`reviewee_id`, `status`),
            KEY `idx_review_business` (`business_id`),
            CONSTRAINT `fk_review_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_review_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_review_reviewee` FOREIGN KEY (`reviewee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_review_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `disputes` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `reference` VARCHAR(20) NOT NULL,
            `order_id` INT UNSIGNED NULL,
            `raised_by` INT UNSIGNED NOT NULL,
            `against_user_id` INT UNSIGNED NOT NULL,
            `category` ENUM('wrong_material','weight_mismatch','quality_issue','payment_issue','non_delivery','fraud','damage','other') NOT NULL DEFAULT 'other',
            `subject` VARCHAR(190) NOT NULL,
            `description` TEXT NOT NULL,
            `claimed_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `status` ENUM('open','under_review','evidence_requested','resolved','rejected','escalated','closed') NOT NULL DEFAULT 'open',
            `priority` ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
            `assigned_to` INT UNSIGNED NULL,
            `resolution` TEXT NULL,
            `resolution_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `internal_notes` TEXT NULL,
            `resolved_by` INT UNSIGNED NULL,
            `resolved_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_dispute_reference` (`reference`),
            KEY `idx_dispute_order` (`order_id`),
            KEY `idx_dispute_status` (`status`),
            KEY `idx_dispute_raised_by` (`raised_by`),
            CONSTRAINT `fk_dispute_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_dispute_raiser` FOREIGN KEY (`raised_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_dispute_against` FOREIGN KEY (`against_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_dispute_assignee` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `dispute_messages` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `dispute_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `body` TEXT NOT NULL,
            `attachment_path` VARCHAR(255) NULL,
            `is_internal` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_dmsg_dispute` (`dispute_id`, `id`),
            CONSTRAINT `fk_dmsg_dispute` FOREIGN KEY (`dispute_id`) REFERENCES `disputes` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_dmsg_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `notifications` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `event` VARCHAR(60) NOT NULL,
            `title` VARCHAR(190) NOT NULL,
            `body` VARCHAR(500) NULL,
            `link` VARCHAR(255) NULL,
            `icon` VARCHAR(40) NULL,
            `level` ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
            `entity_type` VARCHAR(40) NULL,
            `entity_id` INT UNSIGNED NULL,
            `read_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_notification_user` (`user_id`, `read_at`),
            KEY `idx_notification_event` (`event`),
            KEY `idx_notification_created` (`created_at`),
            CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Outbound channel queue processed by the scheduler.
        $db->exec("CREATE TABLE IF NOT EXISTS `notification_queue` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NULL,
            `channel` ENUM('email','sms','whatsapp','push') NOT NULL,
            `event` VARCHAR(60) NOT NULL,
            `recipient` VARCHAR(190) NOT NULL,
            `subject` VARCHAR(190) NULL,
            `body` TEXT NOT NULL,
            `payload` TEXT NULL,
            `status` ENUM('queued','processing','sent','failed','skipped','cancelled') NOT NULL DEFAULT 'queued',
            `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `last_error` VARCHAR(255) NULL,
            `provider` VARCHAR(40) NULL,
            `provider_message_id` VARCHAR(120) NULL,
            `scheduled_at` DATETIME NOT NULL,
            `sent_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_queue_status` (`status`, `scheduled_at`),
            KEY `idx_queue_user` (`user_id`),
            CONSTRAINT `fk_queue_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `email_templates` (
            `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `event` VARCHAR(60) NOT NULL,
            `channel` ENUM('email','sms','whatsapp','push') NOT NULL DEFAULT 'email',
            `name` VARCHAR(120) NOT NULL,
            `subject` VARCHAR(190) NULL,
            `body` TEXT NOT NULL,
            `variables` VARCHAR(500) NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_template_event_channel` (`event`, `channel`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `market_rates` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `material_id` INT UNSIGNED NOT NULL,
            `grade_id` INT UNSIGNED NULL,
            `city_id` MEDIUMINT UNSIGNED NULL,
            `city_name` VARCHAR(100) NULL,
            `state_id` SMALLINT UNSIGNED NULL,
            `rate` DECIMAL(15,2) NOT NULL,
            `unit_id` SMALLINT UNSIGNED NOT NULL,
            `rate_date` DATE NOT NULL,
            `previous_rate` DECIMAL(15,2) NULL,
            `change_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `change_percent` DECIMAL(8,3) NOT NULL DEFAULT 0.000,
            `source` VARCHAR(120) NULL,
            `notes` VARCHAR(255) NULL,
            `is_published` TINYINT(1) NOT NULL DEFAULT 1,
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_rate_per_day` (`material_id`, `grade_id`, `city_id`, `rate_date`),
            KEY `idx_rate_material_date` (`material_id`, `rate_date`),
            KEY `idx_rate_city` (`city_id`),
            CONSTRAINT `fk_rate_material` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_rate_grade` FOREIGN KEY (`grade_id`) REFERENCES `material_grades` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_rate_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_rate_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `cms_pages` (
            `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `slug` VARCHAR(120) NOT NULL,
            `title` VARCHAR(190) NOT NULL,
            `content` MEDIUMTEXT NULL,
            `meta_title` VARCHAR(190) NULL,
            `meta_description` VARCHAR(300) NULL,
            `show_in_footer` TINYINT(1) NOT NULL DEFAULT 1,
            `show_in_header` TINYINT(1) NOT NULL DEFAULT 0,
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `is_published` TINYINT(1) NOT NULL DEFAULT 1,
            `is_system` TINYINT(1) NOT NULL DEFAULT 0,
            `updated_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_cms_slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `faqs` (
            `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `question` VARCHAR(255) NOT NULL,
            `answer` TEXT NOT NULL,
            `category` VARCHAR(60) NOT NULL DEFAULT 'general',
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `is_published` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_faq_category` (`category`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `contact_messages` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(120) NOT NULL,
            `email` VARCHAR(190) NOT NULL,
            `mobile` VARCHAR(15) NULL,
            `subject` VARCHAR(190) NULL,
            `message` TEXT NOT NULL,
            `status` ENUM('new','read','replied','closed') NOT NULL DEFAULT 'new',
            `ip` VARCHAR(45) NULL,
            `handled_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_contact_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(Database $db): void
    {
        $this->dropIfExists(
            $db,
            'contact_messages',
            'faqs',
            'cms_pages',
            'market_rates',
            'email_templates',
            'notification_queue',
            'notifications',
            'dispute_messages',
            'disputes',
            'reviews'
        );
    }
};
