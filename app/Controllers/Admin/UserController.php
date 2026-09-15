<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Business;
use App\Models\Listing;
use App\Models\Order;
use App\Models\State;
use App\Models\User;
use App\Services\AuditService;
use App\Services\FraudService;
use App\Services\NotificationService;
use App\Services\ReviewService;

final class UserController extends Controller
{
    protected string $layout = 'layouts/admin';

    public function index(Request $request): Response
    {
        $filters = array_filter([
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'account_type' => (string) $request->query('account_type', ''),
            'kyc_status' => (string) $request->query('kyc_status', ''),
            'role' => (string) $request->query('role', ''),
            'state_id' => $request->int('state_id'),
            'sort' => (string) $request->query('sort', 'newest'),
        ], static fn ($v): bool => $v !== '' && $v !== 0);

        return $this->view('admin/users', [
            'title' => 'Users',
            'users' => User::paginate($filters, $request->page(), 25),
            'filters' => $filters,
            'counts' => User::counts(),
            'roles' => Database::instance()->select('SELECT slug, name FROM roles ORDER BY is_staff DESC, name'),
            'states' => State::active(),
        ]);
    }

    public function show(Request $request): Response
    {
        $userId = $request->paramInt('id');
        $user = User::withBusiness($userId);
        if ($user === null) {
            throw new HttpException(404, 'User not found.');
        }

        return $this->view('admin/user_show', [
            'title' => $user['full_name'],
            'user' => $user,
            'roles' => Database::instance()->select('SELECT * FROM roles ORDER BY is_staff DESC, name'),
            'user_roles' => User::roleSlugs($userId),
            'stats' => User::stats($userId),
            'listings' => Listing::search(['user_id' => $userId, 'status' => 'any'], 1, 10)->items,
            'orders' => Order::paginate(['user_id' => $userId], 1, 10)->items,
            'logins' => User::loginHistory($userId, 15),
            'fraud_flags' => FraudService::flagsFor($userId),
            'reviews' => ReviewService::summary($userId),
            'kyc' => \App\Services\KycService::statusFor($userId),
            'audit' => AuditService::paginate(['user_id' => $userId], 1, 15)->items,
            'wallet' => \App\Services\WalletService::isEnabled() ? \App\Services\WalletService::wallet($userId) : null,
        ]);
    }

    public function changeStatus(Request $request): Response
    {
        $userId = $request->paramInt('id');
        $user = User::find($userId);
        if ($user === null) {
            throw new HttpException(404);
        }

        $status = (string) $request->input('status', '');
        if (!in_array($status, ['active', 'pending', 'suspended', 'blocked', 'rejected'], true)) {
            return $this->fail('Invalid status.');
        }

        // A super admin must always remain reachable.
        if ($userId === Auth::id() && $status !== 'active') {
            return $this->fail('You cannot change the status of your own account.');
        }
        if (in_array('super_admin', User::roleSlugs($userId), true) && $status !== 'active') {
            return $this->fail('A super administrator account cannot be suspended from here.');
        }

        $reason = (string) $request->input('reason', '');
        User::updateById($userId, [
            'status' => $status,
            'rejection_reason' => in_array($status, ['rejected', 'blocked', 'suspended'], true) ? substr($reason, 0, 255) : null,
            'approved_at' => $status === 'active' ? now() : $user['approved_at'],
            'approved_by' => $status === 'active' ? Auth::id() : $user['approved_by'],
        ], true);

        NotificationService::dispatch($userId, 'account_status', [
            'body' => 'Your account status is now ' . label($status) . '.' . ($reason !== '' ? ' Reason: ' . $reason : ''),
            'link' => '/dashboard',
        ]);

        AuditService::log('user_status_changed', 'user', $userId, ['status' => $user['status']], ['status' => $status], $reason);

        flash('success', $user['full_name'] . ' is now ' . label($status) . '.');
        return $this->back('/admin/users/' . $userId);
    }

    public function updateRoles(Request $request): Response
    {
        $userId = $request->paramInt('id');
        $roles = array_map('strval', $request->array('roles'));

        if ($userId === Auth::id() && !in_array('super_admin', $roles, true) && Auth::hasRole('super_admin')) {
            return $this->fail('You cannot remove your own super administrator role.');
        }

        $before = User::roleSlugs($userId);
        User::syncRoles($userId, $roles);
        AuditService::log('user_roles_changed', 'user', $userId, ['roles' => $before], ['roles' => $roles]);

        flash('success', 'Roles updated.');
        return $this->back('/admin/users/' . $userId);
    }

    public function resetPassword(Request $request): Response
    {
        $userId = $request->paramInt('id');
        $user = User::find($userId);
        if ($user === null) {
            throw new HttpException(404);
        }

        // Generate a strong temporary password rather than letting staff choose one.
        $temporary = 'Sx' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)) . random_int(10, 99);
        User::updateById($userId, ['password_hash' => Auth::hash($temporary)], true);

        Database::instance()->statement(
            'UPDATE api_tokens SET revoked_at = :n WHERE user_id = :u AND revoked_at IS NULL',
            ['n' => now(), 'u' => $userId]
        );

        NotificationService::dispatch($userId, 'account_status', [
            'title' => 'Your password was reset by an administrator',
            'body' => 'Sign in with the temporary password you have been given and change it immediately.',
            'link' => '/dashboard/profile',
        ]);

        AuditService::log('admin_password_reset', 'user', $userId);

        flash('warning', 'Temporary password for ' . $user['full_name'] . ': ' . $temporary . ' — share it securely; it is shown only once.');
        return $this->back('/admin/users/' . $userId);
    }

    public function evaluateRisk(Request $request): Response
    {
        $userId = $request->paramInt('id');
        $result = FraudService::evaluate($userId);

        if ($request->wantsJson()) {
            return $this->ok($result, 'Risk score: ' . $result['score']);
        }
        flash('info', 'Risk re-evaluated. Score: ' . $result['score'] . ' (' . count($result['flags']) . ' flag(s)).');
        return $this->back('/admin/users/' . $userId);
    }
}
