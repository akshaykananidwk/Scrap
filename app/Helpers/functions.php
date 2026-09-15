<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Lang;
use App\Core\Session;
use App\Core\View;

if (!function_exists('e')) {
    /** Escape for HTML output. Used in every template — never echo raw user data. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return App\Services\SettingsService::get($key, $default);
    }
}

if (!function_exists('__')) {
    function __(string $key, array $replace = []): string
    {
        return Lang::get($key, $replace);
    }
}

if (!function_exists('base_url')) {
    /**
     * Absolute URL. Use for anything that leaves the page — emails, the sitemap,
     * canonical and Open Graph tags.
     *
     * The origin follows the host the visitor actually used, so a site reached
     * over https, or with a www prefix, does not generate links back to the
     * other spelling. Since Content-Security-Policy restricts scripts, styles
     * and form targets to 'self', a mismatch there does not merely look untidy:
     * the browser blocks the site's own CSS, JS and form posts.
     *
     * The request host is only trusted when it resolves to the configured host,
     * so a forged Host header cannot rewrite links in, say, a password-reset
     * email.
     */
    function base_url(string $path = ''): string
    {
        $configured = rtrim((string) Config::get('app.url', ''), '/');
        $requestHost = (string) ($_SERVER['HTTP_HOST'] ?? '');

        if ($requestHost !== '' && preg_match('/^[A-Za-z0-9.\-]+(:\d+)?$/', $requestHost) === 1) {
            $configuredHost = (string) parse_url($configured, PHP_URL_HOST);
            $bare = static fn (string $host): string => strtolower(preg_replace('/^www\./i', '', explode(':', $host)[0]) ?? '');

            if ($configured === '' || $bare($configuredHost) === $bare($requestHost)) {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http';
                $configured = $scheme . '://' . $requestHost . base_path();
            }
        }

        if ($configured === '') {
            $configured = 'http://localhost';
        }

        return $configured . '/' . ltrim($path, '/');
    }
}

if (!function_exists('base_path')) {
    /**
     * The sub-directory the application is installed in, '' at a domain root.
     * Lets assets be referenced relative to the site root without assuming one.
     */
    function base_path(): string
    {
        $path = (string) parse_url((string) Config::get('app.url', ''), PHP_URL_PATH);
        return rtrim($path, '/');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        return base_url($path);
    }
}

if (!function_exists('asset')) {
    /**
     * Root-relative on purpose. An asset is always served by the same site as
     * the page referencing it, so tying it to a configured absolute URL only
     * creates a way for the two to disagree.
     */
    function asset(string $path): string
    {
        return base_path() . '/public/' . ltrim($path, '/');
    }
}

if (!function_exists('upload_url')) {
    function upload_url(?string $path): string
    {
        if (!$path) {
            return base_path() . '/public/img/placeholder.svg';
        }
        return base_path() . '/uploads/' . ltrim($path, '/');
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        $old = Session::get('_old', []);
        return $old[$key] ?? $default;
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(App\Core\Csrf::token()) . '">';
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return App\Core\Csrf::token();
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('auth')) {
    function auth(): ?array
    {
        return App\Core\Auth::user();
    }
}

if (!function_exists('auth_id')) {
    function auth_id(): ?int
    {
        return App\Core\Auth::id();
    }
}

if (!function_exists('can')) {
    function can(string $permission): bool
    {
        return App\Core\Auth::can($permission);
    }
}

if (!function_exists('view')) {
    function view(string $template, array $data = []): string
    {
        return View::render($template, $data);
    }
}

if (!function_exists('money')) {
    /** Format an amount as Indian rupees with Indian digit grouping. */
    function money(float|string|null $amount, bool $symbol = true): string
    {
        $amount = (float) ($amount ?? 0);
        $negative = $amount < 0;
        $amount = abs($amount);
        $parts = explode('.', number_format($amount, 2, '.', ''));
        $int = $parts[0];
        $dec = $parts[1];
        $last3 = substr($int, -3);
        $rest = substr($int, 0, -3);
        if ($rest !== '' && $rest !== false) {
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $formatted = $rest . ',' . $last3;
        } else {
            $formatted = $last3;
        }
        return ($negative ? '-' : '') . ($symbol ? '₹' : '') . $formatted . '.' . $dec;
    }
}

if (!function_exists('qty')) {
    function qty(float|string|null $value, ?string $unit = null): string
    {
        $value = (float) ($value ?? 0);
        $formatted = rtrim(rtrim(number_format($value, 3, '.', ','), '0'), '.');
        return $formatted . ($unit ? ' ' . $unit : '');
    }
}

if (!function_exists('app_tz')) {
    function app_tz(): DateTimeZone
    {
        return new DateTimeZone((string) Config::get('app.timezone', 'Asia/Kolkata'));
    }
}

if (!function_exists('fmt_dt')) {
    /** Render a UTC database timestamp in the display timezone. */
    function fmt_dt(?string $utc, string $format = 'd M Y, h:i A'): string
    {
        if (!$utc) {
            return '—';
        }
        try {
            $dt = new DateTime($utc, new DateTimeZone('UTC'));
            $dt->setTimezone(app_tz());
            return $dt->format($format);
        } catch (Throwable) {
            return '—';
        }
    }
}

if (!function_exists('fmt_date')) {
    function fmt_date(?string $utc): string
    {
        return fmt_dt($utc, 'd M Y');
    }
}

if (!function_exists('now')) {
    function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}

if (!function_exists('to_utc')) {
    /** Convert a datetime-local input (display timezone) into a UTC string. */
    function to_utc(?string $local): ?string
    {
        if (!$local) {
            return null;
        }
        $local = str_replace('T', ' ', trim($local));
        try {
            $dt = new DateTime($local, app_tz());
            $dt->setTimezone(new DateTimeZone('UTC'));
            return $dt->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }
}

if (!function_exists('to_local_input')) {
    function to_local_input(?string $utc): string
    {
        if (!$utc) {
            return '';
        }
        return fmt_dt($utc, 'Y-m-d\TH:i');
    }
}

if (!function_exists('time_ago')) {
    function time_ago(?string $utc): string
    {
        if (!$utc) {
            return '—';
        }
        $diff = time() - strtotime($utc . ' UTC');
        if ($diff < 60) {
            return 'just now';
        }
        $units = [31536000 => 'year', 2592000 => 'month', 604800 => 'week', 86400 => 'day', 3600 => 'hour', 60 => 'minute'];
        foreach ($units as $seconds => $label) {
            if ($diff >= $seconds) {
                $count = (int) floor($diff / $seconds);
                return $count . ' ' . $label . ($count > 1 ? 's' : '') . ' ago';
            }
        }
        return 'just now';
    }
}

if (!function_exists('countdown_seconds')) {
    function countdown_seconds(?string $utc): int
    {
        if (!$utc) {
            return 0;
        }
        return max(0, strtotime($utc . ' UTC') - time());
    }
}

if (!function_exists('slugify')) {
    function slugify(string $text, int $max = 180): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text) ?? '';
        $text = trim($text, '-');
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        if ($converted !== false) {
            $text = $converted;
        }
        $text = strtolower(preg_replace('~[^-\w]+~', '', $text) ?? '');
        $text = preg_replace('~-+~', '-', $text) ?? '';
        $text = trim($text, '-');
        if ($text === '') {
            $text = 'item';
        }
        return substr($text, 0, $max);
    }
}

