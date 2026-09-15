<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\RateLimiter;

/**
 * One-time passwords for mobile/email verification, login and password reset.
 *
 * Only a SHA-256 hash of the code is stored, attempts are capped, and sending is
 * rate limited per destination and per IP. When no SMS provider is configured
 * the code is written to the log and (in non-production) surfaced to the UI, so
 * the flow is testable without pretending an SMS was delivered.
 */
final class OtpService
{
    public const PURPOSES = ['verify_mobile', 'verify_email', 'login', 'password_reset', 'sensitive_action'];

    /**
     * @return array{ok: bool, error?: string, message?: string, debug_code?: string, expires_in?: int}
     */
    public static function send(string $destination, string $purpose, string $channel = 'sms', ?int $userId = null, string $ip = ''): array
    {
        if (!in_array($purpose, self::PURPOSES, true)) {
            return ['ok' => false, 'error' => 'Invalid verification purpose.'];
        }

        $destination = trim($destination);
        if ($channel === 'sms') {
            $destination = substr(preg_replace('/\D/', '', $destination) ?? '', -10);
            if (!preg_match('/^[6-9]\d{9}$/', $destination)) {
                return ['ok' => false, 'error' => 'Enter a valid 10-digit Indian mobile number.'];
            }
        } elseif (!filter_var($destination, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Enter a valid email address.'];
        }

        // Throttle per destination (per hour) and per IP (per 10 minutes).
        $limit = SettingsService::int('otp_resend_limit', 3);
        if (RateLimiter::tooManyAttempts('otp:send:' . $destination, $limit, 3600)) {
            return ['ok' => false, 'error' => 'Too many codes requested. Please wait an hour and try again.'];
        }
        if ($ip !== '' && RateLimiter::tooManyAttempts('otp:ip:' . $ip, $limit * 4, 600)) {
            return ['ok' => false, 'error' => 'Too many verification attempts from this network. Please try again later.'];
        }

        $length = max(4, min(8, SettingsService::int('otp_length', 6)));
        $expiryMinutes = max(1, SettingsService::int('otp_expiry_minutes', 10));
        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);

        $db = Database::instance();
        // Retire any previous unverified codes for this destination + purpose.
        $db->statement(
            'UPDATE otp_codes SET expires_at = :now WHERE destination = :d AND purpose = :p AND verified_at IS NULL AND expires_at > :now2',
            ['now' => now(), 'd' => $destination, 'p' => $purpose, 'now2' => now()]
        );

        $db->insert('otp_codes', [
            'user_id' => $userId,
            'channel' => $channel,
            'destination' => $destination,
            'purpose' => $purpose,
            'code_hash' => hash('sha256', $code),
            'attempts' => 0,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + ($expiryMinutes * 60)),
            'ip' => $ip !== '' ? $ip : null,
            'created_at' => now(),
        ]);

        RateLimiter::hit('otp:send:' . $destination, 3600);
        if ($ip !== '') {
            RateLimiter::hit('otp:ip:' . $ip, 600);
        }

        $vars = [
            'code' => $code,
            'minutes' => $expiryMinutes,
            'site_name' => (string) SettingsService::get('site_name', 'ScrapX'),
        ];

        $delivered = self::deliver($channel, $destination, $vars, $userId);

        $response = [
            'ok' => true,
            'expires_in' => $expiryMinutes * 60,
            'message' => $delivered['ok']
                ? 'Verification code sent to ' . self::maskDestination($destination, $channel) . '.'
                : 'Verification code generated. ' . $delivered['message'],
        ];

        // Without a provider the operator still needs a way to complete setup.
        if (!$delivered['ok']) {
            logger()->warning('OTP could not be delivered; code written to log', [
                'destination' => self::maskDestination($destination, $channel),
                'purpose' => $purpose,
                'code' => $code,
            ]);
            if (!self::isProduction()) {
                $response['debug_code'] = $code;
            }
        }

