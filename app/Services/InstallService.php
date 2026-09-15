<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Migrator;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use PDO;
use Throwable;

/**
 * Drives the /install wizard. The same code path is reused by the CLI installer
 * so both produce an identical, working deployment.
 */
final class InstallService
{
    public const MIN_PHP = '8.2.0';

    public const REQUIRED_EXTENSIONS = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'fileinfo', 'openssl', 'curl'];
    public const RECOMMENDED_EXTENSIONS = ['gd', 'zip', 'intl', 'exif'];

    public const WRITABLE_PATHS = [
        'storage',
        'storage/logs',
        'storage/cache',
        'storage/backups',
        'storage/tmp',
        'uploads',
    ];

    /** @return array<int, array{name:string, required:bool, passed:bool, current:string, expected:string}> */
    public static function requirements(): array
    {
        $checks = [];

        $checks[] = [
            'name' => 'PHP version',
            'required' => true,
            'passed' => version_compare(PHP_VERSION, self::MIN_PHP, '>='),
            'current' => PHP_VERSION,
            'expected' => self::MIN_PHP . '+',
        ];

        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            $checks[] = [
                'name' => 'Extension: ' . $extension,
                'required' => true,
                'passed' => extension_loaded($extension),
                'current' => extension_loaded($extension) ? 'Loaded' : 'Missing',
                'expected' => 'Loaded',
            ];
        }

        foreach (self::RECOMMENDED_EXTENSIONS as $extension) {
            $checks[] = [
                'name' => 'Extension: ' . $extension . ' (recommended)',
                'required' => false,
                'passed' => extension_loaded($extension),
                'current' => extension_loaded($extension) ? 'Loaded' : 'Missing',
                'expected' => 'Loaded',
            ];
        }

        foreach (self::WRITABLE_PATHS as $path) {
            $absolute = ROOT_PATH . '/' . $path;
            if (!is_dir($absolute)) {
                @mkdir($absolute, 0775, true);
            }
            $writable = is_dir($absolute) && is_writable($absolute);
            $checks[] = [
                'name' => 'Writable: /' . $path,
                'required' => true,
                'passed' => $writable,
                'current' => $writable ? 'Writable' : (is_dir($absolute) ? 'Not writable' : 'Missing'),
                'expected' => 'Writable',
            ];
        }

        $rootWritable = is_writable(ROOT_PATH);
        $checks[] = [
            'name' => 'Writable: project root (for config.php)',
            'required' => true,
            'passed' => $rootWritable || is_file(CONFIG_FILE),
            'current' => $rootWritable ? 'Writable' : 'Not writable',
            'expected' => 'Writable',
        ];

        $checks[] = [
            'name' => 'URL rewriting',
            'required' => false,
            'passed' => self::rewriteAvailable(),
            'current' => self::rewriteAvailable() ? 'Available' : 'Unknown (check .htaccess)',
            'expected' => 'mod_rewrite enabled',
        ];

        return $checks;
    }

    private static function rewriteAvailable(): bool
    {
        if (function_exists('apache_get_modules')) {
            return in_array('mod_rewrite', apache_get_modules(), true);
        }
        // Reaching this code through the front controller means rewriting worked,
        // unless the request came in as /index.php directly.
        return !str_contains($_SERVER['REQUEST_URI'] ?? '', 'index.php');
    }

    public static function requirementsPassed(): bool
    {
        foreach (self::requirements() as $check) {
            if ($check['required'] && !$check['passed']) {
                return false;
            }
        }
        return true;
    }

    /** @return array{ok: bool, message: string, server_version?: string} */
    public static function testDatabase(array $credentials): array
    {
        try {
            $pdo = Database::connectWith($credentials);
            $version = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

            if (!self::versionSupported($version)) {
                return [
                    'ok' => false,
                    'message' => 'MySQL 8.0+ or MariaDB 10.4+ is required. Detected: ' . $version,
                ];
            }

            // Prove we can actually create objects, not just connect.
            $pdo->exec('CREATE TABLE IF NOT EXISTS `scrapx_install_probe` (`id` INT) ENGINE=InnoDB');
            $pdo->exec('DROP TABLE IF EXISTS `scrapx_install_probe`');

            return ['ok' => true, 'message' => 'Connection successful.', 'server_version' => $version];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => self::friendlyDbError($e->getMessage())];
        }
    }

    private static function versionSupported(string $version): bool
    {
        if (stripos($version, 'mariadb') !== false) {
            preg_match('/(\d+\.\d+\.\d+)/', $version, $m);
            return version_compare($m[1] ?? '0', '10.4.0', '>=');
        }
        return version_compare($version, '5.7.0', '>=');
    }

    private static function friendlyDbError(string $message): string
    {
        return match (true) {
            str_contains($message, 'Access denied') => 'Access denied — check the database username and password.',
            str_contains($message, 'Unknown database') => 'That database does not exist. Create it in your hosting panel first.',
            str_contains($message, 'Connection refused'), str_contains($message, 'No such file') =>
                'Could not reach the database server — check the host and port.',
            str_contains($message, 'CREATE command denied') =>
                'The database user cannot create tables. Grant it full privileges on this database.',
            default => 'Connection failed: ' . $message,
        };
    }

    /**
     * Run the whole installation.
     *
     * @param array{host:string,port:string,name:string,user:string,pass:string} $database
     * @param array{site_name:string,site_url:string,admin_name:string,admin_email:string,admin_mobile:string,admin_password:string,demo_data:bool} $app
     * @return array{ok: bool, steps: array<int, array{label:string, ok:bool, detail:string}>, error?: string}
     */
    public static function install(array $database, array $app): array
    {
        $steps = [];
        $fail = static function (string $label, string $detail) use (&$steps): array {
            $steps[] = ['label' => $label, 'ok' => false, 'detail' => $detail];
            return ['ok' => false, 'steps' => $steps, 'error' => $detail];
        };

        // 1. Verify the credentials once more before writing anything.
        $test = self::testDatabase($database);
        if (!$test['ok']) {
            return $fail('Database connection', $test['message']);
        }
        $steps[] = ['label' => 'Database connection', 'ok' => true, 'detail' => 'Connected to ' . $database['name']];

        // 2. Build and persist config.php.
        $config = [
            'app' => [
                'name' => $app['site_name'],
                'url' => rtrim($app['site_url'], '/'),
                'key' => str_random(64),
                'env' => 'production',
                'debug' => false,
                'timezone' => 'Asia/Kolkata',
                'version' => self::readVersion(),
                'installed_at' => now(),
            ],
            'database' => [
                'host' => $database['host'],
                'port' => (string) ($database['port'] ?: '3306'),
                'name' => $database['name'],
                'user' => $database['user'],
                'pass' => $database['pass'],
                'charset' => 'utf8mb4',
            ],
        ];

        if (!Config::write($config)) {
            return $fail('Write configuration', 'Could not write config.php — make the project root writable.');
        }
        $steps[] = ['label' => 'Write configuration', 'ok' => true, 'detail' => 'config.php created'];

        Database::setInstanceFor($config['database']);
        $db = Database::instance();

        // 3. Schema.
        try {
            $migrator = new Migrator($db);
            $result = $migrator->run();
            if ($result['errors'] !== []) {
                return $fail('Create database tables', implode(' | ', $result['errors']));
            }
            $steps[] = [
                'label' => 'Create database tables',
                'ok' => true,
                'detail' => count($result['applied']) . ' migrations applied (' . count($db->tables()) . ' tables)',
            ];
        } catch (Throwable $e) {
            return $fail('Create database tables', $e->getMessage());
        }

        // 4. Reference data.
        try {
            RoleSeeder::run($db);
            $steps[] = ['label' => 'Roles & permissions', 'ok' => true, 'detail' => count(RoleSeeder::ROLES) . ' roles seeded'];

            SettingSeeder::run($db);
            SettingsService::flush();
            $steps[] = ['label' => 'Platform settings', 'ok' => true, 'detail' => 'Default settings created'];

            LocationSeeder::run($db);
            $steps[] = ['label' => 'Locations', 'ok' => true, 'detail' => count(LocationSeeder::STATES) . ' states and major cities'];

            CatalogSeeder::run($db);
            $categories = $db->count('categories');
            $materials = $db->count('materials');
            $steps[] = ['label' => 'Scrap catalog', 'ok' => true, 'detail' => "{$categories} categories, {$materials} materials"];

            ContentSeeder::run($db);
            $steps[] = ['label' => 'Pages, templates & plans', 'ok' => true, 'detail' => 'CMS, FAQs, notification templates, cron jobs'];
        } catch (Throwable $e) {
            return $fail('Seed reference data', $e->getMessage());
        }

        // 5. Administrator.
        try {
            $adminId = self::createAdmin($db, $app);
            $steps[] = ['label' => 'Administrator account', 'ok' => true, 'detail' => $app['admin_email']];
        } catch (Throwable $e) {
            return $fail('Administrator account', $e->getMessage());
        }

        // 6. Operational settings that depend on the install.
        try {
            SettingsService::set('site_name', $app['site_name'], 'string', 'general');
            SettingsService::set('contact_email', $app['admin_email'], 'string', 'general');
            SettingsService::set('contact_phone', $app['admin_mobile'], 'string', 'general');
            SettingsService::set('mail_from_address', $app['admin_email'], 'string', 'mail');
            SettingsService::set('mail_from_name', $app['site_name'], 'string', 'mail');
            SettingsService::set('cron_secret', str_random(40), 'string', 'cron');
            $steps[] = ['label' => 'Site configuration', 'ok' => true, 'detail' => 'Site name, contact and scheduler key set'];
        } catch (Throwable $e) {
            return $fail('Site configuration', $e->getMessage());
        }

        // 7. Optional demo content.
        if (!empty($app['demo_data'])) {
            try {
                $summary = DemoSeeder::run($db, $adminId);
                $steps[] = ['label' => 'Demo data', 'ok' => true, 'detail' => $summary];
            } catch (Throwable $e) {
                // Demo data is optional — a failure must not abort a good install.
                $steps[] = ['label' => 'Demo data', 'ok' => false, 'detail' => 'Skipped: ' . $e->getMessage()];
            }
        }

        // 8. Harden the installation.
        try {
            self::writeProtectionFiles();
            file_put_contents(INSTALL_LOCK, json_encode([
                'installed_at' => now(),
                'version' => self::readVersion(),
                'php' => PHP_VERSION,
            ], JSON_PRETTY_PRINT));
            @chmod(INSTALL_LOCK, 0640);
            $steps[] = ['label' => 'Lock installer', 'ok' => true, 'detail' => 'storage/installed.lock created'];
        } catch (Throwable $e) {
            return $fail('Lock installer', $e->getMessage());
        }

        return ['ok' => true, 'steps' => $steps];
    }

    private static function createAdmin(Database $db, array $app): int
    {
        $mobile = preg_replace('/\D/', '', $app['admin_mobile']) ?? '';
        $mobile = substr($mobile, -10);

        $existing = $db->first('SELECT id FROM users WHERE email = :e OR mobile = :m', [
            'e' => strtolower($app['admin_email']),
            'm' => $mobile,
        ]);

        $data = [
            'uuid' => self::uuid(),
            'full_name' => $app['admin_name'],
            'email' => strtolower($app['admin_email']),
            'mobile' => $mobile,
            'password_hash' => Auth::hash($app['admin_password']),
            'account_type' => 'both',
            'status' => 'active',
            'kyc_status' => 'verified',
            'email_verified_at' => now(),
            'mobile_verified_at' => now(),
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($existing !== null) {
            $userId = (int) $existing['id'];
            unset($data['uuid'], $data['created_at']);
            $db->update('users', $data, ['id' => $userId]);
        } else {
            $userId = $db->insert('users', $data);
        }

        $roleId = (int) $db->scalar('SELECT id FROM roles WHERE slug = :s', ['s' => 'super_admin'], 0);
        if ($roleId > 0) {
            $db->statement(
                'INSERT IGNORE INTO user_roles (user_id, role_id, assigned_at) VALUES (:u, :r, :a)',
                ['u' => $userId, 'r' => $roleId, 'a' => now()]
            );
        }

        // Give the administrator a business profile so admin-side testing works.
        if ($db->first('SELECT id FROM businesses WHERE user_id = :u', ['u' => $userId]) === null) {
            $db->insert('businesses', [
                'user_id' => $userId,
                'name' => $app['site_name'] . ' (Platform)',
                'slug' => slugify($app['site_name'] . '-platform'),
                'business_type' => 'other',
                'contact_person' => $app['admin_name'],
                'contact_mobile' => $mobile,
                'contact_email' => strtolower($app['admin_email']),
                'kyc_verified' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $userId;
    }

    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function readVersion(): string
    {
        $file = ROOT_PATH . '/version.json';
        if (is_file($file)) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data) && !empty($data['version'])) {
                return (string) $data['version'];
            }
        }
        return '1.0.0';
    }

    /** Defence in depth: deny PHP execution inside writable directories. */
    public static function writeProtectionFiles(): void
    {
        $deny = "# Generated by ScrapX — do not remove.\n"
            . "<IfModule mod_php.c>\n    php_flag engine off\n</IfModule>\n"
            . "<IfModule mod_php7.c>\n    php_flag engine off\n</IfModule>\n"
            . "<IfModule mod_php8.c>\n    php_flag engine off\n</IfModule>\n"
            . "<FilesMatch \"\\.(php|phar|phtml|php3|php4|php5|php7|php8|pl|py|cgi|asp|sh)$\">\n"
            . "    Require all denied\n"
            . "</FilesMatch>\n"
            . "Options -Indexes -ExecCGI\n"
            . "AddType text/plain .php .phtml .phar\n";

        $denyAll = "Require all denied\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n";

        $targets = [
            UPLOAD_PATH . '/.htaccess' => $deny,
            STORAGE_PATH . '/.htaccess' => $denyAll,
            CONFIG_PATH . '/.htaccess' => $denyAll,
            DATABASE_PATH . '/.htaccess' => $denyAll,
            APP_PATH . '/.htaccess' => $denyAll,
            ROOT_PATH . '/resources/.htaccess' => $denyAll,
        ];

        foreach ($targets as $path => $contents) {
            $dir = dirname($path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (is_dir($dir)) {
                @file_put_contents($path, $contents);
            }
        }

        // index.html stubs stop directory listing even without .htaccess support.
        foreach ([UPLOAD_PATH, STORAGE_PATH, STORAGE_PATH . '/logs', STORAGE_PATH . '/backups'] as $dir) {
            if (is_dir($dir) && !is_file($dir . '/index.html')) {
                @file_put_contents($dir . '/index.html', '');
            }
        }
    }
}
