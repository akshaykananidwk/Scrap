<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Migration;

return new class extends Migration {
    public function version(): string
    {
        return '0009';
    }

    public function description(): string
    {
        return 'Orders, items, status history, weighment, transporters, vehicles, deliveries';
    }

    public function up(Database $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS `orders` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `reference` VARCHAR(20) NOT NULL,
            `buyer_id` INT UNSIGNED NOT NULL,
            `seller_id` INT UNSIGNED NOT NULL,
            `buyer_business_id` INT UNSIGNED NULL,
            `seller_business_id` INT UNSIGNED NULL,
            `source_type` ENUM('fixed','offer','auction','rfq','requirement','manual') NOT NULL DEFAULT 'fixed',
            `listing_id` INT UNSIGNED NULL,
            `auction_id` INT UNSIGNED NULL,
            `offer_id` INT UNSIGNED NULL,
            `rfq_id` INT UNSIGNED NULL,
            `requirement_id` INT UNSIGNED NULL,
            `quantity` DECIMAL(15,3) NOT NULL,
            `unit_id` SMALLINT UNSIGNED NOT NULL,
            `rate` DECIMAL(15,4) NOT NULL,
            `price_basis` ENUM('per_kg','per_mt','per_unit','lot') NOT NULL DEFAULT 'per_mt',
            `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `gst_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            `gst_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `transport_charges` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `loading_charges` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `other_charges` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `discount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `final_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `amount_paid` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `loading_by` ENUM('seller','buyer','shared','not_applicable') NOT NULL DEFAULT 'buyer',
            `transport_by` ENUM('seller','buyer','shared','not_applicable') NOT NULL DEFAULT 'buyer',
            `payment_terms` ENUM('advance','partial_advance','on_delivery','after_weighment','credit_7','credit_15','credit_30','negotiable') NOT NULL DEFAULT 'advance',
            `payment_status` ENUM('pending','processing','partial','paid','failed','refunded','disputed') NOT NULL DEFAULT 'pending',
            `pickup_address` VARCHAR(255) NULL,
            `delivery_address` VARCHAR(255) NULL,
            `delivery_city` VARCHAR(100) NULL,
            `delivery_state` VARCHAR(100) NULL,
            `delivery_pincode` CHAR(6) NULL,
            `expected_pickup_date` DATE NULL,
            `expected_delivery_date` DATE NULL,
            `status` ENUM('pending','confirmed','processing','ready_for_pickup','picked_up','in_transit','delivered','weighment','payment_pending','paid','completed','cancelled','disputed') NOT NULL DEFAULT 'pending',
            `cancelled_by` INT UNSIGNED NULL,
            `cancel_reason` VARCHAR(255) NULL,
            `notes` TEXT NULL,
            `buyer_reviewed` TINYINT(1) NOT NULL DEFAULT 0,
            `seller_reviewed` TINYINT(1) NOT NULL DEFAULT 0,
            `completed_at` DATETIME NULL,
            `is_demo` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_order_reference` (`reference`),
            KEY `idx_order_buyer` (`buyer_id`),
            KEY `idx_order_seller` (`seller_id`),
            KEY `idx_order_status` (`status`),
            KEY `idx_order_payment_status` (`payment_status`),
            KEY `idx_order_created` (`created_at`),
            KEY `idx_order_listing` (`listing_id`),
            CONSTRAINT `fk_order_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `fk_order_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `fk_order_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_order_auction` FOREIGN KEY (`auction_id`) REFERENCES `auctions` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_order_offer` FOREIGN KEY (`offer_id`) REFERENCES `offers` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_order_rfq` FOREIGN KEY (`rfq_id`) REFERENCES `rfqs` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_order_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `order_items` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `order_id` INT UNSIGNED NOT NULL,
            `material_id` INT UNSIGNED NULL,
            `grade_id` INT UNSIGNED NULL,
            `description` VARCHAR(255) NOT NULL,
            `hsn_code` VARCHAR(12) NULL,
            `quantity` DECIMAL(15,3) NOT NULL,
            `unit_id` SMALLINT UNSIGNED NOT NULL,
            `rate` DECIMAL(15,4) NOT NULL,
            `amount` DECIMAL(15,2) NOT NULL,
            `gst_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            `gst_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_oitem_order` (`order_id`),
            CONSTRAINT `fk_oitem_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_oitem_material` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_oitem_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `order_status_history` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `order_id` INT UNSIGNED NOT NULL,
            `from_status` VARCHAR(30) NULL,
            `to_status` VARCHAR(30) NOT NULL,
            `changed_by` INT UNSIGNED NULL,
            `notes` VARCHAR(255) NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_osh_order` (`order_id`, `created_at`),
            CONSTRAINT `fk_osh_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_osh_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `weighments` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `order_id` INT UNSIGNED NOT NULL,
            `delivery_id` INT UNSIGNED NULL,
            `expected_weight_kg` DECIMAL(15,3) NOT NULL DEFAULT 0.000,
            `gross_weight_kg` DECIMAL(15,3) NULL,
            `tare_weight_kg` DECIMAL(15,3) NULL,
            `actual_weight_kg` DECIMAL(15,3) NOT NULL DEFAULT 0.000,
            `difference_kg` DECIMAL(15,3) NOT NULL DEFAULT 0.000,
            `difference_percent` DECIMAL(8,3) NOT NULL DEFAULT 0.000,
            `rate_per_kg` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
            `settled_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `deduction_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `deduction_reason` VARCHAR(190) NULL,
            `weighbridge_name` VARCHAR(150) NULL,
            `slip_number` VARCHAR(60) NULL,
            `slip_path` VARCHAR(255) NULL,
            `photo_path` VARCHAR(255) NULL,
            `operator_name` VARCHAR(120) NULL,
            `vehicle_number` VARCHAR(20) NULL,
            `weighed_at` DATETIME NULL,
            `recorded_by` INT UNSIGNED NOT NULL,
            `status` ENUM('recorded','accepted','disputed','revised') NOT NULL DEFAULT 'recorded',
            `accepted_by` INT UNSIGNED NULL,
            `accepted_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_weigh_order` (`order_id`),
            CONSTRAINT `fk_weigh_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_weigh_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `transporters` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NULL,
            `name` VARCHAR(150) NOT NULL,
            `contact_person` VARCHAR(120) NULL,
            `mobile` VARCHAR(15) NOT NULL,
            `alt_mobile` VARCHAR(15) NULL,
            `email` VARCHAR(190) NULL,
            `gstin` VARCHAR(15) NULL,
            `address` VARCHAR(255) NULL,
            `city_name` VARCHAR(100) NULL,
            `state_name` VARCHAR(100) NULL,
            `service_areas` VARCHAR(255) NULL,
            `rating_avg` DECIMAL(3,2) NOT NULL DEFAULT 0.00,
            `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_transporter_user` (`user_id`),
            KEY `idx_transporter_active` (`is_active`),
            CONSTRAINT `fk_transporter_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `vehicles` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `transporter_id` INT UNSIGNED NULL,
            `owner_user_id` INT UNSIGNED NULL,
            `vehicle_number` VARCHAR(20) NOT NULL,
            `vehicle_type` ENUM('pickup','tata_ace','truck','trailer','container','tanker','tipper','other') NOT NULL DEFAULT 'truck',
            `capacity_kg` DECIMAL(12,2) NULL,
            `driver_name` VARCHAR(120) NULL,
            `driver_mobile` VARCHAR(15) NULL,
            `driver_licence` VARCHAR(40) NULL,
            `rc_number` VARCHAR(40) NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_vehicle_number` (`vehicle_number`),
            KEY `idx_vehicle_transporter` (`transporter_id`),
            CONSTRAINT `fk_vehicle_transporter` FOREIGN KEY (`transporter_id`) REFERENCES `transporters` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_vehicle_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `deliveries` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `order_id` INT UNSIGNED NOT NULL,
            `transporter_id` INT UNSIGNED NULL,
            `vehicle_id` INT UNSIGNED NULL,
            `vehicle_number` VARCHAR(20) NULL,
            `driver_name` VARCHAR(120) NULL,
            `driver_mobile` VARCHAR(15) NULL,
            `pickup_location` VARCHAR(255) NULL,
            `delivery_location` VARCHAR(255) NULL,
            `pickup_date` DATE NULL,
            `delivery_date` DATE NULL,
            `weight_kg` DECIMAL(15,3) NULL,
            `lr_number` VARCHAR(60) NULL,
            `eway_bill_number` VARCHAR(20) NULL,
            `eway_bill_date` DATE NULL,
            `freight_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `freight_paid_by` ENUM('seller','buyer','shared') NOT NULL DEFAULT 'buyer',
            `pod_path` VARCHAR(255) NULL,
            `pod_received_at` DATETIME NULL,
            `status` ENUM('assigned','scheduled','picked_up','in_transit','delivered','cancelled') NOT NULL DEFAULT 'assigned',
            `notes` VARCHAR(255) NULL,
            `created_by` INT UNSIGNED NOT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_delivery_order` (`order_id`),
            KEY `idx_delivery_status` (`status`),
            KEY `idx_delivery_transporter` (`transporter_id`),
            CONSTRAINT `fk_delivery_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_delivery_transporter` FOREIGN KEY (`transporter_id`) REFERENCES `transporters` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_delivery_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_delivery_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // deliveries is created after weighments, so this FK is added afterwards.
        $this->addForeignKeyIfMissing(
            $db,
            'weighments',
            'fk_weigh_delivery',
            'FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`id`) ON DELETE SET NULL'
        );
    }

    public function down(Database $db): void
    {
        $this->dropIfExists($db, 'deliveries', 'vehicles', 'transporters', 'weighments', 'order_status_history', 'order_items', 'orders');
    }
};
