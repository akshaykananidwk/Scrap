<?php
/**
 * ScrapX — B2B Scrap Trading Marketplace
 * Single front controller. Every request that is not a real file lands here.
 */

declare(strict_types=1);

define('SCRAPX_START', microtime(true));
define('ROOT_PATH', __DIR__);
define('APP_PATH', ROOT_PATH . '/app');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('VIEW_PATH', ROOT_PATH . '/resources/views');
define('DATABASE_PATH', ROOT_PATH . '/database');
define('CONFIG_FILE', ROOT_PATH . '/config.php');
define('INSTALL_LOCK', STORAGE_PATH . '/installed.lock');

// Apache hands real files to the filesystem before reaching this file (see
// .htaccess). PHP's built-in server has no such rule, so serve existing assets
// here and let `php -S localhost:8000 index.php` work for local development.
if (PHP_SAPI === 'cli-server') {
    $requestPath = ltrim((string) (parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''), '/');
    // Only the two directories that are meant to be public, so a stray log or
    // backup under storage/ is never handed out by the development server.
    $servable = str_starts_with($requestPath, 'public/') || str_starts_with($requestPath, 'uploads/');
    $requested = $servable ? realpath(ROOT_PATH . '/' . $requestPath) : false;

    if ($requested !== false
        && is_file($requested)
        && str_starts_with($requested, ROOT_PATH . DIRECTORY_SEPARATOR)
        && !str_ends_with(strtolower($requested), '.php')
    ) {
        return false;
    }
}

require APP_PATH . '/Core/bootstrap.php';

\App\Core\Kernel::run();
