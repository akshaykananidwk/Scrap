<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Thin PDO wrapper. Every query is a prepared statement — there is no string
 * concatenation of user input anywhere in the application.
 */
final class Database
{
    private static ?Database $instance = null;
    private ?PDO $pdo = null;
    private int $transactionDepth = 0;
    private array $queryLog = [];
    private bool $logQueries = false;

    private function __construct(private array $config)
    {
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self([
                'host' => (string) Config::get('database.host', '127.0.0.1'),
                'port' => (string) Config::get('database.port', '3306'),
                'name' => (string) Config::get('database.name', ''),
                'user' => (string) Config::get('database.user', ''),
                'pass' => (string) Config::get('database.pass', ''),
                'charset' => (string) Config::get('database.charset', 'utf8mb4'),
            ]);
        }
        return self::$instance;
    }

    /** Used by the installer to test arbitrary credentials before writing config. */
    public static function connectWith(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'] ?? '3306',
            $config['name'],
            $config['charset'] ?? 'utf8mb4'
        );
        return new PDO($dsn, $config['user'], $config['pass'], self::pdoOptions());
    }

    public static function setInstanceFor(array $config): void
    {
        self::$instance = new self($config);
    }

    private static function pdoOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $this->config['host'],
                $this->config['port'],
                $this->config['name'],
                $this->config['charset']
            );
            try {
                $this->pdo = new PDO($dsn, $this->config['user'], $this->config['pass'], self::pdoOptions());
                // Strict mode protects DECIMAL/ENUM integrity instead of silently truncating.
                $this->pdo->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
                $this->pdo->exec("SET time_zone = '+00:00'");
            } catch (PDOException $e) {
                Logger::instance()->error('Database connection failed: ' . $e->getMessage());
                throw new HttpException(503, 'Database connection failed.');
            }
        }
        return $this->pdo;
    }

    public function enableQueryLog(): void
    {
        $this->logQueries = true;
    }

    public function queryLog(): array
    {
        return $this->queryLog;
    }

    public function run(string $sql, array $params = []): PDOStatement
    {
        $start = microtime(true);
        $statement = $this->pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $param = is_int($key) ? $key + 1 : $key;
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $statement->bindValue($param, $value, $type);
        }
        $statement->execute();
        if ($this->logQueries) {
            $this->queryLog[] = ['sql' => $sql, 'params' => $params, 'ms' => round((microtime(true) - $start) * 1000, 2)];
        }
        return $statement;
    }

    public function select(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function first(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function scalar(string $sql, array $params = [], mixed $default = null): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();
        return $value === false ? $default : $value;
    }

    public function statement(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    public function exec(string $sql): void
    {
        $this->pdo()->exec($sql);
    }

    public function insert(string $table, array $data): int
    {
        [$columns, $placeholders, $params] = $this->compileInsert($data);
        $sql = sprintf('INSERT INTO `%s` (%s) VALUES (%s)', $this->safeTable($table), $columns, $placeholders);
        $this->run($sql, $params);
        return (int) $this->pdo()->lastInsertId();
    }

    /** INSERT ... ON DUPLICATE KEY UPDATE for idempotent seeders/settings. */
    public function upsert(string $table, array $data, array $updateColumns): void
    {
        [$columns, $placeholders, $params] = $this->compileInsert($data);
        $updates = [];
        foreach ($updateColumns as $column) {
            $updates[] = sprintf('`%s` = VALUES(`%s`)', $this->safeColumn($column), $this->safeColumn($column));
        }
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
            $this->safeTable($table),
            $columns,
            $placeholders,
            implode(', ', $updates)
        );
        $this->run($sql, $params);
    }

    private function compileInsert(array $data): array
    {
        $columns = [];
        $placeholders = [];
        $params = [];
        foreach ($data as $column => $value) {
            $columns[] = '`' . $this->safeColumn($column) . '`';
            $placeholders[] = ':' . $column;
            $params[$column] = $value;
        }
        return [implode(', ', $columns), implode(', ', $placeholders), $params];
    }

    public function update(string $table, array $data, array $where): int
    {
        $sets = [];
        $params = [];
        foreach ($data as $column => $value) {
            $sets[] = sprintf('`%s` = :set_%s', $this->safeColumn($column), $column);
            $params['set_' . $column] = $value;
        }
        [$whereSql, $whereParams] = $this->compileWhere($where);
        $params = array_merge($params, $whereParams);
        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $this->safeTable($table), implode(', ', $sets), $whereSql);
        return $this->statement($sql, $params);
    }

    public function delete(string $table, array $where): int
    {
        [$whereSql, $params] = $this->compileWhere($where);
        $sql = sprintf('DELETE FROM `%s` WHERE %s', $this->safeTable($table), $whereSql);
        return $this->statement($sql, $params);
    }

    public function count(string $table, array $where = []): int
    {
        if ($where === []) {
            return (int) $this->scalar(sprintf('SELECT COUNT(*) FROM `%s`', $this->safeTable($table)), [], 0);
        }
        [$whereSql, $params] = $this->compileWhere($where);
        $sql = sprintf('SELECT COUNT(*) FROM `%s` WHERE %s', $this->safeTable($table), $whereSql);
        return (int) $this->scalar($sql, $params, 0);
    }

    public function compileWhere(array $where, string $prefix = 'w_'): array
    {
        $clauses = [];
        $params = [];
        $i = 0;
        foreach ($where as $column => $value) {
            $key = $prefix . $i++;
            $safe = $this->safeColumn($column);
            if ($value === null) {
                $clauses[] = "`{$safe}` IS NULL";
                continue;
            }
            if (is_array($value)) {
                if ($value === []) {
                    $clauses[] = '1 = 0';
                    continue;
                }
                $in = [];
                foreach (array_values($value) as $j => $item) {
                    $in[] = ":{$key}_{$j}";
                    $params["{$key}_{$j}"] = $item;
                }
                $clauses[] = "`{$safe}` IN (" . implode(', ', $in) . ')';
                continue;
            }
            $clauses[] = "`{$safe}` = :{$key}";
            $params[$key] = $value;
        }
        return [$clauses === [] ? '1 = 1' : implode(' AND ', $clauses), $params];
    }

    public function safeTable(string $table): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            throw new \InvalidArgumentException('Invalid table name.');
        }
        return $table;
    }

    public function safeColumn(string $column): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $column)) {
            throw new \InvalidArgumentException('Invalid column name.');
        }
        return $column;
    }

    public function beginTransaction(): void
    {
        if ($this->transactionDepth === 0) {
            $this->pdo()->beginTransaction();
        } else {
            $this->pdo()->exec('SAVEPOINT sp_' . $this->transactionDepth);
        }
        $this->transactionDepth++;
    }

    public function commit(): void
    {
        if ($this->transactionDepth === 0) {
            return;
        }
        $this->transactionDepth--;
        if ($this->transactionDepth === 0) {
            $this->pdo()->commit();
        } else {
            $this->pdo()->exec('RELEASE SAVEPOINT sp_' . $this->transactionDepth);
        }
    }

    public function rollBack(): void
    {
        if ($this->transactionDepth === 0) {
            return;
        }
        $this->transactionDepth--;
        if ($this->transactionDepth === 0) {
            if ($this->pdo()->inTransaction()) {
                $this->pdo()->rollBack();
            }
        } else {
            $this->pdo()->exec('ROLLBACK TO SAVEPOINT sp_' . $this->transactionDepth);
        }
    }

    /** Run a closure inside a transaction; rolls back and rethrows on failure. */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    /** Row-level lock helper used by the bidding engine. */
    public function lockRow(string $table, int $id): ?array
    {
        $sql = sprintf('SELECT * FROM `%s` WHERE id = :id FOR UPDATE', $this->safeTable($table));
        return $this->first($sql, ['id' => $id]);
    }

    public function tableExists(string $table): bool
    {
        $row = $this->first(
            'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t',
            ['t' => $table]
        );
        return (int) ($row['c'] ?? 0) > 0;
    }

    public function columns(string $table): array
    {
        $rows = $this->select(
            'SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t',
            ['t' => $table]
        );
        return array_map(static fn (array $r): string => (string) ($r['column_name'] ?? $r['COLUMN_NAME']), $rows);
    }

    public function tables(): array
    {
        $rows = $this->select('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name');
        return array_map(static fn (array $r): string => (string) ($r['table_name'] ?? $r['TABLE_NAME']), $rows);
    }
}
