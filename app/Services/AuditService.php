<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Model;
use App\Core\Paginator;

/**
 * Append-only audit trail. Sensitive fields are redacted before storage so the
 * log itself never becomes a credential leak.
 */
final class AuditService
{
    private const REDACT = [
        'password', 'password_hash', 'token', 'github_token', 'api_key', 'secret',
        'smtp_password', 'razorpay_key_secret', 'token_hash', 'code_hash',
    ];

    public static function log(
        string $action,
        string $entityType = '',
        ?int $entityId = null,
        ?array $before = null,
        ?array $after = null,
        ?string $description = null
    ): void {
        try {
            Database::instance()->insert('audit_logs', [
                'user_id' => Auth::id(),
                'action' => substr($action, 0, 60),
                'entity_type' => substr($entityType, 0, 60),
                'entity_id' => $entityId,
                'description' => $description !== null ? substr($description, 0, 255) : null,
                'before_data' => $before !== null ? self::encode($before) : null,
                'after_data' => $after !== null ? self::encode($after) : null,
                'ip' => self::ip(),
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Auditing must never break the user-facing action.
            Logger::instance()->warning('Audit log failed: ' . $e->getMessage(), ['action' => $action]);
        }
    }

    /** Log only the fields that actually changed. */
    public static function logChange(string $action, string $entityType, int $entityId, array $before, array $after): void
    {
        $changedBefore = [];
        $changedAfter = [];
        foreach ($after as $key => $value) {
            if (!array_key_exists($key, $before) || (string) $before[$key] !== (string) $value) {
                $changedBefore[$key] = $before[$key] ?? null;
                $changedAfter[$key] = $value;
            }
        }
        if ($changedAfter === []) {
            return;
        }
        self::log($action, $entityType, $entityId, $changedBefore, $changedAfter);
    }

    private static function encode(array $data): string
    {
        foreach ($data as $key => $value) {
            foreach (self::REDACT as $needle) {
                if (stripos((string) $key, $needle) !== false) {
                    $data[$key] = '[redacted]';
                    continue 2;
                }
            }
            if (is_array($value)) {
                $data[$key] = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }
        return substr((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 60000);
    }

    private static function ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            $value = $_SERVER[$key] ?? '';
            if ($value !== '') {
                $ip = trim(explode(',', (string) $value)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    public static function paginate(array $filters, int $page, int $perPage = 40): Paginator
    {
        $sql = 'SELECT a.*, u.full_name, u.mobile FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE 1 = 1';
        $params = [];
        if (!empty($filters['user_id'])) {
            $sql .= ' AND a.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['action'])) {
            $sql .= ' AND a.action = :action';
            $params['action'] = $filters['action'];
        }
        if (!empty($filters['entity_type'])) {
            $sql .= ' AND a.entity_type = :entity_type';
            $params['entity_type'] = $filters['entity_type'];
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND a.created_at >= :from';
            $params['from'] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND a.created_at <= :to';
            $params['to'] = $filters['to'] . ' 23:59:59';
        }
        $sql .= ' ORDER BY a.id DESC';

        return Model::paginateQuery(
            $sql,
            $params,
            $page,
            $perPage,
            'SELECT COUNT(*) FROM audit_logs a WHERE 1 = 1'
                . (!empty($filters['user_id']) ? ' AND a.user_id = :user_id' : '')
                . (!empty($filters['action']) ? ' AND a.action = :action' : '')
                . (!empty($filters['entity_type']) ? ' AND a.entity_type = :entity_type' : '')
                . (!empty($filters['from']) ? ' AND a.created_at >= :from' : '')
                . (!empty($filters['to']) ? ' AND a.created_at <= :to' : '')
        );
    }

    public static function actions(): array
    {
        $rows = Database::instance()->select('SELECT DISTINCT action FROM audit_logs ORDER BY action LIMIT 200');
        return array_map(static fn (array $r): string => (string) $r['action'], $rows);
    }
}
