<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Services\Notifications\ChannelProvider;
use App\Services\Notifications\MailProvider;
use App\Services\Notifications\PushProvider;
use App\Services\Notifications\SmsProvider;
use App\Services\Notifications\WhatsAppProvider;

/**
 * Central notification engine.
 *
 * dispatch() always writes an in-app notification, then queues one row per
 * enabled outbound channel. The scheduler drains the queue through the
 * ChannelProvider implementations, so a missing SMS credential can never break
 * a bid, an order or a login.
 */
final class NotificationService
{
    /** event => [title template, default channels, icon, level] */
    public const EVENTS = [
        'welcome' => ['Welcome to :site', ['email'], 'bi-stars', 'success'],
        'kyc_submitted' => ['KYC documents submitted', ['email'], 'bi-file-earmark-check', 'info'],
        'kyc_approved' => ['Your business is verified', ['email', 'sms'], 'bi-patch-check', 'success'],
        'kyc_rejected' => ['KYC needs attention', ['email'], 'bi-exclamation-octagon', 'danger'],
        'listing_approved' => ['Listing approved', ['email'], 'bi-check2-circle', 'success'],
        'listing_rejected' => ['Listing rejected', ['email'], 'bi-x-circle', 'danger'],
        'listing_expiring' => ['Listing expiring soon', ['email'], 'bi-hourglass-split', 'warning'],
        'new_bid' => ['New bid received', ['email'], 'bi-hammer', 'info'],
        'outbid' => ['You have been outbid', ['email', 'sms'], 'bi-arrow-down-circle', 'warning'],
        'auction_starting' => ['Auction starting soon', ['email'], 'bi-play-circle', 'info'],
        'auction_ending' => ['Auction ending soon', ['email', 'sms'], 'bi-alarm', 'warning'],
        'auction_won' => ['You won the auction', ['email', 'sms'], 'bi-trophy', 'success'],
        'auction_lost' => ['Auction closed', ['email'], 'bi-emoji-neutral', 'info'],
        'auction_unsold' => ['Auction closed below reserve', ['email'], 'bi-dash-circle', 'warning'],
        'new_rfq' => ['New RFQ you can quote on', ['email'], 'bi-file-earmark-text', 'info'],
        'rfq_quote' => ['New quote received', ['email'], 'bi-receipt', 'info'],
        'rfq_awarded' => ['RFQ awarded', ['email'], 'bi-award', 'success'],
        'requirement_offer' => ['Seller responded to your requirement', ['email'], 'bi-box-seam', 'info'],
        'new_offer' => ['New offer received', ['email'], 'bi-tag', 'info'],
        'offer_countered' => ['Your offer was countered', ['email'], 'bi-arrow-left-right', 'warning'],
        'offer_accepted' => ['Offer accepted', ['email', 'sms'], 'bi-check-circle', 'success'],
        'offer_rejected' => ['Offer declined', ['email'], 'bi-x-circle', 'warning'],
        'order_created' => ['Order created', ['email', 'sms'], 'bi-bag-check', 'success'],
        'order_status' => ['Order status updated', ['email'], 'bi-arrow-repeat', 'info'],
        'weighment_recorded' => ['Weighment recorded', ['email'], 'bi-speedometer2', 'info'],
        'delivery_update' => ['Delivery update', ['email'], 'bi-truck', 'info'],
        'payment_received' => ['Payment recorded', ['email'], 'bi-cash-coin', 'success'],
        'payment_pending' => ['Payment pending', ['email'], 'bi-clock-history', 'warning'],
        'new_message' => ['New message', [], 'bi-chat-dots', 'info'],
        'review_received' => ['You received a review', ['email'], 'bi-star', 'info'],
        'dispute_update' => ['Dispute updated', ['email'], 'bi-shield-exclamation', 'warning'],
        'account_status' => ['Account status changed', ['email'], 'bi-person-gear', 'warning'],
    ];

