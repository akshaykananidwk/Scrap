<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Translation loader. Strings live in resources/lang/{locale}.php as a flat map.
 * Missing keys fall back to English and then to the key itself, so adding a new
 * language never breaks a page.
 */
final class Lang
{
    private static array $lines = [];
    private static string $locale = 'en';
    private static array $fallback = [];

    public const SUPPORTED = ['en' => 'English', 'hi' => 'हिन्दी', 'gu' => 'ગુજરાતી'];

    public static function boot(): void
    {
        $locale = (string) (Session::get('locale') ?? '');
        if (!isset(self::SUPPORTED[$locale])) {
            $locale = (string) \App\Services\SettingsService::get('default_language', 'en');
        }
        self::setLocale(isset(self::SUPPORTED[$locale]) ? $locale : 'en');
    }

    public static function setLocale(string $locale): void
    {
        if (!isset(self::SUPPORTED[$locale])) {
            $locale = 'en';
        }
        self::$locale = $locale;
        self::$lines = self::load($locale);
        self::$fallback = $locale === 'en' ? self::$lines : self::load('en');
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    private static function load(string $locale): array
    {
        $file = ROOT_PATH . '/resources/lang/' . preg_replace('/[^a-z_]/', '', $locale) . '.php';
        if (!is_file($file)) {
            return [];
        }
        $lines = require $file;
        return is_array($lines) ? $lines : [];
    }

    public static function get(string $key, array $replace = []): string
    {
        $line = self::$lines[$key] ?? self::$fallback[$key] ?? $key;
        foreach ($replace as $search => $value) {
            $line = str_replace(':' . $search, (string) $value, $line);
        }
        return $line;
    }
}
