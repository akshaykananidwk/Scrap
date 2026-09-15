<?php

declare(strict_types=1);

namespace App\Core;

final class Logger
{
    private static ?Logger $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $dir = STORAGE_PATH . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = sprintf(
            "[%s] %s: %s%s\n",
            gmdate('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ''
        );
        @file_put_contents($dir . '/' . gmdate('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    public function debug(string $m, array $c = []): void
    {
        if (Config::get('app.debug')) {
            $this->log('debug', $m, $c);
        }
    }

    public function info(string $m, array $c = []): void
    {
        $this->log('info', $m, $c);
    }

    public function warning(string $m, array $c = []): void
    {
        $this->log('warning', $m, $c);
    }

    public function error(string $m, array $c = []): void
    {
        $this->log('error', $m, $c);
    }

    public function exception(\Throwable $e): void
    {
        $this->log('error', get_class($e) . ': ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 12),
        ]);
    }

    /** Files available to the admin error viewer. */
    public function files(): array
    {
        $dir = STORAGE_PATH . '/logs';
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob($dir . '/*.log') ?: [];
        rsort($files);
        return array_map(static fn (string $f): array => [
            'name' => basename($f),
            'size' => filesize($f) ?: 0,
            'modified' => gmdate('Y-m-d H:i:s', filemtime($f) ?: time()),
        ], $files);
    }

    public function read(string $name, int $maxBytes = 262144): string
    {
        if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}\.log$/', $name)) {
            return '';
        }
        $file = STORAGE_PATH . '/logs/' . $name;
        if (!is_file($file)) {
            return '';
        }
        $size = filesize($file) ?: 0;
        $handle = fopen($file, 'rb');
        if (!$handle) {
            return '';
        }
        if ($size > $maxBytes) {
            fseek($handle, -$maxBytes, SEEK_END);
        }
        $contents = stream_get_contents($handle) ?: '';
        fclose($handle);
        return $contents;
    }

    public function purge(string $name): bool
    {
        if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}\.log$/', $name)) {
            return false;
        }
        $file = STORAGE_PATH . '/logs/' . $name;
        return is_file($file) && @unlink($file);
    }
}
