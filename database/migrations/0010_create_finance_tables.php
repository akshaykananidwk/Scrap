<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Migration;

return new class extends Migration {
    public function version(): string
    {
        return '0010';
    }

    public function description(): string
    {
        return 'Payments, commissions, wallets with immutable ledger, invoices, plans and subscriptions';
    }

    public function up(Database $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS `payments` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `reference` VARCHAR(24) NOT NULL,
            `order_id` INT UNSIGNED NULL,
            `payer_id` INT UNSIGNED NOT NULL,
            `payee_id` INT UNSIGNED NULL,
            `purpose` ENUM('order','commission','deposit','subscription','listing_fee','featured_fee','refund','other') NOT NULL DEFAULT 'order',
            `amount` DECIMAL(15,2) NOT NULL,
            `currency` CHAR(3) NOT NULL DEFAULT 'INR',
            `method` ENUM('upi','razorpay','bank_transfer','neft','rtgs','imps','cash','cheque','credit','wallet','other') NOT NULL DEFAULT 'bank_transfer',
            `gateway` VARCHAR(40) NOT NULL DEFAULT 'manual',
            `gateway_order_id` VARCHAR(120) NULL,
            `gateway_payment_id` VARCHAR(120) NULL,
            `gateway_signature` VARCHAR(255) NULL,
            `utr_number` VARCHAR(60) NULL,
            `proof_path` VARCHAR(255) NULL,
            `status` ENUM('pending','processing','paid','partial','failed','refunded','disputed','cancelled') NOT NULL DEFAULT 'pending',
            `failure_reason` VARCHAR(255) NULL,
            `paid_at` DATETIME NULL,
            `verified_by` INT UNSIGNED NULL,
            `verified_at` DATETIME NULL,
            `notes` VARCHAR(255) NULL,
            `meta` TEXT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_payment_reference` (`reference`),
            KEY `idx_payment_order` (`order_id`),
            KEY `idx_payment_payer` (`payer_id`),
            KEY `idx_payment_status` (`status`),
            KEY `idx_payment_gateway_payment` (`gateway_payment_id`),
            CONSTRAINT `fk_payment_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_payment_payer` FOREIGN KEY (`payer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `fk_payment_payee` FOREIGN KEY (`payee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `commissions` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `order_id` INT UNSIGNED NULL,
            `auction_id` INT UNSIGNED NULL,
            `listing_id` INT UNSIGNED NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `party` ENUM('buyer','seller','platform') NOT NULL DEFAULT 'seller',
            `fee_type` ENUM('sale_commission','buyer_fee','seller_fee','auction_fee','listing_fee','featured_fee','subscription','lead_fee','verification_fee','logistics_fee') NOT NULL DEFAULT 'sale_commission',
            `base_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `percentage` DECIMAL(6,3) NOT NULL DEFAULT 0.000,
            `fixed_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `gst_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `status` ENUM('pending','invoiced','paid','waived','cancelled') NOT NULL DEFAULT 'pending',
            `payment_id` INT UNSIGNED NULL,
            `notes` VARCHAR(255) NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_commission_order` (`order_id`),
            KEY `idx_commission_user` (`user_id`),
            KEY `idx_commission_status` (`status`),
            KEY `idx_commission_created` (`created_at`),
            CONSTRAINT `fk_commission_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_commission_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_commission_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `wallets` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `locked_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `total_credited` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `total_debited` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `currency` CHAR(3) NOT NULL DEFAULT 'INR',
            `status` ENUM('active','frozen','closed') NOT NULL DEFAULT 'active',
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_wallet_user` (`user_id`),
            CONSTRAINT `fk_wallet_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Append-only ledger: balances are derived from these rows, never edited in place.
        $db->exec("CREATE TABLE IF NOT EXISTS `wallet_transactions` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `wallet_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `reference` VARCHAR(24) NOT NULL,
            `direction` ENUM('credit','debit') NOT NULL,
            `type` ENUM('credit','debit','refund','commission','deposit','withdrawal','adjustment','payout','hold','release') NOT NULL,
            `amount` DECIMAL(15,2) NOT NULL,
            `balance_before` DECIMAL(15,2) NOT NULL,
            `balance_after` DECIMAL(15,2) NOT NULL,
            `order_id` INT UNSIGNED NULL,
            `payment_id` INT UNSIGNED NULL,
            `commission_id` INT UNSIGNED NULL,
            `description` VARCHAR(255) NULL,
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_wallet_txn_reference` (`reference`),
            KEY `idx_wtxn_wallet` (`wallet_id`, `id`),
            KEY `idx_wtxn_user` (`user_id`),
            KEY `idx_wtxn_type` (`type`),
            CONSTRAINT `fk_wtxn_wallet` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_wtxn_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_wtxn_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_wtxn_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `invoices` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `invoice_number` VARCHAR(30) NOT NULL,
            `order_id` INT UNSIGNED NULL,
            `invoice_type` ENUM('sale','commission','subscription','proforma') NOT NULL DEFAULT 'sale',
            `seller_id` INT UNSIGNED NOT NULL,
            `buyer_id` INT UNSIGNED NOT NULL,
            `seller_name` VARCHAR(190) NOT NULL,
            `seller_gstin` VARCHAR(15) NULL,
            `seller_address` VARCHAR(400) NULL,
            `seller_state_code` CHAR(2) NULL,
            `buyer_name` VARCHAR(190) NOT NULL,
            `buyer_gstin` VARCHAR(15) NULL,
            `buyer_address` VARCHAR(400) NULL,
            `buyer_state_code` CHAR(2) NULL,
            `invoice_date` DATE NOT NULL,
            `due_date` DATE NULL,
            `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `cgst` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `sgst` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `igst` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `other_charges` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `round_off` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
            `total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `amount_in_words` VARCHAR(255) NULL,
            `payment_status` ENUM('pending','partial','paid','cancelled') NOT NULL DEFAULT 'pending',
            `notes` TEXT NULL,
            `created_by` INT UNSIGNED NULL,
            `cancelled_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_invoice_number` (`invoice_number`),
            KEY `idx_invoice_order` (`order_id`),
            KEY `idx_invoice_seller` (`seller_id`),
            KEY `idx_invoice_buyer` (`buyer_id`),
            CONSTRAINT `fk_invoice_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_invoice_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `fk_invoice_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `invoice_items` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `invoice_id` INT UNSIGNED NOT NULL,
            `description` VARCHAR(255) NOT NULL,
            `hsn_code` VARCHAR(12) NULL,
            `quantity` DECIMAL(15,3) NOT NULL DEFAULT 1.000,
            `unit_code` VARCHAR(12) NULL,
            `rate` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
            `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `gst_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            `cgst` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `sgst` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `igst` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_iitem_invoice` (`invoice_id`),
            CONSTRAINT `fk_iitem_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `plans` (
            `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(90) NOT NULL,
            `slug` VARCHAR(100) NOT NULL,
            `audience` ENUM('seller','buyer','both') NOT NULL DEFAULT 'both',
            `description` VARCHAR(255) NULL,
            `price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `gst_rate` DECIMAL(5,2) NOT NULL DEFAULT 18.00,
            `billing_period` ENUM('monthly','quarterly','half_yearly','yearly','lifetime') NOT NULL DEFAULT 'monthly',
            `listing_limit` INT NOT NULL DEFAULT 0,
            `auction_limit` INT NOT NULL DEFAULT 0,
            `rfq_limit` INT NOT NULL DEFAULT 0,
            `featured_credits` INT NOT NULL DEFAULT 0,
            `commission_discount` DECIMAL(6,3) NOT NULL DEFAULT 0.000,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_plan_slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `subscription_features` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `plan_id` SMALLINT UNSIGNED NOT NULL,
            `feature_key` VARCHAR(60) NOT NULL,
            `feature_label` VARCHAR(150) NOT NULL,
            `feature_value` VARCHAR(120) NULL,
            `is_included` TINYINT(1) NOT NULL DEFAULT 1,
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_plan_feature` (`plan_id`, `feature_key`),
            CONSTRAINT `fk_planfeature_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `subscriptions` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `plan_id` SMALLINT UNSIGNED NOT NULL,
            `status` ENUM('pending','active','expired','cancelled','suspended') NOT NULL DEFAULT 'pending',
            `starts_at` DATETIME NOT NULL,
            `ends_at` DATETIME NULL,
            `amount_paid` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `payment_id` INT UNSIGNED NULL,
            `auto_renew` TINYINT(1) NOT NULL DEFAULT 0,
            `listings_used` INT UNSIGNED NOT NULL DEFAULT 0,
            `auctions_used` INT UNSIGNED NOT NULL DEFAULT 0,
            `featured_used` INT UNSIGNED NOT NULL DEFAULT 0,
            `cancelled_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_subscription_user` (`user_id`, `status`),
            KEY `idx_subscription_ends` (`ends_at`),
            CONSTRAINT `fk_subscription_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_subscription_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `fk_subscription_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(Database $db): void
    {
        $this->dropIfExists(
            $db,
            'subscriptions',
            'subscription_features',
            'plans',
            'invoice_items',
            'invoices',
            'wallet_transactions',
            'wallets',
            'commissions',
            'payments'
        );
    }
};
