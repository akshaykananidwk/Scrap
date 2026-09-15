<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Lightweight table gateway. Models expose typed finders; complex reporting
 * queries live in the model too (never in controllers or views).
 */
abstract class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;
    protected static bool $softDeletes = false;
    /** Columns that may be mass-assigned from request data. */
    protected static array $fillable = [];

    public static function table(): string
    {
        return static::$table;
    }

    public static function db(): Database
    {
        return Database::instance();
    }

    public static function filter(array $data): array
    {
        if (static::$fillable === []) {
            return $data;
        }
        return array_intersect_key($data, array_flip(static::$fillable));
    }

    public static function create(array $data): int
    {
        $data = static::filter($data);
        if (static::$timestamps) {
            $data['created_at'] ??= now();
            $data['updated_at'] ??= now();
        }
        return static::db()->insert(static::$table, $data);
    }

    /** Insert without fillable filtering — for trusted internal writes. */
    public static function insertRaw(array $data): int
    {
        if (static::$timestamps) {
            $data['created_at'] ??= now();
            $data['updated_at'] ??= now();
        }
        return static::db()->insert(static::$table, $data);
    }

    public static function updateById(int $id, array $data, bool $raw = false): int
    {
        $data = $raw ? $data : static::filter($data);
        if ($data === []) {
            return 0;
        }
        if (static::$timestamps) {
            $data['updated_at'] = now();
        }
        return static::db()->update(static::$table, $data, [static::$primaryKey => $id]);
    }

    public static function find(int $id): ?array
    {
        $sql = sprintf('SELECT * FROM `%s` WHERE `%s` = :id', static::$table, static::$primaryKey);
        if (static::$softDeletes) {
            $sql .= ' AND deleted_at IS NULL';
        }
        return static::db()->first($sql . ' LIMIT 1', ['id' => $id]);
    }

    public static function findOrFail(int $id): array
    {
        $row = static::find($id);
        if ($row === null) {
            throw new HttpException(404, 'Record not found.');
        }
        return $row;
    }

    public static function findBy(string $column, mixed $value): ?array
    {
        $column = static::db()->safeColumn($column);
        $sql = sprintf('SELECT * FROM `%s` WHERE `%s` = :v', static::$table, $column);
        if (static::$softDeletes) {
            $sql .= ' AND deleted_at IS NULL';
        }
        return static::db()->first($sql . ' LIMIT 1', ['v' => $value]);
    }

    public static function where(array $conditions, ?string $orderBy = null, ?int $limit = null): array
    {
        [$whereSql, $params] = static::db()->compileWhere($conditions);
        $sql = sprintf('SELECT * FROM `%s` WHERE %s', static::$table, $whereSql);
        if (static::$softDeletes) {
            $sql .= ' AND deleted_at IS NULL';
        }
        if ($orderBy !== null) {
            $sql .= ' ORDER BY ' . static::safeOrder($orderBy);
        }
        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, $limit);
        }
        return static::db()->select($sql, $params);
    }

    public static function first(array $conditions, ?string $orderBy = null): ?array
    {
        $rows = static::where($conditions, $orderBy, 1);
        return $rows[0] ?? null;
    }

    public static function all(?string $orderBy = null, ?int $limit = null): array
    {
        $sql = sprintf('SELECT * FROM `%s`', static::$table);
        if (static::$softDeletes) {
            $sql .= ' WHERE deleted_at IS NULL';
        }
        if ($orderBy !== null) {
            $sql .= ' ORDER BY ' . static::safeOrder($orderBy);
        }
        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, $limit);
        }
        return static::db()->select($sql);
    }

    public static function count(array $conditions = []): int
    {
        if (static::$softDeletes) {
            [$whereSql, $params] = static::db()->compileWhere($conditions);
            $sql = sprintf('SELECT COUNT(*) FROM `%s` WHERE %s AND deleted_at IS NULL', static::$table, $whereSql);
            return (int) static::db()->scalar($sql, $params, 0);
        }
        return static::db()->count(static::$table, $conditions);
    }

    public static function exists(array $conditions): bool
    {
        return static::count($conditions) > 0;
    }

    public static function destroy(int $id): int
    {
        if (static::$softDeletes) {
            return static::db()->update(static::$table, ['deleted_at' => now()], [static::$primaryKey => $id]);
        }
        return static::db()->delete(static::$table, [static::$primaryKey => $id]);
    }

    public static function forceDelete(int $id): int
    {
        return static::db()->delete(static::$table, [static::$primaryKey => $id]);
    }

    public static function restore(int $id): int
    {
        return static::db()->update(static::$table, ['deleted_at' => null], [static::$primaryKey => $id]);
    }

    /** Whitelist "column direction" order clauses to keep them injection-proof. */
    protected static function safeOrder(string $order): string
    {
        $parts = [];
        foreach (explode(',', $order) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }
            $tokens = preg_split('/\s+/', $chunk) ?: [];
            $column = $tokens[0] ?? 'id';
            $direction = strtoupper($tokens[1] ?? 'ASC');
            if (!preg_match('/^[A-Za-z0-9_.]+$/', $column)) {
                continue;
            }
            $parts[] = '`' . str_replace('.', '`.`', $column) . '` ' . ($direction === 'DESC' ? 'DESC' : 'ASC');
        }
        return $parts === [] ? '`id` DESC' : implode(', ', $parts);
    }

    /** Paginate an arbitrary SELECT with its bound params. */
    public static function paginateQuery(string $sql, array $params, int $page, int $perPage, string $countSql = ''): Paginator
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        if ($countSql === '') {
            $countSql = 'SELECT COUNT(*) FROM (' . $sql . ') AS sub';
        }
        $total = (int) static::db()->scalar($countSql, $params, 0);
        $rows = static::db()->select($sql . ' LIMIT ' . $perPage . ' OFFSET ' . $offset, $params);

        return new Paginator($rows, $total, $page, $perPage);
    }
}
