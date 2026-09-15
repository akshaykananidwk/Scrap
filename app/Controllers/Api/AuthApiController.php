<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Models\Business;
use App\Models\User;
use App\Services\InstallService;
use App\Services\OtpService;
use App\Services\SettingsService;

final class AuthApiController extends BaseApiController
{
    public function login(Request $request): Response
    {
        $identifier = trim((string) $request->input('identifier', ''));
        $password = (string) $request->raw('password', '');

        if ($identifier === '' || $password === '') {
            return $this->error('identifier and password are required.');
        }

        $key = 'api-login:' . strtolower($identifier) . ':' . $request->ip();
        $max = SettingsService::int('login_max_attempts', 5);
        $decay = SettingsService::int('login_decay_seconds', 900);

        if (RateLimiter::tooManyAttempts($key, $max, $decay)) {
            return $this->error('Too many failed attempts. Try again later.', 429);
        }

        $user = Auth::attempt($identifier, $password);
        if ($user === null) {
            RateLimiter::hit($key, $decay);
            User::recordLogin(null, $identifier, false, $request->ip(), $request->userAgent());
            return $this->error('Invalid credentials.', 401);
        }
        if ($user['status'] !== 'active') {
            return $this->error('This account is ' . $user['status'] . '.', 403);
        }

        RateLimiter::clear($key, $decay);
        $token = Auth::issueToken(
            (int) $user['id'],
            (string) $request->input('device_name', 'api'),
            gmdate('Y-m-d H:i:s', strtotime('+90 days'))
        );
        User::recordLogin((int) $user['id'], $identifier, true, $request->ip(), $request->userAgent());
        User::updateById((int) $user['id'], ['last_login_at' => now()], true);

        return $this->data([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => gmdate('Y-m-d H:i:s', strtotime('+90 days')),
            'user' => $this->presentUser(User::withBusiness((int) $user['id']) ?? $user),
        ]);
    }

