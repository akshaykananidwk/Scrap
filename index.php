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

require APP_PATH . '/Core/bootstrap.php';

\App\Core\Kernel::run();