    /**
     * @param array{title?:string, body?:string, link?:string, entity_type?:string, entity_id?:int,
     *              channels?:array, vars?:array, level?:string} $payload
     */
    public static function dispatch(int $userId, string $event, array $payload = []): void
    {
        $meta = self::EVENTS[$event] ?? ['Notification', [], 'bi-bell', 'info'];
        $vars = $payload['vars'] ?? [];
        $vars['site_name'] ??= (string) SettingsService::get('site_name', 'ScrapX');

        $title = $payload['title'] ?? str_replace(':site', $vars['site_name'], $meta[0]);
        $body = $payload['body'] ?? '';
        $link = $payload['link'] ?? null;

        try {
            Database::instance()->insert('notifications', [
                'user_id' => $userId,
                'event' => $event,
                'title' => substr($title, 0, 190),
                'body' => substr($body, 0, 500),
                'link' => $link !== null ? substr($link, 0, 255) : null,
                'icon' => $payload['icon'] ?? $meta[2],
                'level' => $payload['level'] ?? $meta[3],
                'entity_type' => $payload['entity_type'] ?? null,
                'entity_id' => $payload['entity_id'] ?? null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Logger::instance()->warning('In-app notification failed: ' . $e->getMessage(), ['event' => $event]);
            return;
        }

        $channels = $payload['channels'] ?? $meta[1];
        if ($channels === []) {
            return;
        }

        $user = Database::instance()->first(
            'SELECT id, full_name, email, mobile, notify_email, notify_sms, notify_whatsapp FROM users WHERE id = :id',
            ['id' => $userId]
        );
        if ($user === null) {
            return;
        }
        $vars['name'] ??= $user['full_name'];
        $vars['link'] ??= $link !== null ? base_url(ltrim($link, '/')) : base_url('/dashboard');

        foreach ($channels as $channel) {
            self::queue($channel, $user, $event, $vars, $title, $body);
        }
    }

    /** Notify several users about the same event. */
    public static function dispatchMany(array $userIds, string $event, array $payload = []): void
    {
        foreach (array_unique(array_filter($userIds)) as $userId) {
            self::dispatch((int) $userId, $event, $payload);
        }
    }

    private static function queue(string $channel, array $user, string $event, array $vars, string $title, string $body): void
    {
        if (!self::channelEnabled($channel)) {
            return;
        }
        // Honour the recipient's own preferences.
        $preferenceColumn = 'notify_' . $channel;
        if (array_key_exists($preferenceColumn, $user) && (int) $user[$preferenceColumn] !== 1) {
            return;
        }

        $recipient = match ($channel) {
            'email' => (string) ($user['email'] ?? ''),
            'sms', 'whatsapp' => (string) ($user['mobile'] ?? ''),
            default => (string) $user['id'],
        };
        if ($recipient === '') {
            return;
        }

        $template = self::template($event, $channel);
        $subject = $template['subject'] ?? $title;
        $content = $template['body'] ?? ($body !== '' ? $body : $title);

        $subject = self::render((string) $subject, $vars);
        $content = self::render((string) $content, $vars);

        try {
            Database::instance()->insert('notification_queue', [
                'user_id' => (int) $user['id'],
                'channel' => $channel,
                'event' => $event,
                'recipient' => substr($recipient, 0, 190),
                'subject' => substr($subject, 0, 190),
                'body' => $content,
                'payload' => json_encode($vars, JSON_UNESCAPED_UNICODE),
                'status' => 'queued',
                'scheduled_at' => now(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Logger::instance()->warning('Notification queue insert failed: ' . $e->getMessage());
        }
    }

    public static function channelEnabled(string $channel): bool
    {
        return match ($channel) {
            'email' => SettingsService::bool('notify_channel_email', true),
            'sms' => SettingsService::bool('notify_channel_sms', false),
            'whatsapp' => SettingsService::bool('notify_channel_whatsapp', false),
            'push' => false,
            default => false,
        };
    }

    private static function template(string $event, string $channel): array
    {
        static $cache = [];
        $key = $event . ':' . $channel;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $row = Database::instance()->first(
            'SELECT subject, body FROM email_templates WHERE event = :e AND channel = :c AND is_active = 1 LIMIT 1',
            ['e' => $event, 'c' => $channel]
        );
        return $cache[$key] = $row ?? [];
    }

    public static function render(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $template = str_replace('{{' . $key . '}}', (string) $value, $template);
            }
        }
        // Drop any placeholder we could not fill rather than showing {{x}} to a user.
        return preg_replace('/\{\{[a-z_]+\}\}/i', '', $template) ?? $template;
    }

    public static function provider(string $channel): ChannelProvider
    {
        return match ($channel) {
            'email' => new MailProvider(),
            'sms' => new SmsProvider(),
            'whatsapp' => new WhatsAppProvider(),
            default => new PushProvider(),
        };
    }

    /** Drain the outbound queue. Called by the scheduler. */
    public static function processQueue(int $limit = 25): array
    {
        $db = Database::instance();
        $maxAttempts = SettingsService::int('notify_max_attempts', 3);
        $rows = $db->select(
            "SELECT * FROM notification_queue
             WHERE status = 'queued' AND scheduled_at <= :now AND attempts < :max
             ORDER BY id LIMIT " . max(1, $limit),
            ['now' => now(), 'max' => $maxAttempts]
        );

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $db->update('notification_queue', ['status' => 'processing', 'attempts' => (int) $row['attempts'] + 1], ['id' => $id]);

            $provider = self::provider((string) $row['channel']);
            if (!$provider->isConfigured()) {
                $db->update('notification_queue', [
                    'status' => 'skipped',
                    'last_error' => $provider->name() . ' is not configured',
                    'provider' => $provider->name(),
                ], ['id' => $id]);
                $skipped++;
                continue;
            }

            try {
                $result = $provider->send(
                    (string) $row['recipient'],
                    (string) ($row['subject'] ?? ''),
                    (string) $row['body'],
                    json_decode((string) ($row['payload'] ?? '{}'), true) ?: []
                );
                if ($result['ok']) {
                    $db->update('notification_queue', [
                        'status' => 'sent',
                        'sent_at' => now(),
                        'provider' => $provider->name(),
                        'provider_message_id' => $result['message_id'] ?? null,
                        'last_error' => null,
                    ], ['id' => $id]);
                    $sent++;
                } else {
                    $attempts = (int) $row['attempts'] + 1;
                    $db->update('notification_queue', [
                        'status' => $attempts >= $maxAttempts ? 'failed' : 'queued',
                        'last_error' => substr((string) ($result['error'] ?? 'unknown error'), 0, 255),
                        'provider' => $provider->name(),
                        'scheduled_at' => gmdate('Y-m-d H:i:s', time() + (60 * $attempts)),
                    ], ['id' => $id]);
                    $failed++;
                }
            } catch (\Throwable $e) {
                $attempts = (int) $row['attempts'] + 1;
                $db->update('notification_queue', [
                    'status' => $attempts >= $maxAttempts ? 'failed' : 'queued',
                    'last_error' => substr($e->getMessage(), 0, 255),
                ], ['id' => $id]);
                $failed++;
            }
        }

        return ['processed' => count($rows), 'sent' => $sent, 'failed' => $failed, 'skipped' => $skipped];
    }

    public static function unreadCount(int $userId): int
    {
        return (int) Database::instance()->scalar(
            'SELECT COUNT(*) FROM notifications WHERE user_id = :u AND read_at IS NULL',
            ['u' => $userId],
            0
        );
    }

    public static function recent(int $userId, int $limit = 10): array
    {
        return Database::instance()->select(
            'SELECT * FROM notifications WHERE user_id = :u ORDER BY id DESC LIMIT ' . max(1, $limit),
            ['u' => $userId]
        );
    }

    public static function markRead(int $userId, ?int $notificationId = null): int
    {
        if ($notificationId !== null) {
            return Database::instance()->statement(
                'UPDATE notifications SET read_at = :n WHERE id = :id AND user_id = :u AND read_at IS NULL',
                ['n' => now(), 'id' => $notificationId, 'u' => $userId]
            );
        }
        return Database::instance()->statement(
            'UPDATE notifications SET read_at = :n WHERE user_id = :u AND read_at IS NULL',
            ['n' => now(), 'u' => $userId]
        );
    }
}
