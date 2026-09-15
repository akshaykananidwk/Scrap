<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Migration runner. Discovers database/migrations/*.php, applies the pending
 * ones in version order, and records each in `migrations`. The GitHub updater
 * calls run() automatically after deploying new files.
 */
final class Migrator
{
    public function __construct(private Database $db)
    {
    }

    public function ensureTable(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS `migrations` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `version` VARCHAR(32) NOT NULL,
                `description` VARCHAR(255) NOT NULL DEFAULT '',
                `batch` INT UNSIGNED NOT NULL DEFAULT 1,
                `executed_at` DATETIME NOT NULL,
                `execution_ms` INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_migration_version` (`version`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** @return array<string, Migration> version => migration */
    public function discover(): array
    {
        $files = glob(DATABASE_PATH . '/migrations/*.php') ?: [];
        sort($files);
        $migrations = [];
        foreach ($files as $file) {
            $migration = require $file;
            if (!$migration instanceof Migration) {
                throw new \RuntimeException('Migration file did not return a Migration instance: ' . basename($file));
            }
            $migrations[$migration->version()] = $migration;
        }
        ksort($migrations);
        return $migrations;
    }

    public function applied(): array
    {
        $this->ensureTable();
        $rows = $this->db->select('SELECT version, description, batch, executed_at FROM migrations ORDER BY version');
        $out = [];
        foreach ($rows as $row) {
            $out[$row['version']] = $row;
        }
        return $out;
    }

    /** @return array<string, Migration> */
    public function pending(): array
    {
        $applied = $this->applied();
        return array_diff_key($this->discover(), $applied);
    }

    /**
     * Apply all pending migrations.
     * @return array{applied: string[], errors: string[]}
     */
    public function run(?callable $progress = null): array
    {
        $this->ensureTable();
        $pending = $this->pending();
        $batch = (int) $this->db->scalar('SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations', [], 1);

        $appliedVersions = [];
        $errors = [];

        foreach ($pending as $version => $migration) {
            $start = microtime(true);
            try {
                // DDL is not transactional in MySQL, so each migration must be
                // individually safe/idempotent (CREATE TABLE IF NOT EXISTS etc.).
                $migration->up($this->db);
                $this->db->insert('migrations', [
                    'version' => $version,
                    'description' => substr($migration->description(), 0, 255),
                    'batch' => $batch,
                    'executed_at' => now(),
                    'execution_ms' => (int) round((microtime(true) - $start) * 1000),
                ]);
                $appliedVersions[] = $version;
                if ($progress) {
                    $progress($version, $migration->description(), true, null);
                }
            } catch (\Throwable $e) {
                $errors[] = $version . ': ' . $e->getMessage();
                Logger::instance()->error('Migration failed', ['version' => $version, 'error' => $e->getMessage()]);
                if ($progress) {
                    $progress($version, $migration->description(), false, $e->getMessage());
                }
                break;
            }
        }

        return ['applied' => $appliedVersions, 'errors' => $errors];
    }

    /** Roll back the most recent batch (used by update rollback). */
    public function rollbackBatch(?int $batch = null): array
    {
        $this->ensureTable();
        $batch ??= (int) $this->db->scalar('SELECT COALESCE(MAX(batch), 0) FROM migrations', [], 0);
        if ($batch <= 0) {
            return ['rolled_back' => [], 'errors' => []];
        }
        $rows = $this->db->select('SELECT version FROM migrations WHERE batch = :b ORDER BY version DESC', ['b' => $batch]);
        $all = $this->discover();
        $rolledBack = [];
        $errors = [];
        foreach ($rows as $row) {
            $version = (string) $row['version'];
            if (!isset($all[$version])) {
                continue;
            }
            try {
                $all[$version]->down($this->db);
                $this->db->delete('migrations', ['version' => $version]);
                $rolledBack[] = $version;
            } catch (\Throwable $e) {
                $errors[] = $version . ': ' . $e->getMessage();
            }
        }
        return ['rolled_back' => $rolledBack, 'errors' => $errors];
    }

    public function rollbackTo(string $version): array
    {
        $this->ensureTable();
        $rows = $this->db->select('SELECT version FROM migrations WHERE version > :v ORDER BY version DESC', ['v' => $version]);
        $all = $this->discover();
        $rolledBack = [];
        $errors = [];
        foreach ($rows as $row) {
            $v = (string) $row['version'];
            if (!isset($all[$v])) {
                continue;
            }
            try {
                $all[$v]->down($this->db);
                $this->db->delete('migrations', ['version' => $v]);
                $rolledBack[] = $v;
            } catch (\Throwable $e) {
                $errors[] = $v . ': ' . $e->getMessage();
            }
        }
        return ['rolled_back' => $rolledBack, 'errors' => $errors];
    }

    public function currentVersion(): string
    {
        $this->ensureTable();
        return (string) $this->db->scalar('SELECT COALESCE(MAX(version), "0") FROM migrations', [], '0');
    }
}
