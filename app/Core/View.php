<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal PHP template engine: templates are plain PHP files, values are escaped
 * explicitly with e(). Layout composition uses section()/endSection()/yieldSection().
 */
final class View
{
    private static array $sections = [];
    private static array $stack = [];
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function render(string $template, array $data = [], ?string $layout = null): string
    {
        $file = self::path($template);
        if (!is_file($file)) {
            throw new \RuntimeException('View not found: ' . $template);
        }

        $data = array_merge(self::$shared, $data);
        $content = self::capture($file, $data);

        $layout ??= $data['layout'] ?? null;
        if ($layout) {
            self::$sections['content'] = $content;
            $layoutFile = self::path($layout);
            if (!is_file($layoutFile)) {
                throw new \RuntimeException('Layout not found: ' . $layout);
            }
            $content = self::capture($layoutFile, $data);
            self::$sections = [];
        }

        return $content;
    }

    public static function partial(string $template, array $data = []): string
    {
        $file = self::path($template);
        if (!is_file($file)) {
            return '';
        }
        return self::capture($file, array_merge(self::$shared, $data));
    }

    private static function capture(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    private static function path(string $template): string
    {
        $template = str_replace(['..', "\0"], '', $template);
        return VIEW_PATH . '/' . ltrim($template, '/') . '.php';
    }

    public static function section(string $name): void
    {
        self::$stack[] = $name;
        ob_start();
    }

    public static function endSection(): void
    {
        $name = array_pop(self::$stack);
        if ($name !== null) {
            self::$sections[$name] = (string) ob_get_clean();
        }
    }

    public static function yieldSection(string $name, string $default = ''): string
    {
        return self::$sections[$name] ?? $default;
    }

    public static function hasSection(string $name): bool
    {
        return isset(self::$sections[$name]);
    }
}
