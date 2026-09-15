<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Services\SettingsService;

/**
 * WhatsApp Cloud API provider.
 *
 * Meta's Cloud API only accepts pre-approved template messages outside a
 * 24-hour service window, so this sends a template message when a template name
 * is supplied in the notification context, and a plain text message otherwise.
 * Not configured → skipped, never silently dropped.
 */
final class WhatsAppProvider implements ChannelProvider
{
    private const API_VERSION = 'v21.0';

    public function name(): string
    {
        return 'whatsapp_cloud';
    }

    public function isConfigured(): bool
    {
        return SettingsService::get('whatsapp_provider', '') === 'cloud_api'
            && SettingsService::configured('whatsapp_phone_number_id', 'whatsapp_access_token');
    }

    public function send(string $recipient, string $subject, string $body, array $context = []): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'WhatsApp Cloud API is not configured'];
        }

        $mobile = preg_replace('/\D/', '', $recipient) ?? '';
        if (strlen($mobile) === 10) {
            $mobile = '91' . $mobile;
        }
        if (strlen($mobile) < 11) {
            return ['ok' => false, 'error' => 'Invalid WhatsApp number'];
        }

        $phoneNumberId = (string) SettingsService::get('whatsapp_phone_number_id', '');
        $token = (string) SettingsService::get('whatsapp_access_token', '');
        $url = sprintf('https://graph.facebook.com/%s/%s/messages', self::API_VERSION, $phoneNumberId);

        if (!empty($context['template'])) {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $mobile,
                'type' => 'template',
                'template' => [
                    'name' => (string) $context['template'],
                    'language' => ['code' => (string) ($context['language'] ?? 'en')],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => array_map(
                            static fn ($value): array => ['type' => 'text', 'text' => (string) $value],
                            (array) ($context['template_params'] ?? [])
                        ),
                    ]],
                ],
            ];
        } else {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $mobile,
                'type' => 'text',
                'text' => ['preview_url' => false, 'body' => $this->plainText($body)],
            ];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'error' => 'WhatsApp request failed: ' . $error];
        }

        $decoded = json_decode((string) $response, true);
        if ($status < 200 || $status >= 300) {
            $message = $decoded['error']['message'] ?? ('HTTP ' . $status);
            return ['ok' => false, 'error' => 'WhatsApp API: ' . $message];
        }

        return ['ok' => true, 'message_id' => (string) ($decoded['messages'][0]['id'] ?? '')];
    }

    private function plainText(string $body): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $body)), ENT_QUOTES, 'UTF-8');
        return trim(preg_replace('/\n{3,}/', "\n\n", $text) ?? $text);
    }
}
