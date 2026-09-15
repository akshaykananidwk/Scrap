<?php

declare(strict_types=1);

namespace App\Services\Updates;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Migrator;
use App\Services\AuditService;
use App\Services\BackupService;
use App\Services\HealthService;
use App\Services\InstallService;
use App\Services\SettingsService;
use ZipArchive;

/**
 * GitHub-based self-update.
 *
 * The administrator saves owner/repo/branch/token once. After that,
 * "Check for update" and "Update now" do everything — no FTP, no SSH, no
 * manual file upload.
 *
 * The pipeline is strictly ordered and every step is recorded in
 * `system_updates`. If any step fails, rollback() restores the pre-update files
 * and database from the backup taken in step 3, and the version is reverted.
 */
final class UpdateService
{
    /** Paths that an update must never overwrite or delete. */
    public const ALWAYS_PROTECTED = [
        'config.php',
        '.env',
        'uploads',
        'storage',
        'backup',
        'backups',
        'logs',
        'user_uploads',
        'public/uploads',
        'storage/uploads',
    ];

    /** Files that only exist in the repo and should not be deployed to a live site. */
    private const IGNORED_FROM_PACKAGE = [
        '.git', '.github', '.gitignore', '.gitattributes', 'tests', 'phpunit.xml',
        'README.md', 'LICENSE', 'CONTRIBUTING.md', '.editorconfig', 'docs',
    ];

    public static function protectedPaths(): array
    {
        $configured = SettingsService::json('update_protected_paths', []);
        $paths = array_merge(self::ALWAYS_PROTECTED, $configured);
        return array_values(array_unique(array_filter(array_map(
            static fn ($p): string => trim(str_replace('\\', '/', (string) $p), '/'),
            $paths
        ))));
    }

    public static function isProtected(string $relativePath, ?array $protected = null): bool
    {
        $protected ??= self::protectedPaths();
        $path = trim(str_replace('\\', '/', $relativePath), '/');

        foreach ($protected as $rule) {
            if ($rule === '') {
                continue;
            }
            if ($path === $rule || str_starts_with($path, $rule . '/')) {
                return true;
            }
            // Support simple globs like "*.local.php".
            if (str_contains($rule, '*') && fnmatch($rule, $path)) {
                return true;
            }
        }
        return false;
    }

    public static function currentVersion(): string
    {
        return InstallService::readVersion();
    }

    public static function currentCommit(): string
    {
        $manifest = self::localManifest();
        return (string) ($manifest['commit'] ?? '');
    }

