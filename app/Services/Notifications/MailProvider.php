<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Services\SettingsService;

/**
 * Email delivery. Two drivers, neither needing Composer:
 *  - "mail": PHP's mail() — works on most shared hosting out of the box.
 *  - "smtp": a small, dependency-free SMTP client (AUTH LOGIN over TLS/SSL).
 */
final class MailProvider implements ChannelProvider
{
    public function name(): string
    {
        return SettingsService::get('mail_driver', 'mail') === 'smtp' ? 'smtp' : 'php_mail';
    }

    public function isConfigured(): bool
    {
        $from = (string) SettingsService::get('mail_from_address', '');
        if ($from === '') {
            return false;
        }
        if (SettingsService::get('mail_driver', 'mail') === 'smtp') {
            return SettingsService::configured('smtp_host', 'smtp_username', 'smtp_password');
        }
        return function_exists('mail');
    }

    public function send(string $recipient, string $subject, string $body, array $context = []): array
    {
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Invalid email address'];
        }

        $fromAddress = (string) SettingsService::get('mail_from_address', '');
        $fromName = (string) SettingsService::get('mail_from_name', 'ScrapX');
        $html = $this->wrap($subject, $body);

        if (SettingsService::get('mail_driver', 'mail') === 'smtp') {
            return $this->sendSmtp($recipient, $subject, $html, $fromAddress, $fromName);
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->encodeName($fromName) . ' <' . $fromAddress . '>',
            'Reply-To: ' . $fromAddress,
            'X-Mailer: ScrapX',
        ];
        $ok = @mail($recipient, $this->encodeSubject($subject), $html, implode("\r\n", $headers));
        return $ok
            ? ['ok' => true, 'message_id' => 'mail-' . substr(md5($recipient . microtime()), 0, 12)]
            : ['ok' => false, 'error' => 'PHP mail() returned false'];
    }

    private function sendSmtp(string $to, string $subject, string $html, string $fromAddress, string $fromName): array
    {
        $host = (string) SettingsService::get('smtp_host', '');
        $port = SettingsService::int('smtp_port', 587);
        $user = (string) SettingsService::get('smtp_username', '');
        $pass = (string) SettingsService::get('smtp_password', '');
        $encryption = (string) SettingsService::get('smtp_encryption', 'tls');

        $transport = $encryption === 'ssl' ? 'ssl://' : '';
        $socket = @stream_socket_client(
            $transport . $host . ':' . $port,
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT
        );
        if (!$socket) {
            return ['ok' => false, 'error' => "SMTP connect failed: {$errstr}"];
        }
        stream_set_timeout($socket, 15);

        $read = static function () use ($socket): string {
            $data = '';
            while ($line = fgets($socket, 515)) {
                $data .= $line;
                if (strlen($line) < 4 || $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        $write = static function (string $command) use ($socket): void {
            fwrite($socket, $command . "\r\n");
        };
        $expect = static function (string $response, string $code, string $step) use ($socket): ?array {
            if (!str_starts_with(trim($response), $code)) {
                fclose($socket);
                return ['ok' => false, 'error' => "SMTP {$step} failed: " . trim($response)];
            }
            return null;
        };

        if ($error = $expect($read(), '220', 'greeting')) {
            return $error;
        }
        $hostname = parse_url(base_url('/'), PHP_URL_HOST) ?: 'localhost';
        $write('EHLO ' . $hostname);
        if ($error = $expect($read(), '250', 'EHLO')) {
            return $error;
        }

        if ($encryption === 'tls') {
            $write('STARTTLS');
            if ($error = $expect($read(), '220', 'STARTTLS')) {
                return $error;
            }
            if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                return ['ok' => false, 'error' => 'SMTP TLS negotiation failed'];
            }
            $write('EHLO ' . $hostname);
            if ($error = $expect($read(), '250', 'EHLO (TLS)')) {
                return $error;
            }
        }

        if ($user !== '') {
            $write('AUTH LOGIN');
            if ($error = $expect($read(), '334', 'AUTH')) {
                return $error;
            }
            $write(base64_encode($user));
            if ($error = $expect($read(), '334', 'username')) {
                return $error;
            }
            $write(base64_encode($pass));
            if ($error = $expect($read(), '235', 'password')) {
                return $error;
            }
        }

        $write('MAIL FROM:<' . $fromAddress . '>');
        if ($error = $expect($read(), '250', 'MAIL FROM')) {
            return $error;
        }
        $write('RCPT TO:<' . $to . '>');
        if ($error = $expect($read(), '250', 'RCPT TO')) {
            return $error;
        }
        $write('DATA');
        if ($error = $expect($read(), '354', 'DATA')) {
            return $error;
        }

        $messageId = '<' . bin2hex(random_bytes(12)) . '@' . $hostname . '>';
        $headers = [
            'Date: ' . date('r'),
            'From: ' . $this->encodeName($fromName) . ' <' . $fromAddress . '>',
            'To: <' . $to . '>',
            'Subject: ' . $this->encodeSubject($subject),
            'Message-ID: ' . $messageId,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        $payload = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($html), 76, "\r\n");
        fwrite($socket, $payload . "\r\n.\r\n");
        if ($error = $expect($read(), '250', 'send')) {
            return $error;
        }

        $write('QUIT');
        @fclose($socket);

        return ['ok' => true, 'message_id' => trim($messageId, '<>')];
    }

    private function encodeSubject(string $subject): string
    {
        return '=?UTF-8?B?' . base64_encode($subject) . '?=';
    }

    private function encodeName(string $name): string
    {
        return preg_match('/[^\x20-\x7E]/', $name) ? $this->encodeSubject($name) : '"' . str_replace('"', '', $name) . '"';
    }

    /** Wrap the template body in a responsive shell so every email looks the same. */
    private function wrap(string $subject, string $body): string
    {
        $siteName = e((string) SettingsService::get('site_name', 'ScrapX'));
        $year = gmdate('Y');
        $url = base_url('/');
        return <<<HTML
<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$subject}</title></head>
<body style="margin:0;padding:24px;background:#f1f5f9;font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;color:#0f172a">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0">
<tr><td style="background:#0f766e;padding:20px 24px">
    <a href="{$url}" style="color:#ffffff;font-size:20px;font-weight:700;text-decoration:none">{$siteName}</a>
</td></tr>
<tr><td style="padding:24px;font-size:15px;line-height:1.6">{$body}</td></tr>
<tr><td style="padding:16px 24px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b">
    &copy; {$year} {$siteName}. You are receiving this because you have an account on our marketplace.
</td></tr>
</table></body></html>
HTML;
    }
}
