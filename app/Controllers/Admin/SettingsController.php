<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Uploader;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\SettingsService;

final class SettingsController extends Controller
{
    protected string $layout = 'layouts/admin';

    private const GROUP_LABELS = [
        'general' => 'General',
        'marketplace' => 'Marketplace',
        'auction' => 'Auctions',
        'commission' => 'Commission & fees',
        'security' => 'Security',
        'uploads' => 'Uploads',
        'notifications' => 'Notifications',
        'mail' => 'Email / SMTP',
        'sms' => 'SMS gateway',
        'whatsapp' => 'WhatsApp',
        'payments' => 'Payments',
        'invoice' => 'Invoicing',
        'seo' => 'SEO',
        'backup' => 'Backups',
        'cron' => 'Scheduler',
        'updates' => 'Updates',
    ];

    public function index(Request $request): Response
    {
        $group = (string) $request->query('group', 'general');
        if (!isset(self::GROUP_LABELS[$group])) {
            $group = 'general';
        }

        return $this->view('admin/settings', [
            'title' => 'Settings — ' . self::GROUP_LABELS[$group],
            'group' => $group,
            'groups' => self::GROUP_LABELS,
            'settings' => SettingsService::group($group),
            'providers' => [
                'email' => NotificationService::provider('email'),
                'sms' => NotificationService::provider('sms'),
                'whatsapp' => NotificationService::provider('whatsapp'),
            ],
            'payment_gateway' => \App\Services\PaymentService::gateway(),
            'cron_secret' => SettingsService::get('cron_secret', ''),
            'cron_url' => base_url('cron/run?key=' . SettingsService::get('cron_secret', '')),
        ]);
    }

    public function save(Request $request): Response
    {
        $group = (string) $request->input('group', 'general');
        if (!isset(self::GROUP_LABELS[$group])) {
            return $this->fail('Unknown settings group.');
        }

        $rows = Database::instance()->select(
            'SELECT key_name, type FROM settings WHERE group_name = :g',
            ['g' => $group]
        );

        $submitted = $request->array('settings');
        $changed = [];

        foreach ($rows as $row) {
            $key = (string) $row['key_name'];
            $type = (string) $row['type'];

            if ($type === 'boolean') {
                // Unchecked checkboxes are absent from the POST body.
                $value = isset($submitted[$key]) && in_array((string) $submitted[$key], ['1', 'on', 'true', 'yes'], true);
            } else {
                if (!array_key_exists($key, $submitted)) {
                    continue;
                }
                $value = $submitted[$key];
                if (is_string($value)) {
                    $value = trim($value);
                }
                // A secret left as the placeholder means "keep the stored value".
                if ($type === 'secret' && ($value === '__SET__' || $value === '')) {
                    continue;
                }
                if ($type === 'integer') {
                    $value = (int) $value;
                }
            }

            SettingsService::set($key, $value, $type, $group);
            $changed[] = $key;
        }

        // File-backed settings.
        foreach (['site_logo' => 400, 'site_favicon' => 64, 'seo_og_image' => 1200] as $field => $width) {
            $file = $request->file($field);
            if ($file === null) {
                continue;
            }
            $uploader = Uploader::images('branding', 2);
            $path = $uploader->store($file, $width);
            if ($path !== null) {
                SettingsService::set($field, $path, 'string', $group);
                $changed[] = $field;
            } else {
                flash('warning', (string) $uploader->firstError());
            }
        }

        SettingsService::flush();
        AuditService::log('settings_saved', 'settings', null, null, ['group' => $group, 'keys' => $changed]);

        flash('success', count($changed) . ' setting(s) saved.');
        return $this->redirect('/admin/settings?group=' . $group);
    }

    /** Send a real test email so the operator knows SMTP works before going live. */
    public function testMail(Request $request): Response
    {
        $to = trim((string) $request->input('to', ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $this->fail('Enter a valid email address to send the test to.');
        }

        $provider = NotificationService::provider('email');
        if (!$provider->isConfigured()) {
            return $this->fail('Email is not configured yet. Set a from-address (and SMTP details if using SMTP).');
        }

        $result = $provider->send(
            $to,
            'ScrapX test email',
            '<p>This is a test email from <strong>' . e((string) SettingsService::get('site_name', 'ScrapX')) . '</strong>.</p>'
            . '<p>If you are reading this, outgoing email is working. Sent at ' . fmt_dt(now()) . '.</p>'
        );

        if ($request->wantsJson()) {
            return $result['ok']
                ? $this->ok($result, 'Test email sent to ' . $to . '.')
                : $this->fail((string) ($result['error'] ?? 'Sending failed.'));
        }

        flash($result['ok'] ? 'success' : 'danger', $result['ok']
            ? 'Test email sent to ' . $to . '.'
            : 'Could not send: ' . ($result['error'] ?? 'unknown error'));
        return $this->back('/admin/settings?group=mail');
    }

    public function roles(Request $request): Response
    {
        $db = Database::instance();
        $roles = $db->select('SELECT * FROM roles ORDER BY is_staff DESC, name');
        foreach ($roles as &$role) {
            $role['permissions'] = array_map(
                static fn (array $r): string => (string) $r['slug'],
                $db->select(
                    'SELECT p.slug FROM role_permissions rp INNER JOIN permissions p ON p.id = rp.permission_id
                     WHERE rp.role_id = :r',
                    ['r' => (int) $role['id']]
                )
            );
            $role['user_count'] = (int) $db->scalar(
                'SELECT COUNT(*) FROM user_roles WHERE role_id = :r',
                ['r' => (int) $role['id']],
                0
            );
        }

        $permissions = $db->select('SELECT * FROM permissions ORDER BY module, slug');
        $grouped = [];
        foreach ($permissions as $permission) {
            $grouped[(string) $permission['module']][] = $permission;
        }

        return $this->view('admin/roles', [
            'title' => 'Roles & permissions',
            'roles' => $roles,
            'permission_groups' => $grouped,
        ]);
    }

    public function savePermissions(Request $request): Response
    {
        $roleId = $request->paramInt('id');
        $db = Database::instance();
        $role = $db->first('SELECT * FROM roles WHERE id = :id', ['id' => $roleId]);
        if ($role === null) {
            return $this->fail('Role not found.');
        }
        if ($role['slug'] === 'super_admin') {
            return $this->fail('The super administrator always has every permission.');
        }

        $slugs = array_map('strval', $request->array('permissions'));
        $before = array_map(
            static fn (array $r): string => (string) $r['slug'],
            $db->select(
                'SELECT p.slug FROM role_permissions rp INNER JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = :r',
                ['r' => $roleId]
            )
        );

        $db->delete('role_permissions', ['role_id' => $roleId]);
        if ($slugs !== []) {
            [$where, $params] = $db->compileWhere(['slug' => $slugs]);
            foreach ($db->select('SELECT id FROM permissions WHERE ' . $where, $params) as $permission) {
                $db->statement(
                    'INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (:r, :p)',
                    ['r' => $roleId, 'p' => (int) $permission['id']]
                );
            }
        }

        AuditService::log('role_permissions_saved', 'role', $roleId, ['permissions' => $before], ['permissions' => $slugs]);
        flash('success', 'Permissions updated for ' . $role['name'] . '.');
        return $this->redirect('/admin/roles');
    }
}
