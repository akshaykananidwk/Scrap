<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Migrator;

/**
 * Post-update health check. If any REQUIRED check fails after an update, the
 * updater rolls the whole deployment back rather than leaving a broken site.
 */
final class HealthService
{
    /**
     * @return array{passed: bool, checks: array<int, array{name:string, passed:bool, required:bool, detail:string}>}
     */
    public static function run(): array
    {
        $checks = [];

        // --- PHP runtime ------------------------------------------------------
        $phpOk = version_compare(PHP_VERSION, InstallService::MIN_PHP, '>=');
        $checks[] = self::check('PHP version', $phpOk, true, PHP_VERSION . ' (needs ' . InstallService::MIN_PHP . '+)');

        foreach (InstallService::REQUIRED_EXTENSIONS as $extension) {
            $checks[] = self::check(
                'Extension: ' . $extension,
                extension_loaded($extension),
                true,
                extension_loaded($extension) ? 'loaded' : 'MISSING'
            );
        }

        // --- Configuration ----------------------------------------------------
        $checks[] = self::check('Configuration file', is_file(CONFIG_FILE), true, is_file(CONFIG_FILE) ? 'config.php present' : 'config.php missing');
        $appKey = (string) Config::get('app.key', '');
        $checks[] = self::check('Application key', strlen($appKey) >= 32, true, strlen($appKey) >= 32 ? 'set' : 'missing or too short');

        // --- Database ---------------------------------------------------------
        try {
            $db = Database::instance();
            $db->scalar('SELECT 1');
            $checks[] = self::check('Database connection', true, true, 'connected to ' . Config::get('database.name', ''));

            $tables = $db->tables();
            $expected = [
                'users', 'roles', 'permissions', 'businesses', 'categories', 'materials', 'units',
                'listings', 'auctions', 'bids', 'offers', 'orders', 'payments', 'commissions',
                'invoices', 'reviews', 'disputes', 'notifications', 'settings', 'migrations',
                'system_updates', 'system_backups', 'cron_jobs',
            ];
            $missing = array_diff($expected, $tables);
            $checks[] = self::check(
                'Database schema',
                $missing === [],
                true,
                $missing === [] ? count($tables) . ' tables present' : 'missing: ' . implode(', ', $missing)
            );

            $migrator = new Migrator($db);
            $pending = $migrator->pending();
            $checks[] = self::check(
                'Migrations',
                $pending === [],
                true,
                $pending === []
                    ? 'up to date at ' . $migrator->currentVersion()
                    : count($pending) . ' pending: ' . implode(', ', array_keys($pending))
            );

            $adminCount = (int) $db->scalar(
                "SELECT COUNT(*) FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id WHERE r.slug = 'super_admin'",
                [],
                0
            );
            $checks[] = self::check('Administrator account', $adminCount > 0, true, $adminCount . ' super admin(s)');

            $settingCount = (int) $db->scalar('SELECT COUNT(*) FROM settings', [], 0);
            $checks[] = self::check('Platform settings', $settingCount > 20, true, $settingCount . ' settings');

            $categoryCount = (int) $db->scalar('SELECT COUNT(*) FROM categories', [], 0);
            $checks[] = self::check('Catalog', $categoryCount > 0, false, $categoryCount . ' categories');
        } catch (\Throwable $e) {
            $checks[] = self::check('Database connection', false, true, $e->getMessage());
        }

        // --- Filesystem -------------------------------------------------------
        foreach (InstallService::WRITABLE_PATHS as $path) {
            $absolute = ROOT_PATH . '/' . $path;
            $writable = is_dir($absolute) && is_writable($absolute);
            $checks[] = self::check('Writable: /' . $path, $writable, true, $writable ? 'writable' : 'NOT writable');
        }

        $uploadGuard = is_file(UPLOAD_PATH . '/.htaccess');
        $checks[] = self::check(
            'Upload directory hardened',
            $uploadGuard,
            false,
            $uploadGuard ? 'uploads/.htaccess present' : 'uploads/.htaccess missing — PHP execution may be possible'
        );

        // --- Application boot -------------------------------------------------
        $criticalFiles = [
            'index.php', 'app/Core/Kernel.php', 'app/Core/Database.php', 'routes/web.php',
            'resources/views/layouts/app.php', 'resources/views/home/index.php',
        ];
        $missingFiles = array_values(array_filter(
            $criticalFiles,
            static fn (string $file): bool => !is_file(ROOT_PATH . '/' . $file)
        ));
        $checks[] = self::check(
            'Critical files',
            $missingFiles === [],
            true,
            $missingFiles === [] ? count($criticalFiles) . ' files present' : 'missing: ' . implode(', ', $missingFiles)
        );

        // Routes must actually compile.
        try {
            $routeCount = self::countRoutes();
            $checks[] = self::check('Routes', $routeCount > 20, true, $routeCount . ' routes registered');
        } catch (\Throwable $e) {
            $checks[] = self::check('Routes', false, true, 'route file error: ' . $e->getMessage());
        }

        // Templates must parse.
        $templateErrors = self::lintTemplates();
        $checks[] = self::check(
            'View templates',
            $templateErrors === [],
            true,
            $templateErrors === [] ? 'all templates parse' : implode('; ', array_slice($templateErrors, 0, 3))
        );

        // --- Operations -------------------------------------------------------
        $cron = CronService::isHealthy();
        $checks[] = self::check('Scheduler', $cron['ok'], false, $cron['message']);

        $queueBacklog = 0;
        try {
            $queueBacklog = (int) Database::instance()->scalar(
                "SELECT COUNT(*) FROM notification_queue WHERE status = 'queued'",
                [],
                0
            );
        } catch (\Throwable) {
            // Table unavailable — already reported by the schema check.
        }
        $checks[] = self::check('Notification queue', $queueBacklog < 500, false, $queueBacklog . ' queued message(s)');

        $passed = true;
        foreach ($checks as $check) {
            if ($check['required'] && !$check['passed']) {
                $passed = false;
                break;
            }
        }

        return ['passed' => $passed, 'checks' => $checks];
    }

    private static function countRoutes(): int
    {
        // Count route declarations without mutating the live router.
        $count = 0;
        foreach (['web', 'api'] as $file) {
            $path = ROOT_PATH . '/routes/' . $file . '.php';
            if (!is_file($path)) {
                continue;
            }
            $contents = (string) file_get_contents($path);
            $count += preg_match_all('/\$router->(get|post|put|patch|delete|any)\(/', $contents);
        }
        return $count;
    }

    /** php -l equivalent using the tokenizer, so no shell is required. */
    private static function lintTemplates(): array
    {
        $errors = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(VIEW_PATH, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            try {
                // Throws ParseError on malformed PHP without executing it.
                \PhpToken::tokenize($source, TOKEN_PARSE);
            } catch (\ParseError $e) {
                $errors[] = basename($file->getPathname()) . ': ' . $e->getMessage();
            }
        }
        return $errors;
    }

    private static function check(string $name, bool $passed, bool $required, string $detail): array
    {
        return ['name' => $name, 'passed' => $passed, 'required' => $required, 'detail' => $detail];
    }

    public static function summary(): array
    {
        $report = self::run();
        $failed = array_values(array_filter($report['checks'], static fn (array $c): bool => !$c['passed']));
        return [
            'passed' => $report['passed'],
            'total' => count($report['checks']),
            'failed' => count($failed),
            'failures' => $failed,
        ];
    }
}
