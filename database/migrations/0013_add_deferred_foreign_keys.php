<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Migration;

/**
 * Cross-module references that could not be declared earlier because the target
 * table did not exist yet (auction → order, offer → order, RFQ → quote, …).
 */
return new class extends Migration {
    public function version(): string
    {
        return '0013';
    }

    public function description(): string
    {
        return 'Deferred cross-module foreign keys';
    }

    public function up(Database $db): void
    {
        $this->addForeignKeyIfMissing($db, 'auctions', 'fk_auction_current_bid', 'FOREIGN KEY (`current_bid_id`) REFERENCES `bids` (`id`) ON DELETE SET NULL');
        $this->addForeignKeyIfMissing($db, 'auctions', 'fk_auction_order', 'FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL');
        $this->addForeignKeyIfMissing($db, 'offers', 'fk_offer_order', 'FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL');
        $this->addForeignKeyIfMissing($db, 'offers', 'fk_offer_thread', 'FOREIGN KEY (`thread_id`) REFERENCES `conversations` (`id`) ON DELETE SET NULL');
        $this->addForeignKeyIfMissing($db, 'requirement_offers', 'fk_reqoffer_order', 'FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL');
        $this->addForeignKeyIfMissing($db, 'rfq_quotes', 'fk_rfqquote_order', 'FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL');
        $this->addForeignKeyIfMissing($db, 'rfqs', 'fk_rfq_awarded_quote', 'FOREIGN KEY (`awarded_quote_id`) REFERENCES `rfq_quotes` (`id`) ON DELETE SET NULL');
        $this->addForeignKeyIfMissing($db, 'conversations', 'fk_conv_order', 'FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL');
        $this->addForeignKeyIfMissing($db, 'messages', 'fk_msg_order', 'FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL');
        $this->addForeignKeyIfMissing($db, 'users', 'fk_user_approver', 'FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL');
    }

    public function down(Database $db): void
    {
        $constraints = [
            'auctions' => ['fk_auction_current_bid', 'fk_auction_order'],
            'offers' => ['fk_offer_order', 'fk_offer_thread'],
            'requirement_offers' => ['fk_reqoffer_order'],
            'rfq_quotes' => ['fk_rfqquote_order'],
            'rfqs' => ['fk_rfq_awarded_quote'],
            'conversations' => ['fk_conv_order'],
            'messages' => ['fk_msg_order'],
            'users' => ['fk_user_approver'],
        ];
        foreach ($constraints as $table => $names) {
            foreach ($names as $name) {
                try {
                    $db->exec(sprintf('ALTER TABLE `%s` DROP FOREIGN KEY `%s`', $table, $name));
                } catch (\Throwable) {
                    // Already absent — nothing to undo.
                }
            }
        }
    }
};