    public static function localManifest(): array
    {
        $file = ROOT_PATH . '/version.json';
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    public static function isConfigured(): bool
    {
        return GitHubClient::fromSettings() !== null;
    }

    /**
     * CHECK FOR UPDATE.
     * @return array{ok:bool, update_available?:bool, ...}
     */
    public static function check(): array
    {
        $client = GitHubClient::fromSettings();
        if ($client === null) {
            return ['ok' => false, 'error' => 'Save your GitHub owner, repository and branch first.'];
        }

        $useReleases = SettingsService::bool('github_use_releases', false);
        $currentVersion = self::currentVersion();
        $currentCommit = self::currentCommit();

        if ($useReleases) {
            $release = $client->latestRelease();
            if (!$release['ok']) {
                return ['ok' => false, 'error' => $release['error'] ?? 'Could not read the latest release.'];
            }
            $latestVersion = ltrim($release['tag'], 'vV');
            $available = version_compare($latestVersion, $currentVersion, '>');

            SettingsService::set('update_last_check_at', now(), 'string', 'updates');
            SettingsService::set('update_latest_version', $latestVersion, 'string', 'updates');

            return [
                'ok' => true,
                'channel' => 'release',
                'update_available' => $available,
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion,
                'release_notes' => $release['body'],
                'commit_author' => $release['author'],
                'commit_date' => $release['published_at'],
                'repository' => $client->repoPath(),
                'branch' => $client->branch(),
            ];
        }

        $commit = $client->latestCommit();
        if (!$commit['ok']) {
            return ['ok' => false, 'error' => $commit['error'] ?? 'Could not read the latest commit.'];
        }

        $manifest = $client->remoteManifest();
        $latestVersion = (string) ($manifest['version'] ?? $currentVersion);

        // A new commit SHA means an update is available even when the version
        // string has not been bumped.
        $available = $currentCommit === ''
            ? true
            : ($currentCommit !== $commit['sha'] || version_compare($latestVersion, $currentVersion, '>'));

        $changedFiles = $commit['files'];
        $commitsBehind = 0;
        if ($currentCommit !== '' && $currentCommit !== $commit['sha']) {
            $comparison = $client->compare($currentCommit, $commit['sha']);
            if ($comparison['ok']) {
                $changedFiles = $comparison['files'];
                $commitsBehind = $comparison['total_commits'];
            }
        }

        SettingsService::set('update_last_check_at', now(), 'string', 'updates');
        SettingsService::set('update_latest_version', $latestVersion, 'string', 'updates');

        $compatibility = self::checkCompatibility($manifest ?? []);

        return [
            'ok' => true,
            'channel' => 'branch',
            'update_available' => $available,
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,
            'current_commit' => $currentCommit !== '' ? substr($currentCommit, 0, 7) : null,
            'commit_sha' => $commit['sha'],
            'commit_short' => substr($commit['sha'], 0, 7),
            'commit_message' => $commit['message'],
            'commit_author' => $commit['author'],
            'commit_date' => $commit['date'],
            'commit_url' => $commit['url'],
            'commits_behind' => $commitsBehind,
            'changed_files' => array_slice($changedFiles, 0, 200),
            'changed_file_count' => count($changedFiles),
            'release_notes' => (string) ($manifest['release_notes'] ?? ''),
            'repository' => $client->repoPath(),
            'branch' => $client->branch(),
            'compatible' => $compatibility['ok'],
            'compatibility_message' => $compatibility['message'],
            'database_version' => (string) ($manifest['database_version'] ?? ''),
            'minimum_php' => (string) ($manifest['minimum_php'] ?? ''),
        ];
    }

    /** The release manifest may demand a newer PHP or extensions. */
    private static function checkCompatibility(array $manifest): array
    {
        $problems = [];

        $minimumPhp = (string) ($manifest['minimum_php'] ?? '');
        if ($minimumPhp !== '' && version_compare(PHP_VERSION, $minimumPhp, '<')) {
            $problems[] = 'requires PHP ' . $minimumPhp . '+, this server runs ' . PHP_VERSION;
        }

        foreach ((array) ($manifest['required_extensions'] ?? []) as $extension) {
            if (!extension_loaded((string) $extension)) {
                $problems[] = 'requires the ' . $extension . ' extension';
            }
        }

        return [
            'ok' => $problems === [],
            'message' => $problems === [] ? 'Compatible with this server.' : 'Not compatible: ' . implode('; ', $problems),
        ];
    }

    /**
     * UPDATE NOW — the full pipeline.
     * @return array{ok: bool, update_id?: int, steps: array, error?: string, rolled_back?: bool}
     */
    public static function apply(?int $userId = null): array
    {
        $db = Database::instance();
        $steps = [];
        $updateId = null;
        $backupId = null;
        $tempDir = null;
        $maintenanceWasOn = SettingsService::bool('maintenance_mode', false);
        $startTime = microtime(true);

        $log = static function (string $step, bool $ok, string $detail) use (&$steps, &$updateId, $db): void {
            $steps[] = ['step' => $step, 'ok' => $ok, 'detail' => $detail, 'at' => now()];
            if ($updateId !== null) {
                $db->update('system_updates', [
                    'current_step' => substr($step, 0, 60),
                    'log' => substr(json_encode($steps, JSON_UNESCAPED_SLASHES) ?: '', 0, 60000),
                ], ['id' => $updateId]);
            }
        };

        @set_time_limit(900);
        @ignore_user_abort(true);

        try {
            // ---- 1. Verify credentials and repository ------------------------
            $client = GitHubClient::fromSettings();
            if ($client === null) {
                return ['ok' => false, 'steps' => $steps, 'error' => 'GitHub repository is not configured.'];
            }
            $connection = $client->testConnection();
            if (!$connection['ok']) {
                return ['ok' => false, 'steps' => $steps, 'error' => $connection['error']];
            }
            $log('Verify repository', true, $connection['message']);

            // ---- 2. Check what we are updating to ----------------------------
            $check = self::check();
            if (!$check['ok']) {
                return ['ok' => false, 'steps' => $steps, 'error' => $check['error']];
            }
            if (!($check['update_available'] ?? false)) {
                return ['ok' => false, 'steps' => $steps, 'error' => 'Already up to date — nothing to install.'];
            }
            if (isset($check['compatible']) && $check['compatible'] === false) {
                return ['ok' => false, 'steps' => $steps, 'error' => $check['compatibility_message']];
            }

            $updateId = $db->insert('system_updates', [
                'update_uid' => bin2hex(random_bytes(16)),
                'from_version' => $check['current_version'],
                'to_version' => $check['latest_version'],
                'commit_sha' => $check['commit_sha'] ?? null,
                'commit_message' => isset($check['commit_message']) ? substr((string) $check['commit_message'], 0, 500) : null,
                'commit_author' => $check['commit_author'] ?? null,
                'commit_date' => $check['commit_date'] ?? null,
                'channel' => $check['channel'] === 'release' ? 'release' : 'branch',
                'started_at' => now(),
                'triggered_by' => $userId,
                'status' => 'running',
                'created_at' => now(),
            ]);
            $log('Plan update', true, $check['current_version'] . ' → ' . $check['latest_version']);

            // ---- 3. Server preflight -----------------------------------------
            $preflight = self::preflight();
            if (!$preflight['ok']) {
                throw new UpdateException($preflight['error']);
            }
            $log('Server requirements', true, $preflight['message']);

            // ---- 4. Maintenance mode on --------------------------------------
            SettingsService::set('maintenance_mode', true, 'boolean', 'security');
            $log('Maintenance mode', true, 'Site is showing the maintenance page');

            // ---- 5. Backup ----------------------------------------------------
            if (SettingsService::bool('update_backup_before', true)) {
                $backup = BackupService::create('full', 'pre_update', $userId);
                if (!$backup['ok']) {
                    throw new UpdateException('Backup failed, update aborted: ' . ($backup['error'] ?? ''));
                }
                $backupId = $backup['backup_id'];
                $db->update('system_updates', ['backup_id' => $backupId], ['id' => $updateId]);
                $log('Backup', true, $backup['filename'] . ' (' . human_bytes($backup['size'] ?? 0) . ')');
            } else {
                $log('Backup', true, 'Skipped (disabled in settings)');
            }

            // ---- 6. Download --------------------------------------------------
            $tempDir = STORAGE_PATH . '/tmp/update_' . bin2hex(random_bytes(8));
            if (!@mkdir($tempDir, 0775, true) && !is_dir($tempDir)) {
                throw new UpdateException('Could not create a temporary directory for the download.');
            }
            $zipPath = $tempDir . '/package.zip';
            $ref = $check['channel'] === 'release' ? ('v' . ltrim((string) $check['latest_version'], 'v')) : null;
            $download = $client->downloadZipball($zipPath, $ref);
            if (!$download['ok'] && $ref !== null) {
                // Some repositories tag without the "v" prefix.
                $download = $client->downloadZipball($zipPath, ltrim((string) $check['latest_version'], 'v'));
            }
            if (!$download['ok']) {
                throw new UpdateException($download['error']);
            }
            $db->update('system_updates', ['download_bytes' => $download['size']], ['id' => $updateId]);
            $log('Download', true, human_bytes($download['size']) . ' downloaded from ' . $client->repoPath());

            // ---- 7. Verify + extract -------------------------------------------
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new UpdateException('The downloaded package is not a valid archive.');
            }
            if ($zip->numFiles < 5) {
                $zip->close();
                throw new UpdateException('The downloaded package looks empty.');
            }
            $extractDir = $tempDir . '/extracted';
            @mkdir($extractDir, 0775, true);
            if (!$zip->extractTo($extractDir)) {
                $zip->close();
                throw new UpdateException('Could not extract the update package. Check disk space and permissions.');
            }
            $fileCount = $zip->numFiles;
            $zip->close();
            $log('Verify & extract', true, $fileCount . ' files extracted');

            // GitHub zipballs nest everything inside owner-repo-sha/.
            $entries = array_values(array_diff(scandir($extractDir) ?: [], ['.', '..']));
            $sourceDir = (count($entries) === 1 && is_dir($extractDir . '/' . $entries[0]))
                ? $extractDir . '/' . $entries[0]
                : $extractDir;

            // Sanity-check the package really is this application.
            if (!is_file($sourceDir . '/index.php') || !is_dir($sourceDir . '/app')) {
                throw new UpdateException('The package does not look like a ScrapX release (index.php or app/ is missing).');
            }
            $log('Validate package', true, 'Package structure verified');

            // ---- 8. Compare and apply -------------------------------------------
            $result = self::applyFiles($sourceDir, $updateId);
            $db->update('system_updates', [
                'files_added' => $result['added'],
                'files_updated' => $result['updated'],
                'files_skipped' => $result['skipped'],
            ], ['id' => $updateId]);
            $log(
                'Apply files',
                true,
                sprintf('%d added, %d updated, %d protected/unchanged', $result['added'], $result['updated'], $result['skipped'])
            );

            // ---- 9. Migrations ---------------------------------------------------
            $migrator = new Migrator($db);
            $migration = $migrator->run();
            if ($migration['errors'] !== []) {
                $db->update('system_updates', ['migration_status' => 'failed'], ['id' => $updateId]);
                throw new UpdateException('Database migration failed: ' . implode(' | ', $migration['errors']));
            }
            $db->update('system_updates', [
                'migration_status' => 'success',
                'migrations_applied' => substr(implode(',', $migration['applied']), 0, 500),
            ], ['id' => $updateId]);
            $log(
                'Database migrations',
                true,
                $migration['applied'] === [] ? 'No new migrations' : count($migration['applied']) . ' applied: ' . implode(', ', $migration['applied'])
            );

            // ---- 10. Re-seed reference data (idempotent) ---------------------------
            try {
                \Database\Seeders\SettingSeeder::run($db);
                \Database\Seeders\RoleSeeder::run($db);
                \Database\Seeders\ContentSeeder::run($db);
                $log('Reference data', true, 'New settings, permissions and templates merged');
            } catch (\Throwable $e) {
                $log('Reference data', true, 'Skipped: ' . $e->getMessage());
            }

            // ---- 11. Clear caches --------------------------------------------------
            $cleared = self::clearCache();
            $db->update('system_updates', ['cache_status' => 'cleared'], ['id' => $updateId]);
            $log('Clear cache', true, $cleared . ' cached item(s) removed');

            // ---- 12. Health check --------------------------------------------------
            $health = HealthService::run();
            $failed = array_values(array_filter(
                $health['checks'],
                static fn (array $c): bool => $c['required'] && !$c['passed']
            ));
            $db->update('system_updates', [
                'health_status' => $health['passed'] ? 'passed' : 'failed',
                'health_report' => substr(json_encode($health['checks'], JSON_UNESCAPED_SLASHES) ?: '', 0, 60000),
            ], ['id' => $updateId]);

            if (!$health['passed']) {
                $names = array_map(static fn (array $c): string => $c['name'] . ' (' . $c['detail'] . ')', $failed);
                throw new UpdateException('Health check failed after update: ' . implode('; ', array_slice($names, 0, 4)));
            }
            $log('Health check', true, count($health['checks']) . ' checks passed');

            // ---- 13. Record the new version ----------------------------------------
            self::writeLocalManifest($check);
            $config = Config::all();
            $config['app']['version'] = $check['latest_version'];
            Config::write($config);
            $log('Version', true, 'Now running ' . $check['latest_version']);

            // ---- 14. Maintenance mode off ------------------------------------------
            if (!$maintenanceWasOn) {
                SettingsService::set('maintenance_mode', false, 'boolean', 'security');
            }
            $log('Maintenance mode', true, $maintenanceWasOn ? 'Left on (it was on before the update)' : 'Site is live again');

            // ---- 15. Finish ---------------------------------------------------------
            self::cleanupTemp($tempDir);
            $db->update('system_updates', [
                'status' => 'success',
                'finished_at' => now(),
                'duration_ms' => (int) round((microtime(true) - $startTime) * 1000),
                'current_step' => 'completed',
                'log' => substr(json_encode($steps, JSON_UNESCAPED_SLASHES) ?: '', 0, 60000),
            ], ['id' => $updateId]);

            AuditService::log('system_updated', 'system_update', $updateId, null, [
                'from' => $check['current_version'],
                'to' => $check['latest_version'],
                'commit' => $check['commit_sha'] ?? null,
            ]);

            return [
                'ok' => true,
                'update_id' => $updateId,
                'steps' => $steps,
                'from_version' => $check['current_version'],
                'to_version' => $check['latest_version'],
                'files' => $result,
                'migrations' => $migration['applied'],
                'duration_ms' => (int) round((microtime(true) - $startTime) * 1000),
            ];
        } catch (\Throwable $e) {
            Logger::instance()->error('Update failed: ' . $e->getMessage());
            $log('FAILED', false, $e->getMessage());

            // ---- Automatic rollback ------------------------------------------------
            $rollback = ['ok' => false, 'message' => 'No backup was available to roll back to.'];
            if ($backupId !== null) {
                $rollback = self::rollback($backupId, $updateId);
                $log('Rollback', $rollback['ok'], $rollback['message']);
            }

            if ($updateId !== null) {
                $db->update('system_updates', [
                    'status' => $rollback['ok'] ? 'rolled_back' : 'failed',
                    'finished_at' => now(),
                    'duration_ms' => (int) round((microtime(true) - $startTime) * 1000),
                    'error_message' => substr($e->getMessage(), 0, 2000),
                    'log' => substr(json_encode($steps, JSON_UNESCAPED_SLASHES) ?: '', 0, 60000),
                ], ['id' => $updateId]);
            }

            if ($tempDir !== null) {
                self::cleanupTemp($tempDir);
            }
            if (!$maintenanceWasOn) {
                SettingsService::set('maintenance_mode', false, 'boolean', 'security');
            }

            AuditService::log('system_update_failed', 'system_update', $updateId, null, [
                'error' => $e->getMessage(),
                'rolled_back' => $rollback['ok'],
            ]);

            return [
                'ok' => false,
                'update_id' => $updateId,
                'steps' => $steps,
                'error' => $e->getMessage(),
                'rolled_back' => $rollback['ok'],
                'rollback_message' => $rollback['message'],
            ];
        }
    }

