<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Buyer ↔ seller messaging.
 *
 * Delivery is AJAX polling (`/dashboard/messages/{id}/poll?after=<id>`) so it
 * works on shared hosting with no WebSocket server. The poll endpoint returns
 * only messages after a cursor, which is also exactly the shape a WebSocket
 * push would take — swapping transports later does not change the data model.
 */
final class ChatService
{
    /** Find or create the conversation between two users about a subject. */
    public static function ensureConversation(int $buyerId, int $sellerId, array $context = []): int
    {
        $db = Database::instance();

        $conditions = ['buyer_id' => $buyerId, 'seller_id' => $sellerId];
        $sql = 'SELECT id FROM conversations WHERE buyer_id = :b AND seller_id = :s';
        $params = ['b' => $buyerId, 's' => $sellerId];

        foreach (['listing_id', 'requirement_id', 'rfq_id', 'order_id', 'auction_id'] as $key) {
            if (!empty($context[$key])) {
                $sql .= " AND {$key} = :{$key}";
                $params[$key] = (int) $context[$key];
            }
        }
        $existing = $db->first($sql . ' LIMIT 1', $params);
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        return $db->insert('conversations', [
            'subject' => substr((string) ($context['subject'] ?? 'Enquiry'), 0, 190),
            'listing_id' => $context['listing_id'] ?? null,
            'requirement_id' => $context['requirement_id'] ?? null,
            'rfq_id' => $context['rfq_id'] ?? null,
            'order_id' => $context['order_id'] ?? null,
            'auction_id' => $context['auction_id'] ?? null,
            'buyer_id' => $buyerId,
            'seller_id' => $sellerId,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function isBlocked(int $userId, int $otherUserId): bool
    {
        return (int) Database::instance()->scalar(
            'SELECT COUNT(*) FROM blocked_users
             WHERE (user_id = :a AND blocked_user_id = :b) OR (user_id = :c AND blocked_user_id = :d)',
            ['a' => $userId, 'b' => $otherUserId, 'c' => $otherUserId, 'd' => $userId],
            0
        ) > 0;
    }

    /** @return array{ok: bool, error?: string, message_id?: int} */
    public static function send(int $conversationId, int $senderId, string $body, array $attachments = [], array $context = []): array
    {
        $db = Database::instance();
        $conversation = $db->first('SELECT * FROM conversations WHERE id = :id', ['id' => $conversationId]);
        if ($conversation === null) {
            return ['ok' => false, 'error' => 'Conversation not found.'];
        }
        if ((int) $conversation['buyer_id'] !== $senderId && (int) $conversation['seller_id'] !== $senderId) {
            return ['ok' => false, 'error' => 'You are not part of this conversation.'];
        }
        if ($conversation['status'] === 'blocked') {
            return ['ok' => false, 'error' => 'This conversation is blocked.'];
        }

        $otherId = (int) $conversation['buyer_id'] === $senderId
            ? (int) $conversation['seller_id']
            : (int) $conversation['buyer_id'];

        if (self::isBlocked($senderId, $otherId)) {
            return ['ok' => false, 'error' => 'You cannot message this user.'];
        }

        $body = trim($body);
        if ($body === '' && $attachments === []) {
            return ['ok' => false, 'error' => 'Write a message or attach a file.'];
        }
        if (mb_strlen($body) > 5000) {
            return ['ok' => false, 'error' => 'Message is too long (5000 characters maximum).'];
        }

        $messageId = $db->transaction(static function (Database $db) use ($conversationId, $senderId, $body, $attachments, $context, $conversation): int {
            $id = $db->insert('messages', [
                'conversation_id' => $conversationId,
                'sender_id' => $senderId,
                'body' => $body,
                'message_type' => $attachments !== [] && $body === '' ? 'attachment' : ($context['type'] ?? 'text'),
                'offer_id' => $context['offer_id'] ?? null,
                'order_id' => $context['order_id'] ?? null,
                'created_at' => now(),
            ]);

            foreach ($attachments as $attachment) {
                $db->insert('message_attachments', [
                    'message_id' => $id,
                    'file_path' => $attachment['path'],
                    'original_name' => substr((string) ($attachment['name'] ?? ''), 0, 190),
                    'mime_type' => substr((string) ($attachment['mime'] ?? ''), 0, 80),
                    'file_size' => (int) ($attachment['size'] ?? 0),
                    'created_at' => now(),
                ]);
            }

            $isBuyer = (int) $conversation['buyer_id'] === $senderId;
            $db->statement(
                'UPDATE conversations
                 SET last_message_at = :n, last_message_preview = :p, updated_at = :n2,
                     buyer_unread = buyer_unread + :bi, seller_unread = seller_unread + :si,
                     buyer_archived = 0, seller_archived = 0
                 WHERE id = :id',
                [
                    'n' => now(),
                    'n2' => now(),
                    'p' => substr($body !== '' ? $body : '[attachment]', 0, 190),
                    'bi' => $isBuyer ? 0 : 1,
                    'si' => $isBuyer ? 1 : 0,
                    'id' => $conversationId,
                ]
            );

            return $id;
        });

        NotificationService::dispatch($otherId, 'new_message', [
            'body' => substr($body !== '' ? $body : 'Sent an attachment', 0, 150),
            'link' => '/dashboard/messages/' . $conversationId,
            'entity_type' => 'conversation',
            'entity_id' => $conversationId,
        ]);

        return ['ok' => true, 'message_id' => $messageId];
    }

    /** A message posted by the platform itself (offer made, order created…). */
    public static function systemMessage(int $conversationId, int $actorId, string $body, array $context = []): void
    {
        $db = Database::instance();
        $db->insert('messages', [
            'conversation_id' => $conversationId,
            'sender_id' => $actorId,
            'body' => $body,
            'message_type' => 'system',
            'offer_id' => $context['offer_id'] ?? null,
            'order_id' => $context['order_id'] ?? null,
            'created_at' => now(),
        ]);
        $db->update('conversations', [
            'last_message_at' => now(),
            'last_message_preview' => substr($body, 0, 190),
            'updated_at' => now(),
        ], ['id' => $conversationId]);
    }

    public static function conversations(int $userId, array $filters = []): array
    {
        $sql = 'SELECT c.*,
                       CASE WHEN c.buyer_id = :u THEN c.seller_id ELSE c.buyer_id END AS other_user_id,
                       CASE WHEN c.buyer_id = :u2 THEN c.buyer_unread ELSE c.seller_unread END AS unread,
                       CASE WHEN c.buyer_id = :u3 THEN "buyer" ELSE "seller" END AS my_role,
                       l.title AS listing_title, l.slug AS listing_slug,
                       r.title AS requirement_title,
                       o.reference AS order_reference
                FROM conversations c
                LEFT JOIN listings l ON l.id = c.listing_id
                LEFT JOIN wanted_requirements r ON r.id = c.requirement_id
                LEFT JOIN orders o ON o.id = c.order_id
                WHERE (c.buyer_id = :u4 OR c.seller_id = :u5)';
        $params = ['u' => $userId, 'u2' => $userId, 'u3' => $userId, 'u4' => $userId, 'u5' => $userId];

        if (!empty($filters['unread'])) {
            $sql .= ' AND ((c.buyer_id = :u6 AND c.buyer_unread > 0) OR (c.seller_id = :u7 AND c.seller_unread > 0))';
            $params['u6'] = $userId;
            $params['u7'] = $userId;
        }
        $sql .= ' ORDER BY c.last_message_at DESC, c.id DESC LIMIT 100';

        $rows = Database::instance()->select($sql, $params);
        if ($rows === []) {
            return [];
        }

        $otherIds = array_values(array_unique(array_map(static fn (array $r): int => (int) $r['other_user_id'], $rows)));
        // compileWhere() validates identifiers against [A-Za-z0-9_], so the
        // column is passed unqualified and the table alias is applied in the SQL.
        [$where, $whereParams] = Database::instance()->compileWhere(['id' => $otherIds]);
        $people = Database::instance()->select(
            'SELECT u.id, u.full_name, u.avatar, b.name AS business_name, b.logo, b.kyc_verified
             FROM users u LEFT JOIN businesses b ON b.user_id = u.id AND b.deleted_at IS NULL
             WHERE ' . str_replace('`id`', 'u.`id`', $where),
            $whereParams
        );
        $byId = [];
        foreach ($people as $person) {
            $byId[(int) $person['id']] = $person;
        }
        foreach ($rows as &$row) {
            $person = $byId[(int) $row['other_user_id']] ?? [];
            $row['other_name'] = $person['business_name'] ?? ($person['full_name'] ?? 'User');
            $row['other_avatar'] = $person['logo'] ?? ($person['avatar'] ?? null);
            $row['other_verified'] = (int) ($person['kyc_verified'] ?? 0);
        }
        return $rows;
    }

    public static function messages(int $conversationId, int $afterId = 0, int $limit = 100): array
    {
        return Database::instance()->select(
            'SELECT m.*, u.full_name AS sender_name, b.name AS sender_business
             FROM messages m
             INNER JOIN users u ON u.id = m.sender_id
             LEFT JOIN businesses b ON b.user_id = m.sender_id AND b.deleted_at IS NULL
             WHERE m.conversation_id = :c AND m.id > :after AND m.deleted_at IS NULL
             ORDER BY m.id ASC LIMIT ' . max(1, $limit),
            ['c' => $conversationId, 'after' => $afterId]
        );
    }

    public static function attachments(array $messageIds): array
    {
        if ($messageIds === []) {
            return [];
        }
        [$where, $params] = Database::instance()->compileWhere(['message_id' => $messageIds]);
        $rows = Database::instance()->select('SELECT * FROM message_attachments WHERE ' . $where . ' ORDER BY id', $params);
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['message_id']][] = $row;
        }
        return $grouped;
    }

    public static function markRead(int $conversationId, int $userId): void
    {
        $db = Database::instance();
        $conversation = $db->first('SELECT buyer_id, seller_id FROM conversations WHERE id = :id', ['id' => $conversationId]);
        if ($conversation === null) {
            return;
        }
        $column = (int) $conversation['buyer_id'] === $userId ? 'buyer_unread' : 'seller_unread';
        $db->statement("UPDATE conversations SET {$column} = 0 WHERE id = :id", ['id' => $conversationId]);
        $db->statement(
            'UPDATE messages SET read_at = :n WHERE conversation_id = :c AND sender_id <> :u AND read_at IS NULL',
            ['n' => now(), 'c' => $conversationId, 'u' => $userId]
        );
    }

    public static function detail(int $conversationId, int $userId): ?array
    {
        $row = Database::instance()->first(
            'SELECT c.*, l.title AS listing_title, l.slug AS listing_slug,
                    r.title AS requirement_title, r.slug AS requirement_slug,
                    o.reference AS order_reference
             FROM conversations c
             LEFT JOIN listings l ON l.id = c.listing_id
             LEFT JOIN wanted_requirements r ON r.id = c.requirement_id
             LEFT JOIN orders o ON o.id = c.order_id
             WHERE c.id = :id LIMIT 1',
            ['id' => $conversationId]
        );
        if ($row === null) {
            return null;
        }
        if ((int) $row['buyer_id'] !== $userId && (int) $row['seller_id'] !== $userId) {
            return null;
        }
        $otherId = (int) $row['buyer_id'] === $userId ? (int) $row['seller_id'] : (int) $row['buyer_id'];
        $other = Database::instance()->first(
            'SELECT u.id, u.full_name, u.avatar, u.last_active_at, b.name AS business_name, b.slug AS business_slug,
                    b.logo, b.kyc_verified, b.city_name
             FROM users u LEFT JOIN businesses b ON b.user_id = u.id AND b.deleted_at IS NULL
             WHERE u.id = :id',
            ['id' => $otherId]
        );
        $row['other'] = $other ?? [];
        $row['my_role'] = (int) $row['buyer_id'] === $userId ? 'buyer' : 'seller';
        return $row;
    }

    public static function block(int $userId, int $targetId, string $reason = ''): void
    {
        Database::instance()->statement(
            'INSERT IGNORE INTO blocked_users (user_id, blocked_user_id, reason, created_at) VALUES (:u, :t, :r, :c)',
            ['u' => $userId, 't' => $targetId, 'r' => substr($reason, 0, 190), 'c' => now()]
        );
        Database::instance()->statement(
            "UPDATE conversations SET status = 'blocked', updated_at = :n
             WHERE (buyer_id = :a AND seller_id = :b) OR (buyer_id = :c AND seller_id = :d)",
            ['n' => now(), 'a' => $userId, 'b' => $targetId, 'c' => $targetId, 'd' => $userId]
        );
    }

    public static function unblock(int $userId, int $targetId): void
    {
        Database::instance()->delete('blocked_users', ['user_id' => $userId, 'blocked_user_id' => $targetId]);
        Database::instance()->statement(
            "UPDATE conversations SET status = 'open', updated_at = :n
             WHERE ((buyer_id = :a AND seller_id = :b) OR (buyer_id = :c AND seller_id = :d)) AND status = 'blocked'",
            ['n' => now(), 'a' => $userId, 'b' => $targetId, 'c' => $targetId, 'd' => $userId]
        );
    }

    public static function unreadCount(int $userId): int
    {
        return (int) Database::instance()->scalar(
            'SELECT COALESCE(SUM(CASE WHEN buyer_id = :u THEN buyer_unread ELSE seller_unread END), 0)
             FROM conversations WHERE buyer_id = :u2 OR seller_id = :u3',
            ['u' => $userId, 'u2' => $userId, 'u3' => $userId],
            0
        );
    }
}