    public function register(Request $request): Response
    {
        $validator = \App\Core\Validator::make($request->all(), [
            'full_name' => 'required|min:3|max:150',
            'business_name' => 'required|min:2|max:180',
            'mobile' => 'required|mobile|unique:users,mobile',
            'email' => 'nullable|email|unique:users,email',
            'password' => 'required|password',
            'account_type' => 'required|in:buyer,seller,both',
            'business_type' => 'required|in:' . implode(',', array_keys(Business::TYPES)),
            'state_id' => 'required|integer|exists:states,id',
        ]);

        if ($validator->fails()) {
            return $this->error((string) $validator->firstError(), 422, ['errors' => $validator->errors()]);
        }

        $mobile = substr(preg_replace('/\D/', '', (string) $request->input('mobile')) ?? '', -10);
        $accountType = (string) $request->input('account_type');
        $db = Database::instance();

        $userId = $db->transaction(function (Database $db) use ($request, $mobile, $accountType): int {
            $userId = $db->insert('users', [
                'uuid' => InstallService::uuid(),
                'full_name' => (string) $request->input('full_name'),
                'email' => $request->input('email') ? strtolower((string) $request->input('email')) : null,
                'mobile' => $mobile,
                'password_hash' => Auth::hash((string) $request->raw('password')),
                'account_type' => $accountType,
                'status' => SettingsService::bool('require_admin_approval', false) ? 'pending' : 'active',
                'created_ip' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (User::rolesForAccountType($accountType) as $role) {
                User::assignRole($userId, $role);
            }

            $businessName = (string) $request->input('business_name');
            $state = \App\Models\State::find($request->int('state_id'));
            $db->insert('businesses', [
                'user_id' => $userId,
                'name' => $businessName,
                'slug' => Business::uniqueSlug($businessName),
                'business_type' => (string) $request->input('business_type'),
                'contact_person' => (string) $request->input('full_name'),
                'contact_mobile' => $mobile,
                'state_id' => $state['id'] ?? null,
                'state_name' => $state['name'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $userId;
        });

        $otp = OtpService::send($mobile, 'verify_mobile', 'sms', $userId, $request->ip());
        $token = Auth::issueToken($userId, 'api', gmdate('Y-m-d H:i:s', strtotime('+90 days')));

        return $this->data([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->presentUser(User::withBusiness($userId) ?? []),
            'verification' => [
                'required' => SettingsService::bool('require_mobile_verification', true),
                'sent' => $otp['ok'],
                'message' => $otp['message'] ?? ($otp['error'] ?? ''),
            ],
        ]);
    }

    public function sendOtp(Request $request): Response
    {
        $destination = (string) $request->input('destination', '');
        $purpose = (string) $request->input('purpose', 'verify_mobile');
        $channel = (string) $request->input('channel', 'sms');

        if (!in_array($purpose, OtpService::PURPOSES, true)) {
            return $this->error('Invalid purpose.');
        }

        $result = OtpService::send($destination, $purpose, $channel, Auth::id(), $request->ip());
        return $result['ok']
            ? $this->data(['sent' => true, 'expires_in' => $result['expires_in'], 'message' => $result['message']])
            : $this->error((string) $result['error']);
    }

    public function verifyOtp(Request $request): Response
    {
        $result = OtpService::verify(
            (string) $request->input('destination', ''),
            (string) $request->input('code', ''),
            (string) $request->input('purpose', 'verify_mobile')
        );

        if (!$result['ok']) {
            return $this->error((string) $result['error']);
        }

        if ($result['user_id'] !== null && $request->input('purpose', 'verify_mobile') === 'verify_mobile') {
            User::updateById((int) $result['user_id'], ['mobile_verified_at' => now()], true);
        }

        return $this->data(['verified' => true]);
    }

    public function me(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return $this->error('Unauthenticated.', 401);
        }

        return $this->data([
            'user' => $this->presentUser(User::withBusiness((int) $user['id']) ?? $user),
            'roles' => Auth::roles(),
            'permissions' => Auth::permissions(),
            'stats' => User::stats((int) $user['id']),
        ]);
    }

    public function logout(Request $request): Response
    {
        $token = $request->bearerToken();
        if ($token !== null) {
            Database::instance()->statement(
                'UPDATE api_tokens SET revoked_at = :n WHERE token_hash = :h AND revoked_at IS NULL',
                ['n' => now(), 'h' => hash('sha256', $token)]
            );
        }
        return $this->data(['revoked' => true]);
    }

    private function presentUser(array $user): array
    {
        return [
            'id' => (int) ($user['id'] ?? 0),
            'uuid' => $user['uuid'] ?? null,
            'full_name' => $user['full_name'] ?? null,
            'email' => $user['email'] ?? null,
            'mobile' => $user['mobile'] ?? null,
            'account_type' => $user['account_type'] ?? null,
            'status' => $user['status'] ?? null,
            'kyc_status' => $user['kyc_status'] ?? null,
            'mobile_verified' => !empty($user['mobile_verified_at']),
            'avatar' => !empty($user['avatar']) ? upload_url($user['avatar']) : null,
            'business' => !empty($user['business_id']) ? [
                'id' => (int) $user['business_id'],
                'name' => $user['business_name'] ?? null,
                'slug' => $user['business_slug'] ?? null,
                'type' => $user['business_type'] ?? null,
                'city' => $user['city_name'] ?? null,
                'state' => $user['state_name'] ?? null,
                'gstin' => $user['gstin'] ?? null,
                'verified' => (int) ($user['kyc_verified'] ?? 0) === 1,
                'rating' => (float) ($user['rating_avg'] ?? 0),
                'rating_count' => (int) ($user['rating_count'] ?? 0),
                'logo' => !empty($user['business_logo']) ? upload_url($user['business_logo']) : null,
            ] : null,
        ];
    }
}
