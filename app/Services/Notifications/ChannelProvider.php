<?php

declare(strict_types=1);

namespace App\Services\Notifications;

/**
 * A delivery channel. Adding a provider (MSG91, Twilio, Gupshup, FCM…) means
 * implementing this interface — no change to the notification engine.
 */
interface ChannelProvider
{
    public function name(): string;

    /** False when credentials are missing; the queue then marks messages "skipped", not "failed". */
    public function isConfigured(): bool;

    /** @return array{ok: bool, message_id?: string, error?: string} */
    public function send(string $recipient, string $subject, string $body, array $context = []): array;
}
