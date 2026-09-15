<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database;

final class RoleSeeder
{
    public const ROLES = [
        ['super_admin', 'Super Admin', 'Unrestricted access to everything', 1],
        ['admin', 'Admin', 'Day-to-day platform administration', 1],
        ['moderator', 'Moderator', 'Moderates listings, reports and content', 1],
        ['kyc_manager', 'KYC Manager', 'Reviews and approves KYC documents', 1],
        ['finance_manager', 'Finance Manager', 'Payments, commission and payouts', 1],
        ['support_manager', 'Support Manager', 'Disputes and customer support', 1],
        ['seller', 'Seller', 'Can list and sell scrap material', 0],
        ['buyer', 'Buyer', 'Can buy, bid and post requirements', 0],
        ['trader', 'Trader', 'Acts as both buyer and seller', 0],
        ['transporter', 'Transporter', 'Logistics partner', 0],
    ];

    public const PERMISSIONS = [
        // module => [slug => label]
        'users' => [
            'view_users' => 'View users',
            'manage_users' => 'Create / edit / suspend users',
            'reset_user_password' => 'Reset user passwords',
            'impersonate_users' => 'View user activity in detail',
        ],
        'kyc' => [
            'view_kyc' => 'View KYC submissions',
            'manage_kyc' => 'Approve / reject KYC',
        ],
        'catalog' => [
            'view_catalog' => 'View categories and materials',
            'manage_catalog' => 'Create / edit categories, materials, grades, units',
        ],
        'listings' => [
            'view_listings' => 'View all listings',
            'manage_listings' => 'Approve / reject / edit / delete listings',
        ],
        'auctions' => [
            'view_auctions' => 'View all auctions',
            'manage_auctions' => 'Cancel / award / edit auctions',
        ],
        'orders' => [
            'view_orders' => 'View all orders',
            'manage_orders' => 'Edit order status and details',
        ],
        'finance' => [
            'view_finance' => 'View payments, commission and wallets',
            'manage_finance' => 'Verify payments, adjust wallets, waive commission',
            'manage_invoices' => 'Generate and cancel invoices',
        ],
        'support' => [
            'view_disputes' => 'View disputes and reports',
            'manage_disputes' => 'Resolve disputes and content reports',
        ],
        'content' => [
            'manage_cms' => 'Edit CMS pages and FAQs',
            'manage_market_rates' => 'Manage market rates',
            'manage_notifications' => 'Manage notification templates and queue',
        ],
        'system' => [
            'view_settings' => 'View platform settings',
            'manage_settings' => 'Change platform settings',
            'manage_roles' => 'Manage roles and permissions',
            'view_audit_logs' => 'View audit logs',
            'manage_backups' => 'Create and restore backups',
            'manage_updates' => 'Check for and apply system updates',
            'view_system_health' => 'View logs, cron and health',
        ],
    ];

    /** role slug => permission slugs ('*' means everything) */
    public const ROLE_PERMISSIONS = [
        'super_admin' => ['*'],
        'admin' => [
            'view_users', 'manage_users', 'view_kyc', 'manage_kyc', 'view_catalog', 'manage_catalog',
            'view_listings', 'manage_listings', 'view_auctions', 'manage_auctions', 'view_orders',
            'manage_orders', 'view_finance', 'manage_invoices', 'view_disputes', 'manage_disputes',
            'manage_cms', 'manage_market_rates', 'manage_notifications', 'view_settings',
            'view_audit_logs', 'view_system_health',
        ],
        'moderator' => [
            'view_users', 'view_listings', 'manage_listings', 'view_auctions', 'view_disputes',
            'manage_disputes', 'view_catalog',
        ],
        'kyc_manager' => ['view_users', 'view_kyc', 'manage_kyc'],
        'finance_manager' => [
            'view_users', 'view_orders', 'view_finance', 'manage_finance', 'manage_invoices', 'view_audit_logs',
        ],
        'support_manager' => [
            'view_users', 'view_listings', 'view_orders', 'view_disputes', 'manage_disputes', 'view_auctions',
        ],
    ];

    public static function run(Database $db): void
    {
        foreach (self::ROLES as [$slug, $name, $description, $isStaff]) {
            $db->upsert('roles', [
                'slug' => $slug,
                'name' => $name,
                'description' => $description,
                'is_staff' => $isStaff,
                'is_system' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['name', 'description', 'is_staff', 'updated_at']);
        }

        foreach (self::PERMISSIONS as $module => $permissions) {
            foreach ($permissions as $slug => $name) {
                $db->upsert('permissions', [
                    'slug' => $slug,
                    'name' => $name,
                    'module' => $module,
                    'created_at' => now(),
                ], ['name', 'module']);
            }
        }

        $roleIds = [];
        foreach ($db->select('SELECT id, slug FROM roles') as $row) {
            $roleIds[$row['slug']] = (int) $row['id'];
        }
        $permissionIds = [];
        foreach ($db->select('SELECT id, slug FROM permissions') as $row) {
            $permissionIds[$row['slug']] = (int) $row['id'];
        }

        foreach (self::ROLE_PERMISSIONS as $roleSlug => $slugs) {
            if (!isset($roleIds[$roleSlug])) {
                continue;
            }
            $granted = $slugs === ['*'] ? array_keys($permissionIds) : $slugs;
            foreach ($granted as $permissionSlug) {
                if (!isset($permissionIds[$permissionSlug])) {
                    continue;
                }
                $db->statement(
                    'INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (:r, :p)',
                    ['r' => $roleIds[$roleSlug], 'p' => $permissionIds[$permissionSlug]]
                );
            }
        }
    }
}
