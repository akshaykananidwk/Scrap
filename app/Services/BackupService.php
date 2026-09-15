<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use ZipArchive;

/**
 * Backup and restore.
 *
 * `mysqldump` is usually unavailable on shared hosting, so the database dump is
 * written in pure PHP, streamed table by table so memory stays flat even on a
 * large `bids` table.
 *
 * Backups live in storage/backups (denied by .htaccess, outside the web root
 * where the host allows it) and are always pruned to the configured retention.
 */
final class BackupService
{
    private const CHUNK = 500;

    public static function directory(): string
    {
        $dir = STORAGE_PATH . '/backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /**
     * @param string $type full|database|files
     * @return array{ok: bool, backup_id?: int, filename?: string, path?: string, size?: int, error?: string}
     */
    public static function create(string $type = 'full', string $trigger = 'manual', ?int $userId = null): array
    {
        if (!in_array($type, ['full', 'database', 'files'], true)) {
            return ['ok' => false, 'error' => 'Invalid backup type.'];
        }
        if (!class_exists(ZipArchive::class)) {
            return ['ok' => false, 'error' => 'The PHP zip extension is required for backups.'];
        }

        $db = Database::instance();
        $uid = bin2hex(random_bytes(16));
        $version = \App\Services\InstallService::readVersion();
        $filename = sprintf('backup_%s_%s_%s.zip', gmdate('Ymd_His'), $type, str_replace('.', '-', $version));
        $path = self::directory() . '/' . $filename;

        $backupId = $db->insert('system_backups', [
            'backup_uid' => $uid,
            'filename' => $filename,
            'path' => $path,
            'backup_type' => $type,
            'trigger_type' => $trigger,
            'app_version' => $version,
            'schema_version' => (new \App\Core\Migrator($db))->currentVersion(),
            'status' => 'running',
            'created_by' => $userId,
            'created_at' => now(),
        ]);

        @set_time_limit(600);

        try {
            $zip = new ZipArchive();
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Could not create the backup archive. Check that storage/backups is writable.');
            }

            $tableCount = 0;
            $fileCount = 0;

            if ($type === 'full' || $type === 'database') {
                $dumpPath = STORAGE_PATH . '/tmp/dump_' . $uid . '.sql';
                $tableCount = self::dumpDatabase($dumpPath);
                $zip->addFile($dumpPath, 'database.sql');
                // The file must stay on disk until close(); it is removed below.
            }

            if ($type === 'full' || $type === 'files') {
                $fileCount = self::addApplicationFiles($zip);
            }

            $zip->addFromString('manifest.json', json_encode([
                'backup_uid' => $uid,
                'created_at' => now(),
                'type' => $type,
                'trigger' => $trigger,
                'app_version' => $version,
                'schema_version' => (new \App\Core\Migrator($db))->currentVersion(),
                'php_version' => PHP_VERSION,
                'database' => (string) \App\Core\Config::get('database.name', ''),
                'tables' => $tableCount,
                'files' => $fileCount,
                'site_url' => (string) \App\Core\Config::get('app.url', ''),
            ], JSON_PRETTY_PRINT));

            $zip->close();

            if (isset($dumpPath) && is_file($dumpPath)) {
                @unlink($dumpPath);
            }

            $size = filesize($path) ?: 0;
            $db->update('system_backups', [
                'size_bytes' => $size,
                'table_count' => $tableCount,
                'file_count' => $fileCount,
                'status' => 'completed',
                'completed_at' => now(),
            ], ['id' => $backupId]);

            AuditService::log('backup_created', 'backup', $backupId, null, ['type' => $type, 'size' => $size]);

            return ['ok' => true, 'backup_id' => $backupId, 'filename' => $filename, 'path' => $path, 'size' => $size];
        } catch (\Throwable $e) {
            Logger::instance()->error('Backup failed: ' . $e->getMessage());
            $db->update('system_backups', [
                'status' => 'failed',
                'error_message' => substr($e->getMessage(), 0, 500),
                'completed_at' => now(),
            ], ['id' => $backupId]);
            if (is_file($path)) {
                @unlink($path);
            }
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Streaming SQL dump — no mysqldump, no memory blow-up. */
    private static function dumpDatabase(string $destination): int
    {
        $db = Database::instance();
        $dir = dirname($destination);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $handle = fopen($destination, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Could not open the dump file for writing.');
        }

        fwrite($handle, "-- ScrapX database backup\n-- Generated: " . now() . " UTC\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n");
        fwrite($handle, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n");
        fwrite($handle, "SET NAMES utf8mb4;\n\n");

        $tables = $db->tables();
        foreach ($tables as $table) {
            $create = $db->first('SHOW CREATE TABLE `' . $db->safeTable($table) . '`');
            $createSql = $create['Create Table'] ?? ($create['Create View'] ?? null);
            if ($createSql === null) {
                continue;
            }

            fwrite($handle, "\n-- Table: {$table}\n");
            fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
            fwrite($handle, $createSql . ";\n\n");

            $total = (int) $db->scalar('SELECT COUNT(*) FROM `' . $db->safeTable($table) . '`', [], 0);
            if ($total === 0) {
                continue;
            }

            $pdo = $db->pdo();
            for ($offset = 0; $offset < $total; $offset += self::CHUNK) {
                $rows = $db->select(sprintf(
                    'SELECT * FROM `%s` LIMIT %d OFFSET %d',
                    $db->safeTable($table),
                    self::CHUNK,
                    $offset
                ));
                if ($rows === []) {
                    break;
                }

                $columns = '`' . implode('`, `', array_keys($rows[0])) . '`';
                $values = [];
                foreach ($rows as $row) {
                    $escaped = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $escaped[] = 'NULL';
                        } elseif (is_int($value) || is_float($value)) {
                            $escaped[] = (string) $value;
                        } else {
                            $escaped[] = $pdo->quote((string) $value);
                        }
                    }
                    $values[] = '(' . implode(',', $escaped) . ')';
                }
                fwrite($handle, "INSERT INTO `{$table}` ({$columns}) VALUES\n" . implode(",\n", $values) . ";\n");
            }
        }

        fwrite($handle, "\nSET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($handle);

        return count($tables);
    }

    /** Application code only — user uploads are optional and often huge. */
    private static function addApplicationFiles(ZipArchive $zip): int
    {
        $count = 0;
        $roots = ['app', 'config', 'database', 'resources', 'routes', 'public'];
        foreach ($roots as $root) {
            $base = ROOT_PATH . '/' . $root;
            if (!is_dir($base)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $relative = str_replace(ROOT_PATH . '/', '', $file->getPathname());
                $zip->addFile($file->getPathname(), 'files/' . $relative);
                $count++;
            }
        }

        foreach (['index.php', 'cli.php', '.htaccess', 'version.json', 'config.php'] as $file) {
            $path = ROOT_PATH . '/' . $file;
            if (is_file($path)) {
                $zip->addFile($path, 'files/' . $file);
                $count++;
            }
        }

        if (SettingsService::bool('backup_include_uploads', false) && is_dir(UPLOAD_PATH)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(UPLOAD_PATH, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $zip->addFile($file->getPathname(), 'uploads/' . str_replace(UPLOAD_PATH . '/', '', $file->getPathname()));
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Restore from a backup archive.
     * Database restore replays the dump; file restore copies files back,
     * always skipping protected paths so credentials survive.
     */
    public static function restore(int $backupId, bool $restoreFiles = false): array
    {
        $db = Database::instance();
        $backup = $db->first('SELECT * FROM system_backups WHERE id = :id', ['id' => $backupId]);
        if ($backup === null) {
            return ['ok' => false, 'error' => 'Backup record not found.'];
        }
        if (!is_file($backup['path'])) {
            return ['ok' => false, 'error' => 'The backup file is missing from disk.'];
        }

        @set_time_limit(900);

        $zip = new ZipArchive();
        if ($zip->open($backup['path']) !== true) {
            return ['ok' => false, 'error' => 'Could not open the backup archive.'];
        }

        $restored = ['tables' => 0, 'files' => 0];

        try {
            $sql = $zip->getFromName('database.sql');
            if ($sql !== false && $sql !== '') {
                $restored['tables'] = self::replayDump($sql);
            }

            if ($restoreFiles) {
                $protected = Updates\UpdateService::protectedPaths();
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);
                    if ($name === false || !str_starts_with($name, 'files/')) {
                        continue;
                    }
                    $relative = substr($name, 6);
                    if (Updates\UpdateService::isProtected($relative, $protected)) {
                        continue;
                    }
                    $target = ROOT_PATH . '/' . $relative;
                    $contents = $zip->getFromIndex($i);
                    if ($contents === false) {
                        continue;
                    }
                    $dir = dirname($target);
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0775, true);
                    }
                    if (@file_put_contents($target, $contents) !== false) {
                        $restored['files']++;
                    }
                }
            }

            $zip->close();

            $db->update('system_backups', ['status' => 'restored', 'restored_at' => now()], ['id' => $backupId]);
            SettingsService::flush();
            AuditService::log('backup_restored', 'backup', $backupId, null, $restored);

            return [
                'ok' => true,
                'message' => sprintf('Restored %d tables and %d files.', $restored['tables'], $restored['files']),
                'restored' => $restored,
            ];
        } catch (\Throwable $e) {
            $zip->close();
            Logger::instance()->error('Restore failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Restore failed: ' . $e->getMessage()];
        }
    }

    /** Execute a dump statement by statement (splitting on ";\n" boundaries). */
    private static function replayDump(string $sql): int
    {
        $db = Database::instance();
        $pdo = $db->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        $statements = 0;
        $buffer = '';
        foreach (explode("\n", $sql) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }
            $buffer .= $line . "\n";
            if (str_ends_with($trimmed, ';')) {
                try {
                    $pdo->exec($buffer);
                    $statements++;
                } catch (\Throwable $e) {
                    Logger::instance()->warning('Restore statement failed: ' . substr($e->getMessage(), 0, 200));
                }
                $buffer = '';
            }
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        return $statements;
    }

    public static function delete(int $backupId): array
    {
        $db = Database::instance();
        $backup = $db->first('SELECT * FROM system_backups WHERE id = :id', ['id' => $backupId]);
        if ($backup === null) {
            return ['ok' => false, 'error' => 'Backup not found.'];
        }
        if (is_file($backup['path'])) {
            @unlink($backup['path']);
        }
        $db->update('system_backups', ['status' => 'deleted'], ['id' => $backupId]);
        AuditService::log('backup_deleted', 'backup', $backupId);
        return ['ok' => true, 'message' => 'Backup deleted.'];
    }

    /** Enforce the retention policy. */
    public static function prune(): int
    {
        $keep = max(1, SettingsService::int('backup_retention_count', 10));
        $db = Database::instance();
        $old = $db->select(
            "SELECT id, path FROM system_backups
             WHERE status = 'completed'
             ORDER BY id DESC LIMIT 1000 OFFSET " . $keep
        );
        $removed = 0;
        foreach ($old as $backup) {
            if (is_file($backup['path'])) {
                @unlink($backup['path']);
            }
            $db->update('system_backups', ['status' => 'deleted'], ['id' => (int) $backup['id']]);
            $removed++;
        }
        return $removed;
    }

    public static function list(int $limit = 50): array
    {
        $rows = Database::instance()->select(
            "SELECT b.*, u.full_name AS created_by_name FROM system_backups b
             LEFT JOIN users u ON u.id = b.created_by
             WHERE b.status <> 'deleted' ORDER BY b.id DESC LIMIT " . max(1, $limit)
        );
        foreach ($rows as &$row) {
            $row['exists'] = is_file((string) $row['path']);
        }
        return $rows;
    }

    public static function find(int $backupId): ?array
    {
        return Database::instance()->first('SELECT * FROM system_backups WHERE id = :id', ['id' => $backupId]);
    }

    public static function diskUsage(): array
    {
        $files = glob(self::directory() . '/*.zip') ?: [];
        $total = 0;
        foreach ($files as $file) {
            $total += filesize($file) ?: 0;
        }
        return ['count' => count($files), 'bytes' => $total, 'human' => human_bytes($total)];
    }
}