    /** Copy files from the extracted package, honouring protected paths. */
    private static function applyFiles(string $sourceDir, int $updateId): array
    {
        $db = Database::instance();
        $protected = self::protectedPaths();
        $added = 0;
        $updated = 0;
        $skipped = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($sourceDir) + 1));
            if ($relative === '') {
                continue;
            }

            // Repo-only files never reach a live site.
            $topLevel = explode('/', $relative)[0];
            if (in_array($topLevel, self::IGNORED_FROM_PACKAGE, true)) {
                continue;
            }

            if ($item->isDir()) {
                $target = ROOT_PATH . '/' . $relative;
                if (!is_dir($target)) {
                    @mkdir($target, 0775, true);
                }
                continue;
            }

            // NEVER overwrite user data or credentials.
            if (self::isProtected($relative, $protected)) {
                $skipped++;
                self::recordFile($db, $updateId, $relative, 'skipped_protected', 0, null, 'Protected path');
                continue;
            }

            $target = ROOT_PATH . '/' . $relative;
            $sourceHash = @sha1_file($item->getPathname()) ?: '';

            if (is_file($target)) {
                if ((@sha1_file($target) ?: '') === $sourceHash) {
                    $skipped++;
                    // Identical files are not logged individually — only counted.
                    continue;
                }
                $action = 'updated';
            } else {
                $action = 'added';
            }

            $dir = dirname($target);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            if (@copy($item->getPathname(), $target)) {
                @chmod($target, 0644);
                if ($action === 'added') {
                    $added++;
                } else {
                    $updated++;
                }
                self::recordFile($db, $updateId, $relative, $action, $item->getSize(), $sourceHash, null);
            } else {
                self::recordFile($db, $updateId, $relative, 'failed', 0, null, 'Copy failed — check permissions');
                throw new UpdateException('Could not write ' . $relative . '. Check file permissions and disk space.');
            }
        }

        return ['added' => $added, 'updated' => $updated, 'skipped' => $skipped];
    }

    private static function recordFile(Database $db, int $updateId, string $path, string $action, int $size, ?string $checksum, ?string $note): void
    {
        try {
            $db->insert('system_update_files', [
                'update_id' => $updateId,
                'path' => substr($path, 0, 400),
                'action' => $action,
                'size_bytes' => $size,
                'checksum' => $checksum,
                'note' => $note,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // File-level logging must never break the update itself.
        }
    }

    /** Restore files and database from the pre-update backup. */
    public static function rollback(int $backupId, ?int $updateId = null): array
    {
        try {
            $result = BackupService::restore($backupId, true);
            if (!$result['ok']) {
                return ['ok' => false, 'message' => 'Rollback failed: ' . ($result['error'] ?? 'unknown error')];
            }
            SettingsService::flush();

            return [
                'ok' => true,
                'message' => 'Rollback completed. ' . ($result['message'] ?? '') . ' Previous version restored.',
            ];
        } catch (\Throwable $e) {
            Logger::instance()->error('Rollback failed: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Rollback failed: ' . $e->getMessage()];
        }
    }

    /** Manual rollback from the admin UI. */
    public static function rollbackUpdate(int $updateId): array
    {
        $update = Database::instance()->first('SELECT * FROM system_updates WHERE id = :id', ['id' => $updateId]);
        if ($update === null) {
            return ['ok' => false, 'message' => 'Update record not found.'];
        }
        if (empty($update['backup_id'])) {
            return ['ok' => false, 'message' => 'This update has no backup attached, so it cannot be rolled back automatically.'];
        }

        SettingsService::set('maintenance_mode', true, 'boolean', 'security');
        $result = self::rollback((int) $update['backup_id'], $updateId);
        if ($result['ok']) {
            Database::instance()->update('system_updates', [
                'status' => 'rolled_back',
                'error_message' => 'Rolled back manually by an administrator.',
            ], ['id' => $updateId]);
        }
        SettingsService::set('maintenance_mode', false, 'boolean', 'security');

        AuditService::log('system_update_rolled_back', 'system_update', $updateId, null, ['ok' => $result['ok']]);
        return $result;
    }

    private static function preflight(): array
    {
        if (!class_exists(ZipArchive::class)) {
            return ['ok' => false, 'error' => 'The PHP zip extension is required to install updates.'];
        }
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'The PHP cURL extension is required to reach GitHub.'];
        }
        if (!is_writable(ROOT_PATH)) {
            return ['ok' => false, 'error' => 'The application directory is not writable, so files cannot be replaced.'];
        }
        if (!is_writable(STORAGE_PATH)) {
            return ['ok' => false, 'error' => 'storage/ is not writable.'];
        }

        $free = @disk_free_space(ROOT_PATH);
        if ($free !== false && $free < 100 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'Less than 100 MB of disk space is free. Free some space and try again.'];
        }

        return [
            'ok' => true,
            'message' => 'PHP ' . PHP_VERSION . ', ' . ($free !== false ? human_bytes($free) . ' free' : 'disk space unknown'),
        ];
    }

    public static function clearCache(): int
    {
        $cleared = 0;
        $cacheDir = STORAGE_PATH . '/cache';
        if (is_dir($cacheDir)) {
            foreach (glob($cacheDir . '/*') ?: [] as $file) {
                if (is_file($file) && @unlink($file)) {
                    $cleared++;
                }
            }
        }
        SettingsService::flush();
        if (function_exists('opcache_reset')) {
            @opcache_reset();
            $cleared++;
        }
        return $cleared;
    }

    private static function writeLocalManifest(array $check): void
    {
        $manifest = self::localManifest();
        $manifest['version'] = $check['latest_version'];
        $manifest['commit'] = $check['commit_sha'] ?? ($manifest['commit'] ?? '');
        $manifest['release_date'] = gmdate('Y-m-d');
        $manifest['updated_at'] = now();
        if (!empty($check['release_notes'])) {
            $manifest['release_notes'] = $check['release_notes'];
        }
        @file_put_contents(
            ROOT_PATH . '/version.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    private static function cleanupTemp(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }

    public static function history(int $limit = 25): array
    {
        return Database::instance()->select(
            'SELECT u.*, usr.full_name AS triggered_by_name,
                    b.filename AS backup_filename, b.status AS backup_status
             FROM system_updates u
             LEFT JOIN users usr ON usr.id = u.triggered_by
             LEFT JOIN system_backups b ON b.id = u.backup_id
             ORDER BY u.id DESC LIMIT ' . max(1, $limit)
        );
    }

    public static function detail(int $updateId): ?array
    {
        $update = Database::instance()->first(
            'SELECT u.*, usr.full_name AS triggered_by_name, b.filename AS backup_filename
             FROM system_updates u
             LEFT JOIN users usr ON usr.id = u.triggered_by
             LEFT JOIN system_backups b ON b.id = u.backup_id
             WHERE u.id = :id',
            ['id' => $updateId]
        );
        if ($update === null) {
            return null;
        }
        $update['files'] = Database::instance()->select(
            'SELECT * FROM system_update_files WHERE update_id = :u ORDER BY action, path LIMIT 500',
            ['u' => $updateId]
        );
        $update['log_steps'] = json_decode((string) ($update['log'] ?? '[]'), true) ?: [];
        $update['health_checks'] = json_decode((string) ($update['health_report'] ?? '[]'), true) ?: [];
        return $update;
    }
}
