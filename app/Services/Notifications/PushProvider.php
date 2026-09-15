<?php

declare(strict_types=1);

namespace App\Services\Notifications;

/**
 * Web-push placeholder.
 *
 * The PWA service worker already listens for push events, but no push service
 * (FCM / VAPID) is wired up, and subscriptions are not collected yet. This
 * provider therefore reports itself as NOT configured, so queued push messages
 * are marked "skipped" and visibly reported in the admin queue rather than
 * silently discarded. Implementing push means filling in send() and returning
 * true from isConfigured() — nothing else in the engine changes.
 */
final class PushProvider implements ChannelProvider
{
    public function name(): string
    {
        return 'web_push';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function send(string $recipient, string $subject, string $body, array $context = []): array
    {
        return ['ok' => false, 'error' => 'Web push is not configured on this installation'];
    }
}
