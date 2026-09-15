<?php

declare(strict_types=1);

/**
 * ScrapX command line helper.
 *
 * The application does NOT require this file to install or update — the web
 * installer at /install and the admin updater do everything. It exists for
 * operators who do have shell access, and for running the scheduler from cron:
 *
 *   php cli.php migrate            Apply pending migrations
 *   php cli.php migrate:status     Show applied / pending migrations
 *   php cli.php seed [name]        Run a seeder (roles|settings|locations|catalog|content|demo|all)
 *   php cli.php install            Non-interactive install using environment variables
 *   php cli.php cron [job]         Run the scheduler (all jobs, or one job)
 *   php cli.php health             Run the post-update health check
 *   php cli.php backup [type]      Create a backup (full|database|files)
 *   php cli.php admin:password     Reset an administrator password
 *   php cli.php routes             List registered routes
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("cli.php can only be run from the command line.\n");
}

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

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Migrator;
use App\Services\BackupService;
use App\Services\CronService;
use App\Services\HealthService;
use App\Services\InstallService;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;

Config::load();

$command = $argv[1] ?? 'help';
$argument = $argv[2] ?? null;

function out(string $message, string $style = ''): void
{
    $colors = ['ok' => "\033[32m", 'err' => "\033[31m", 'warn' => "\033[33m", 'info' => "\033[36m", 'bold' => "\033[1m"];
    $prefix = $colors[$style] ?? '';
    echo $prefix . $message . ($prefix ? "\033[0m" : '') . "\n";
}

try {
    switch ($command) {
        case 'migrate':
            $migrator = new Migrator(Database::instance());
            $result = $migrator->run(static function (string $version, string $description, bool $ok, ?string $error): void {
                out(($ok ? '  ✓ ' : '  ✗ ') . $version . '  ' . $description . ($error ? ' — ' . $error : ''), $ok ? 'ok' : 'err');
            });
            if ($result['applied'] === [] && $result['errors'] === []) {
                out('Nothing to migrate — the schema is up to date.', 'info');
            }
            exit($result['errors'] === [] ? 0 : 1);

        case 'migrate:status':
            $migrator = new Migrator(Database::instance());
            $applied = $migrator->applied();
            $all = $migrator->discover();
            out(sprintf('%-8s %-10s %s', 'VERSION', 'STATUS', 'DESCRIPTION'), 'bold');
            foreach ($all as $version => $migration) {
                $isApplied = isset($applied[$version]);
                out(sprintf('%-8s %-10s %s', $version, $isApplied ? 'applied' : 'pending', $migration->description()), $isApplied ? 'ok' : 'warn');
            }
            exit(0);

        case 'seed':
            $db = Database::instance();
            $name = $argument ?? 'all';
            $map = [
                'roles' => static fn () => RoleSeeder::run($db),
                'settings' => static fn () => SettingSeeder::run($db),
                'locations' => static fn () => LocationSeeder::run($db),
                'catalog' => static fn () => CatalogSeeder::run($db),
                'content' => static fn () => ContentSeeder::run($db),
                'demo' => static function () use ($db): void {
                    $adminId = (int) $db->scalar('SELECT id FROM users ORDER BY id LIMIT 1', [], 0);
                    out(DemoSeeder::run($db, $adminId), 'info');
                },
            ];
            $toRun = $name === 'all' ? array_slice($map, 0, 5, true) : array_intersect_key($map, [$name => true]);
            if ($toRun === []) {
                out('Unknown seeder: ' . $name, 'err');
                exit(1);
            }
            foreach ($toRun as $key => $seeder) {
                $seeder();
                out('  ✓ seeded: ' . $key, 'ok');
            }
            exit(0);

        case 'install':
            $database = [
                'host' => getenv('DB_HOST') ?: '127.0.0.1',
                'port' => getenv('DB_PORT') ?: '3306',
                'name' => getenv('DB_NAME') ?: '',
                'user' => getenv('DB_USER') ?: '',
                'pass' => getenv('DB_PASS') ?: '',
            ];
            $app = [
                'site_name' => getenv('SITE_NAME') ?: 'ScrapX',
                'site_url' => getenv('SITE_URL') ?: 'http://localhost:8000',
                'admin_name' => getenv('ADMIN_NAME') ?: 'Administrator',
                'admin_email' => getenv('ADMIN_EMAIL') ?: 'admin@example.com',
                'admin_mobile' => getenv('ADMIN_MOBILE') ?: '9999999999',
                'admin_password' => getenv('ADMIN_PASSWORD') ?: 'Admin@12345',
                'demo_data' => (getenv('DEMO_DATA') ?: '0') === '1',
            ];
            if ($database['name'] === '') {
                out('Set DB_NAME, DB_USER, DB_PASS (and optionally DB_HOST/DB_PORT) first.', 'err');
                exit(1);
            }
            $result = InstallService::install($database, $app);
            foreach ($result['steps'] as $step) {
                out(($step['ok'] ? '  ✓ ' : '  ✗ ') . str_pad($step['label'], 32) . $step['detail'], $step['ok'] ? 'ok' : 'err');
            }
            if ($result['ok']) {
                out('Installation complete.', 'ok');
                exit(0);
            }
            out('Installation failed: ' . ($result['error'] ?? 'unknown error'), 'err');
            exit(1);

        case 'cron':
            $results = $argument
                ? [CronService::runJob($argument, 'manual')]
                : CronService::runDue('cron');
            foreach ($results as $result) {
                out(sprintf(
                    '  %s %-22s %s (%dms)',
                    $result['status'] === 'success' ? '✓' : '✗',
                    $result['job'],
                    $result['message'],
                    $result['duration_ms']
                ), $result['status'] === 'success' ? 'ok' : 'err');
            }
            if ($results === []) {
                out('No jobs were due.', 'info');
            }
            exit(0);

        case 'health':
            $report = HealthService::run();
            foreach ($report['checks'] as $check) {
                out(sprintf('  %s %-28s %s', $check['passed'] ? '✓' : '✗', $check['name'], $check['detail']), $check['passed'] ? 'ok' : 'err');
            }
            out($report['passed'] ? 'Health check PASSED' : 'Health check FAILED', $report['passed'] ? 'ok' : 'err');
            exit($report['passed'] ? 0 : 1);

        case 'backup':
            $result = BackupService::create($argument ?: 'full', 'manual', null);
            out($result['ok']
                ? 'Backup created: ' . $result['filename'] . ' (' . human_bytes($result['size'] ?? 0) . ')'
                : 'Backup failed: ' . ($result['error'] ?? ''), $result['ok'] ? 'ok' : 'err');
            exit($result['ok'] ? 0 : 1);

        case 'admin:password':
            $email = $argument;
            $password = $argv[3] ?? null;
            if (!$email || !$password) {
                out('Usage: php cli.php admin:password <email> <new-password>', 'err');
                exit(1);
            }
            $affected = Database::instance()->statement(
                'UPDATE users SET password_hash = :p, updated_at = :u WHERE email = :e',
                ['p' => Auth::hash($password), 'u' => now(), 'e' => strtolower($email)]
            );
            out($affected > 0 ? 'Password updated.' : 'No user found with that email.', $affected > 0 ? 'ok' : 'err');
            exit($affected > 0 ? 0 : 1);

        case 'routes':
            require ROOT_PATH . '/routes/web.php';
            require ROOT_PATH . '/routes/api.php';
            $routes = App\Core\Kernel::router()->routes();
            out(sprintf('%-7s %-46s %s', 'METHOD', 'URI', 'ACTION'), 'bold');
            foreach ($routes as $route) {
                $action = is_array($route['action'])
                    ? str_replace('App\\Controllers\\', '', $route['action'][0]) . '@' . $route['action'][1]
                    : 'Closure';
                out(sprintf('%-7s %-46s %s', $route['method'], $route['uri'], $action));
            }
            out(count($routes) . ' routes.', 'info');
            exit(0);

        default:
            echo file_get_contents(__FILE__, false, null, 0, 1400);
            exit(0);
    }
} catch (Throwable $e) {
    out('Error: ' . $e->getMessage(), 'err');
    out('  at ' . $e->getFile() . ':' . $e->getLine());
    exit(1);
}
