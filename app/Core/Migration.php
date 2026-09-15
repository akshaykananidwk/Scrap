<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Base migration. Each file in database/migrations returns an anonymous class
 * extending this, with a version, a description, and up()/down().
 */
abstract class Migration
{
    abstract public function version(): string;

    abstract public function description(): string;

    abstract public function up(Database $db): void;

    abstract public function down(Database $db): void;

    /**
     * ALTER TABLE ... ADD CONSTRAINT is not idempotent, so guard it. This keeps
     * a migration replayable if it half-failed on a flaky host.
     */
    protected function addForeignKeyIfMissing(Database $db, string $table, string $constraint, string $definition): void
    {
        $exists = (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.table_constraints
             WHERE constraint_schema = DATABASE() AND table_name = :t AND constraint_name = :c',
            ['t' => $table, 'c' => $constraint],
            0
        );
        if ($exists === 0) {
            $db->exec(sprintf(
                'ALTER TABLE `%s` ADD CONSTRAINT `%s` %s',
                $db->safeTable($table),
                $db->safeColumn($constraint),
                $definition
            ));
        }
    }

    protected function dropIfExists(Database $db, string ...$tables): void
    {
        $db->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $table) {
            $db->exec('DROP TABLE IF EXISTS `' . $db->safeTable($table) . '`');
        }
        $db->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