if (!function_exists('str_random')) {
    function str_random(int $length = 32): string
    {
        return substr(bin2hex(random_bytes((int) ceil($length / 2))), 0, $length);
    }
}

if (!function_exists('array_get')) {
    function array_get(array $array, string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }
        return $array;
    }
}

if (!function_exists('dec')) {
    /** Normalise a user-supplied numeric string into a DECIMAL-safe string. */
    function dec(mixed $value, int $scale = 2): string
    {
        $value = is_string($value) ? str_replace([',', ' ', '₹'], '', $value) : $value;
        if ($value === '' || $value === null || !is_numeric($value)) {
            $value = 0;
        }
        return number_format((float) $value, $scale, '.', '');
    }
}

if (!function_exists('percent_of')) {
    function percent_of(float|string $amount, float|string $percent): string
    {
        return dec(((float) $amount * (float) $percent) / 100, 2);
    }
}

if (!function_exists('status_badge')) {
    function status_badge(?string $status): string
    {
        $map = [
            'active' => 'success', 'approved' => 'success', 'verified' => 'success', 'paid' => 'success',
            'completed' => 'success', 'delivered' => 'success', 'accepted' => 'success', 'awarded' => 'success',
            'won' => 'success', 'open' => 'info', 'live' => 'info', 'published' => 'success',
            'pending' => 'warning', 'processing' => 'warning', 'draft' => 'secondary', 'scheduled' => 'info',
            'closing_soon' => 'warning', 'partial' => 'warning', 'countered' => 'warning', 'under_review' => 'warning',
            'rejected' => 'danger', 'cancelled' => 'danger', 'failed' => 'danger', 'blocked' => 'danger',
            'suspended' => 'danger', 'disputed' => 'danger', 'expired' => 'secondary', 'lost' => 'secondary',
            'closed' => 'secondary', 'sold' => 'dark', 'resolved' => 'success', 'escalated' => 'danger',
        ];
        $key = strtolower((string) $status);
        $class = $map[$key] ?? 'secondary';
        return '<span class="badge text-bg-' . $class . '">' . e(label($status)) . '</span>';
    }
}

if (!function_exists('label')) {
    function label(?string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', (string) $value));
    }
}

if (!function_exists('flash')) {
    function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): App\Core\Response
    {
        return App\Core\Response::redirect($path);
    }
}

if (!function_exists('back')) {
    function back(): App\Core\Response
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? base_url('/');
        return App\Core\Response::redirect($ref);
    }
}

if (!function_exists('abort')) {
    function abort(int $code, string $message = ''): never
    {
        throw new App\Core\HttpException($code, $message);
    }
}

if (!function_exists('logger')) {
    function logger(): App\Core\Logger
    {
        return App\Core\Logger::instance();
    }
}

if (!function_exists('db')) {
    function db(): App\Core\Database
    {
        return App\Core\Database::instance();
    }
}

if (!function_exists('is_installed')) {
    function is_installed(): bool
    {
        return is_file(INSTALL_LOCK) && is_file(CONFIG_FILE);
    }
}

if (!function_exists('mask_secret')) {
    function mask_secret(?string $secret): string
    {
        if (!$secret) {
            return '';
        }
        $len = strlen($secret);
        return str_repeat('•', max(4, min(12, $len - 4))) . substr($secret, -4);
    }
}

if (!function_exists('human_bytes')) {
    function human_bytes(int|float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round((float) $bytes, 2) . ' ' . $units[$i];
    }
}

if (!function_exists('active_nav')) {
    function active_nav(string $prefix): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if ($prefix === '/') {
            return $path === '/' ? 'active' : '';
        }
        return str_starts_with($path, $prefix) ? 'active' : '';
    }
}
