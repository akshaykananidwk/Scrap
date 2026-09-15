<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use App\Core\Paginator;

final class User extends Model
{
    protected static string $table = 'users';
    protected static bool $softDeletes = true;
    protected static array $fillable = [
        'uuid', 'full_name', 'email', 'mobile', 'alt_mobile', 'password_hash', 'account_type',
        'status', 'kyc_status', 'avatar', 'designation', 'email_verified_at', 'mobile_verified_at',
        'approved_at', 'approved_by', 'rejection_reason', 'preferred_language', 'notify_email',
        'notify_sms', 'notify_whatsapp', 'created_ip', 'created_at', 'updated_at',
    ];

    public static function withBusiness(int $id): ?array
    {
        return self::db()->first(
            'SELECT u.*, b.id AS business_id, b.name AS business_name, b.slug AS business_slug,
                    b.logo AS business_logo, b.business_type, b.city_name, b.state_name, b.gstin,
                    b.kyc_verified, b.gst_verified, b.rating_avg, b.rating_count
             FROM users u
             LEFT JOIN businesses b ON b.user_id = u.id AND b.deleted_at IS NULL
             WHERE u.id = :id AND u.deleted_at IS NULL
             LIMIT 1',
            ['id' => $id]
        );
    }

    public static function roleSlugs(int $userId): array
    {
        $rows = self::db()->select(
            'SELECT r.slug FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = :u',
            ['u' => $userId]
        );
        return array_map(static fn (array $r): string => (string) $r['slug'], $rows);
    }

    public static function syncRoles(int $userId, array $roleSlugs): void
    {
        $db = self::db();
        $db->delete('user_roles', ['user_id' => $userId]);
        if ($roleSlugs === []) {
            return;
        }
        [$in, $params] = $db->compileWhere(['slug' => $roleSlugs]);
        $roles = $db->select('SELECT id FROM roles WHERE ' . $in, $params);
        foreach ($roles as $role) {
            $db->statement(
                'INSERT IGNORE INTO user_roles (user_id, role_id, assigned_at) VALUES (:u, :r, :a)',
                ['u' => $userId, 'r' => (int) $role['id'], 'a' => now()]
            );
        }
    }

    public static function assignRole(int $userId, string $roleSlug): void
    {
        $roleId = (int) self::db()->scalar('SELECT id FROM roles WHERE slug = :s', ['s' => $roleSlug], 0);
        if ($roleId > 0) {
            self::db()->statement(
                'INSERT IGNORE INTO user_roles (user_id, role_id, assigned_at) VALUES (:u, :r, :a)',
                ['u' => $userId, 'r' => $roleId, 'a' => now()]
            );
        }
    }

    /** Roles implied by the account type — kept in sync whenever the type changes. */
    public static function rolesForAccountType(string $accountType): array
    {
        return match ($accountType) {
            'buyer' => ['buyer'],
            'seller' => ['seller'],
            default => ['trader', 'buyer', 'seller'],
        };
    }

    public static function paginate(array $filters, int $page, int $perPage = 25): Paginator
    {
        $sql = 'SELECT u.*, b.name AS business_name, b.city_name, b.state_name, b.kyc_verified,
                       (SELECT GROUP_CONCAT(r.slug) FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id) AS role_slugs
                FROM users u
                LEFT JOIN businesses b ON b.user_id = u.id AND b.deleted_at IS NULL
                WHERE u.deleted_at IS NULL';
        $count = 'SELECT COUNT(*) FROM users u LEFT JOIN businesses b ON b.user_id = u.id AND b.deleted_at IS NULL WHERE u.deleted_at IS NULL';
        $params = [];

        if (!empty($filters['q'])) {
            $clause = ' AND (u.full_name LIKE :q OR u.email LIKE :q OR u.mobile LIKE :q OR b.name LIKE :q)';
            $sql .= $clause;
            $count .= $clause;
            $params['q'] = '%' . $filters['q'] . '%';
        }
        foreach (['status' => 'u.status', 'account_type' => 'u.account_type', 'kyc_status' => 'u.kyc_status'] as $key => $column) {
            if (!empty($filters[$key])) {
                $clause = " AND {$column} = :{$key}";
                $sql .= $clause;
                $count .= $clause;
                $params[$key] = $filters[$key];
            }
        }
        if (!empty($filters['state_id'])) {
            $sql .= ' AND b.state_id = :state_id';
            $count .= ' AND b.state_id = :state_id';
            $params['state_id'] = (int) $filters['state_id'];
        }
        if (!empty($filters['role'])) {
            $clause = ' AND EXISTS (SELECT 1 FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id AND r.slug = :role)';
            $sql .= $clause;
            $count .= $clause;
            $params['role'] = $filters['role'];
        }

        $sort = match ($filters['sort'] ?? '') {
            'oldest' => 'u.id ASC',
            'name' => 'u.full_name ASC',
            default => 'u.id DESC',
        };
        $sql .= ' ORDER BY ' . $sort;

        return self::paginateQuery($sql, $params, $page, $perPage, $count);
    }