        return $response;
    }

    private static function deliver(string $channel, string $destination, array $vars, ?int $userId): array
    {
        $provider = NotificationService::provider($channel);
        if (!NotificationService::channelEnabled($channel) || !$provider->isConfigured()) {
            return [
                'ok' => false,
                'message' => 'No ' . strtoupper($channel) . ' provider is configured yet — the code is in storage/logs for the administrator.',
            ];
        }

        $template = Database::instance()->first(
            'SELECT subject, body FROM email_templates WHERE event = :e AND channel = :c AND is_active = 1',
            ['e' => 'otp', 'c' => $channel]
        ) ?? [];

        $body = NotificationService::render(
            (string) ($template['body'] ?? '{{code}} is your {{site_name}} verification code. Valid for {{minutes}} minutes.'),
            $vars
        );
        $subject = NotificationService::render((string) ($template['subject'] ?? 'Your verification code'), $vars);

        // OTPs go out immediately rather than through the queue.
        $result = $provider->send($destination, $subject, $body, $vars);

        Database::instance()->insert('notification_queue', [
            'user_id' => $userId,
            'channel' => $channel,
            'event' => 'otp',
            'recipient' => $destination,
            'subject' => $subject,
            'body' => '[code redacted]',
            'status' => $result['ok'] ? 'sent' : 'failed',
            'attempts' => 1,
            'last_error' => $result['ok'] ? null : substr((string) ($result['error'] ?? ''), 0, 255),
            'provider' => $provider->name(),
            'provider_message_id' => $result['message_id'] ?? null,
            'scheduled_at' => now(),
            'sent_at' => $result['ok'] ? now() : null,
            'created_at' => now(),
        ]);

        return ['ok' => $result['ok'], 'message' => (string) ($result['error'] ?? '')];
    }

    /** @return array{ok: bool, error?: string, user_id?: ?int} */
    public static function verify(string $destination, string $code, string $purpose): array
    {
        $destination = trim($destination);
        if (!str_contains($destination, '@')) {
            $destination = substr(preg_replace('/\D/', '', $destination) ?? '', -10);
        }
        $code = preg_replace('/\D/', '', $code) ?? '';
        if ($code === '') {
            return ['ok' => false, 'error' => 'Enter the verification code.'];
        }

        $db = Database::instance();
        $otp = $db->first(
            'SELECT * FROM otp_codes WHERE destination = :d AND purpose = :p AND verified_at IS NULL
             ORDER BY id DESC LIMIT 1',
            ['d' => $destination, 'p' => $purpose]
        );

        if ($otp === null) {
            return ['ok' => false, 'error' => 'No active verification code. Please request a new one.'];
        }
        if (strtotime((string) $otp['expires_at'] . ' UTC') < time()) {
            return ['ok' => false, 'error' => 'This code has expired. Please request a new one.'];
        }

        $maxAttempts = SettingsService::int('otp_max_attempts', 5);
        if ((int) $otp['attempts'] >= $maxAttempts) {
            return ['ok' => false, 'error' => 'Too many incorrect attempts. Please request a new code.'];
        }

        $db->statement('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = :id', ['id' => (int) $otp['id']]);

        if (!hash_equals((string) $otp['code_hash'], hash('sha256', $code))) {
            $remaining = $maxAttempts - ((int) $otp['attempts'] + 1);
            return [
                'ok' => false,
                'error' => 'That code is not correct.' . ($remaining > 0 ? " {$remaining} attempt(s) left." : ' Please request a new code.'),
            ];
        }

        $db->update('otp_codes', ['verified_at' => now()], ['id' => (int) $otp['id']]);
        RateLimiter::clear('otp:send:' . $destination, 3600);

        return ['ok' => true, 'user_id' => $otp['user_id'] !== null ? (int) $otp['user_id'] : null];
    }

    public static function maskDestination(string $destination, string $channel): string
    {
        if ($channel === 'sms') {
            return strlen($destination) === 10 ? substr($destination, 0, 2) . '••••' . substr($destination, -3) : $destination;
        }
        [$local, $domain] = array_pad(explode('@', $destination, 2), 2, '');
        return substr($local, 0, 2) . '•••@' . $domain;
    }

    private static function isProduction(): bool
    {
        return \App\Core\Config::get('app.env', 'production') === 'production'
            && !\App\Core\Config::get('app.debug', false);
    }

    /** Was this destination verified for this purpose recently? */
    public static function wasVerified(string $destination, string $purpose, int $withinSeconds = 1800): bool
    {
        return Database::instance()->first(
            'SELECT id FROM otp_codes WHERE destination = :d AND purpose = :p AND verified_at IS NOT NULL
             AND verified_at >= :since ORDER BY id DESC LIMIT 1',
            ['d' => $destination, 'p' => $purpose, 'since' => gmdate('Y-m-d H:i:s', time() - $withinSeconds)]
        ) !== null;
    }
}
