<?php

declare(strict_types=1);

namespace App\Core;

/**
 * AES-256-GCM encryption for secrets at rest (GitHub token, SMTP password,
 * payment keys). Key material comes from config('app.key') which the installer
 * generates per deployment.
 */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';
    private const PREFIX = 'enc:v1:';

    private static function key(): string
    {
        $key = (string) Config::get('app.key', '');
        if ($key === '') {
            // Fall back to a deterministic but installation-specific key so that
            // encryption never silently becomes a no-op.
            $key = hash('sha256', (string) Config::get('database.name', 'scrapx') . CONFIG_FILE);
        }
        return hash('sha256', $key, true);
    }

    public static function encrypt(?string $plain): string
    {
        if ($plain === null || $plain === '') {
            return '';
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('Encryption failed.');
        }
        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(?string $payload): string
    {
        if ($payload === null || $payload === '') {
            return '';
        }
        if (!str_starts_with($payload, self::PREFIX)) {
            // Value stored before encryption was enabled — return as-is.
            return $payload;
        }
        $raw = base64_decode(substr($payload, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? '' : $plain;
    }

    public static function isEncrypted(?string $value): bool
    {
        return is_string($value) && str_starts_with($value, self::PREFIX);
    }
}