    public static function stats(int $userId): array
    {
        $db = self::db();
        return [
            'listings' => (int) $db->scalar('SELECT COUNT(*) FROM listings WHERE user_id = :u AND deleted_at IS NULL', ['u' => $userId], 0),
            'active_listings' => (int) $db->scalar("SELECT COUNT(*) FROM listings WHERE user_id = :u AND status = 'active' AND deleted_at IS NULL", ['u' => $userId], 0),
            'auctions' => (int) $db->scalar('SELECT COUNT(*) FROM auctions WHERE owner_id = :u AND deleted_at IS NULL', ['u' => $userId], 0),
            'bids' => (int) $db->scalar('SELECT COUNT(*) FROM bids WHERE user_id = :u', ['u' => $userId], 0),
            'requirements' => (int) $db->scalar('SELECT COUNT(*) FROM wanted_requirements WHERE user_id = :u AND deleted_at IS NULL', ['u' => $userId], 0),
            'orders_buying' => (int) $db->scalar('SELECT COUNT(*) FROM orders WHERE buyer_id = :u', ['u' => $userId], 0),
            'orders_selling' => (int) $db->scalar('SELECT COUNT(*) FROM orders WHERE seller_id = :u', ['u' => $userId], 0),
            'completed' => (int) $db->scalar("SELECT COUNT(*) FROM orders WHERE (buyer_id = :u OR seller_id = :u2) AND status = 'completed'", ['u' => $userId, 'u2' => $userId], 0),
            'unread_messages' => (int) $db->scalar(
                'SELECT COALESCE(SUM(CASE WHEN buyer_id = :u THEN buyer_unread WHEN seller_id = :u2 THEN seller_unread ELSE 0 END), 0)
                 FROM conversations WHERE buyer_id = :u3 OR seller_id = :u4',
                ['u' => $userId, 'u2' => $userId, 'u3' => $userId, 'u4' => $userId],
                0
            ),
            'unread_notifications' => (int) $db->scalar('SELECT COUNT(*) FROM notifications WHERE user_id = :u AND read_at IS NULL', ['u' => $userId], 0),
        ];
    }

    public static function recordLogin(?int $userId, string $identifier, bool $successful, string $ip, string $userAgent): void
    {
        Database::instance()->insert('login_history', [
            'user_id' => $userId,
            'identifier' => substr($identifier, 0, 190),
            'successful' => $successful ? 1 : 0,
            'ip' => $ip,
            'user_agent' => substr($userAgent, 0, 255),
            'created_at' => now(),
        ]);
    }

    public static function loginHistory(int $userId, int $limit = 20): array
    {
        return self::db()->select(
            'SELECT * FROM login_history WHERE user_id = :u ORDER BY id DESC LIMIT ' . max(1, $limit),
            ['u' => $userId]
        );
    }

    public static function counts(): array
    {
        $db = self::db();
        $row = $db->first(
            "SELECT
                COUNT(*) AS total,
                SUM(status = 'active') AS active,
                SUM(status = 'pending') AS pending,
                SUM(status = 'suspended') AS suspended,
                SUM(status = 'blocked') AS blocked,
                SUM(account_type = 'buyer') AS buyers,
                SUM(account_type = 'seller') AS sellers,
                SUM(account_type = 'both') AS traders,
                SUM(kyc_status = 'pending') AS kyc_pending,
                SUM(kyc_status = 'verified') AS kyc_verified,
                SUM(created_at >= :today) AS today
             FROM users WHERE deleted_at IS NULL",
            ['today' => gmdate('Y-m-d 00:00:00')]
        ) ?? [];
        return array_map(static fn ($v) => (int) $v, $row);
    }
}
