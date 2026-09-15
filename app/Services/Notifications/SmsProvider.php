<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Services\SettingsService;

/**
 * Generic HTTP SMS gateway.
 *
 * Most Indian providers (MSG91, TextLocal, Fast2SMS, Kaleyra…) accept a simple
 * GET/POST with an API key, sender id and DLT template id. The endpoint is a
 * setting with {{placeholders}}, so a new provider is a configuration change:
 *
 *   https://api.example.com/send?key={{api_key}}&to={{mobile}}&sender={{sender}}
 *     &template={{template_id}}&message={{message}}
 *
 * Until an endpoint and key are configured, isConfigured() returns false and
 * the queue marks the message "skipped" — it never pretends to have sent it.
 */
final class SmsProvider implements ChannelProvider
{
    public function name(): string
    {
        $provider = (string) SettingsService::get('sms_provider', '');
        return $provider !== '' ? $provider : 'sms';
    }

    public function isConfigured(): bool
    {
        return SettingsService::configured('sms_api_url', 'sms_api_key');
    }

    public function send(string $recipient, string $subject, string $body, array $context = []): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'SMS provider is not configured'];
        }

        $mobile = preg_replace('/\D/', '', $recipient) ?? '';
        if (strlen($mobile) < 10) {
            return ['ok' => false, 'error' => 'Invalid mobile number'];
        }
        $mobile = substr($mobile, -10);

        $replacements = [
            '{{api_key}}' => rawurlencode((string) SettingsService::get('sms_api_key', '')),
            '{{mobile}}' => rawurlencode($mobile),
            '{{mobile_91}}' => rawurlencode('91' . $mobile),
            '{{sender}}' => rawurlencode((string) SettingsService::get('sms_sender_id', '')),
            '{{template_id}}' => rawurlencode((string) SettingsService::get('sms_dlt_template_id', '')),
            '{{message}}' => rawurlencode($this->plainText($body)),
        ];

        $url = str_replace(array_keys($replacements), array_values($replacements), (string) SettingsService::get('sms_api_url', ''));

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'ScrapX/1.0',
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($response === false) {
            return ['ok' => false, 'error' => 'SMS request failed: ' . $error];
        }
        if ($status < 200 || $status >= 300) {
            return ['ok' => false, 'error' => 'SMS gateway returned HTTP ' . $status];
        }

        $messageId = null;
        $decoded = json_decode((string) $response, true);
        if (is_array($decoded)) {
            $messageId = (string) ($decoded['message_id'] ?? $decoded['request_id'] ?? $decoded['id'] ?? '');
        }

        return ['ok' => true, 'message_id' => $messageId ?: substr(md5((string) $response), 0, 16)];
    }

    private function plainText(string $body): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $body)), ENT_QUOTES, 'UTF-8');
        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }
}
