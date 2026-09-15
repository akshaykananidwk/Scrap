<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Migration;

return new class extends Migration {
    public function version(): string
    {
        return '0003';
    }

    public function description(): string
    {
        return 'Businesses, KYC documents, verifications, follows and content reports';
    }

    public function up(Database $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS `businesses` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(180) NOT NULL,
            `slug` VARCHAR(200) NOT NULL,
            `business_type` ENUM('trader','dealer','aggregator','recycler','manufacturer','factory','contractor','industrial','ewaste_dealer','metal_trader','plastic_paper_trader','transporter','other') NOT NULL DEFAULT 'trader',
            `logo` VARCHAR(255) NULL,
            `cover_image` VARCHAR(255) NULL,
            `about` TEXT NULL,
            `established_year` SMALLINT UNSIGNED NULL,
            `employee_count` VARCHAR(30) NULL,
            `annual_turnover` VARCHAR(40) NULL,
            `website` VARCHAR(190) NULL,
            `contact_person` VARCHAR(120) NULL,
            `contact_mobile` VARCHAR(15) NULL,
            `contact_email` VARCHAR(190) NULL,
            `gstin` VARCHAR(15) NULL,
            `pan` VARCHAR(10) NULL,
            `registration_number` VARCHAR(60) NULL,
            `address_line1` VARCHAR(255) NULL,
            `address_line2` VARCHAR(255) NULL,
            `city_id` MEDIUMINT UNSIGNED NULL,
            `state_id` SMALLINT UNSIGNED NULL,
            `country_id` SMALLINT UNSIGNED NULL,
            `city_name` VARCHAR(100) NULL,
            `state_name` VARCHAR(100) NULL,
            `pincode` CHAR(6) NULL,
            `latitude` DECIMAL(10,7) NULL,
            `longitude` DECIMAL(10,7) NULL,
            `gst_verified` TINYINT(1) NOT NULL DEFAULT 0,
            `pan_verified` TINYINT(1) NOT NULL DEFAULT 0,
            `kyc_verified` TINYINT(1) NOT NULL DEFAULT 0,
            `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
            `rating_avg` DECIMAL(3,2) NOT NULL DEFAULT 0.00,
            `rating_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `total_listings` INT UNSIGNED NOT NULL DEFAULT 0,
            `completed_orders` INT UNSIGNED NOT NULL DEFAULT 0,
            `response_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            `avg_response_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
            `follower_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `profile_views` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            `deleted_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_business_slug` (`slug`),
            KEY `idx_business_user` (`user_id`),
            KEY `idx_business_city` (`city_id`),
            KEY `idx_business_state` (`state_id`),
            KEY `idx_business_type` (`business_type`),
            KEY `idx_business_gstin` (`gstin`),
            CONSTRAINT `fk_business_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_business_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_business_state` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `business_documents` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `business_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `doc_type` ENUM('gst_certificate','pan_card','aadhaar','business_registration','shop_license','address_proof','bank_proof','other') NOT NULL,
            `doc_number` VARCHAR(60) NULL,
            `file_path` VARCHAR(255) NOT NULL,
            `original_name` VARCHAR(190) NULL,
            `mime_type` VARCHAR(80) NULL,
            `file_size` INT UNSIGNED NOT NULL DEFAULT 0,
            `status` ENUM('pending','verified','rejected','expired') NOT NULL DEFAULT 'pending',
            `reviewed_by` INT UNSIGNED NULL,
            `reviewed_at` DATETIME NULL,
            `review_notes` VARCHAR(255) NULL,
            `expires_at` DATE NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_bizdoc_business` (`business_id`),
            KEY `idx_bizdoc_user` (`user_id`),
            KEY `idx_bizdoc_status` (`status`),
            CONSTRAINT `fk_bizdoc_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_bizdoc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_bizdoc_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `kyc_verifications` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `business_id` INT UNSIGNED NULL,
            `status` ENUM('pending','under_review','verified','rejected','expired') NOT NULL DEFAULT 'pending',
            `submitted_at` DATETIME NOT NULL,
            `reviewed_by` INT UNSIGNED NULL,
            `reviewed_at` DATETIME NULL,
            `rejection_reason` VARCHAR(255) NULL,
            `internal_notes` TEXT NULL,
            `verification_method` ENUM('manual','gst_api','pan_api','hybrid') NOT NULL DEFAULT 'manual',
            `expires_at` DATE NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_kyc_user` (`user_id`),
            KEY `idx_kyc_status` (`status`),
            CONSTRAINT `fk_kyc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_kyc_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_kyc_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `follows` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `followable_type` ENUM('business','category','material','seller') NOT NULL,
            `followable_id` INT UNSIGNED NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_follow` (`user_id`, `followable_type`, `followable_id`),
            KEY `idx_follow_target` (`followable_type`, `followable_id`),
            CONSTRAINT `fk_follow_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `content_reports` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `reporter_id` INT UNSIGNED NOT NULL,
            `reportable_type` ENUM('listing','user','business','auction','message','requirement','rfq') NOT NULL,
            `reportable_id` INT UNSIGNED NOT NULL,
            `reason` ENUM('fake_listing','fake_buyer','fake_seller','fraud','wrong_material','weight_mismatch','payment_issue','abuse','spam','other') NOT NULL,
            `details` TEXT NULL,
            `evidence_path` VARCHAR(255) NULL,
            `status` ENUM('open','under_review','action_taken','dismissed') NOT NULL DEFAULT 'open',
            `handled_by` INT UNSIGNED NULL,
            `handled_at` DATETIME NULL,
            `admin_notes` TEXT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_report_target` (`reportable_type`, `reportable_id`),
            KEY `idx_report_status` (`status`),
            KEY `idx_report_reporter` (`reporter_id`),
            CONSTRAINT `fk_report_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_report_handler` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `fraud_flags` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `rule` VARCHAR(60) NOT NULL,
            `severity` ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low',
            `score` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            `details` TEXT NULL,
            `status` ENUM('open','reviewed','cleared','confirmed') NOT NULL DEFAULT 'open',
            `reviewed_by` INT UNSIGNED NULL,
            `reviewed_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_fraud_user` (`user_id`),
            KEY `idx_fraud_status` (`status`),
            KEY `idx_fraud_rule` (`rule`),
            CONSTRAINT `fk_fraud_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(Database $db): void
    {
        $this->dropIfExists($db, 'fraud_flags', 'content_reports', 'follows', 'kyc_verifications', 'business_documents', 'businesses');
    }
};
