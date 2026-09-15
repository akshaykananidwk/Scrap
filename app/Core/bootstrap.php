<?php

declare(strict_types=1);

/**
 * Framework bootstrap: autoloader, helpers, error handling, timezone.
 * Deliberately Composer-free so the project runs on plain shared hosting.
 */

spl_autoload_register(static function (string $class): void {
    // App\Foo\Bar  -> app/Foo/Bar.php
    // Database\Seeders\X -> database/seeders/X.php
    if (str_starts_with($class, 'App\\')) {
        $file = APP_PATH . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    } elseif (str_starts_with($class, 'Database\\')) {
        $relative = str_replace('\\', '/', substr($class, 9));
        $parts = explode('/', $relative);
        $parts[0] = strtolower($parts[0]);
        $file = DATABASE_PATH . '/' . implode('/', $parts) . '.php';
    } else {
        return;
    }
    if (is_file($file)) {
        require $file;
    }
});

require APP_PATH . '/Helpers/functions.php';

mb_internal_encoding('UTF-8');
// Internally everything is UTC. Display conversion happens in fmt_* helpers.
date_default_timezone_set('UTC');

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
