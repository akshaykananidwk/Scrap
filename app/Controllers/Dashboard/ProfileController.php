<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Uploader;
use App\Models\Business;
use App\Models\City;
use App\Models\State;
use App\Models\User;
use App\Services\AuditService;

final class ProfileController extends Controller
{
    protected string $layout = 'layouts/dashboard';

    public function edit(Request $request): Response
    {
        return $this->view('dashboard/profile', [
            'title' => 'My profile',
            'user' => $this->user(),
            'business' => Business::forUser($this->userId()),
        ]);
    }

    public function update(Request $request): Response
    {
        $user = $this->user();
        $validator = $this->validate($request, [
            'full_name' => 'required|min:3|max:150',
            'email' => 'nullable|email|max:190|unique:users,email,' . $user['id'],
            'alt_mobile' => 'nullable|mobile',
            'designation' => 'nullable|max:100',
            'account_type' => 'required|in:buyer,seller,both',
            'preferred_language' => 'nullable|in:en,hi,gu',
        ], ['full_name' => 'Full name', 'alt_mobile' => 'Alternate mobile']);

        if ($validator->fails()) {
            return $validator->failResponse($request, '/dashboard/profile');
        }

        $accountType = (string) $request->input('account_type');
        $data = [
            'full_name' => (string) $request->input('full_name'),
            'email' => $request->input('email') ? strtolower((string) $request->input('email')) : null,
            'alt_mobile' => $request->input('alt_mobile') ?: null,
            'designation' => $request->input('designation') ?: null,
            'account_type' => $accountType,
            'preferred_language' => (string) $request->input('preferred_language', 'en'),
        ];

        // Changing the email invalidates its verification.
        if ($data['email'] !== $user['email']) {
            $data['email_verified_at'] = null;
        }

        User::updateById((int) $user['id'], $data);

        if ($accountType !== $user['account_type']) {
            User::syncRoles((int) $user['id'], User::rolesForAccountType($accountType));
        }
        if (!empty($data['preferred_language'])) {
            Session::put('locale', $data['preferred_language']);
        }

        $avatar = $request->file('avatar');
        if ($avatar !== null) {
            $uploader = Uploader::images('avatars', 2);
            $path = $uploader->store($avatar, 400);
            if ($path !== null) {
                Uploader::delete($user['avatar'] ?? null);
                User::updateById((int) $user['id'], ['avatar' => $path], true);
            } else {
                flash('warning', (string) $uploader->firstError());
            }
        }

        Auth::refresh();
        AuditService::logChange('profile_updated', 'user', (int) $user['id'], $user, $data);
        flash('success', 'Profile updated.');
        return $this->redirect('/dashboard/profile');
    }

    public function changePassword(Request $request): Response
    {
        $user = $this->user();

        if (!password_verify((string) $request->raw('current_password', ''), (string) $user['password_hash'])) {
            flash('danger', 'Your current password is not correct.');
            return $this->back('/dashboard/profile');
        }

        $validator = $this->validate($request, ['password' => 'required|password|confirmed']);
        if ($validator->fails()) {
            return $validator->failResponse($request, '/dashboard/profile');
        }

        User::updateById((int) $user['id'], ['password_hash' => Auth::hash((string) $request->raw('password'))], true);

        // Signing out other API sessions is the safe default after a password change.
        Database::instance()->statement(
            'UPDATE api_tokens SET revoked_at = :n WHERE user_id = :u AND revoked_at IS NULL',
            ['n' => now(), 'u' => (int) $user['id']]
        );

        AuditService::log('password_changed', 'user', (int) $user['id']);
        flash('success', 'Password updated. Any API tokens have been revoked.');
        return $this->redirect('/dashboard/profile');
    }

    public function updateNotifications(Request $request): Response
    {
        User::updateById($this->userId(), [
            'notify_email' => $request->bool('notify_email') ? 1 : 0,
            'notify_sms' => $request->bool('notify_sms') ? 1 : 0,
            'notify_whatsapp' => $request->bool('notify_whatsapp') ? 1 : 0,
        ], true);

        Auth::refresh();
        flash('success', 'Notification preferences saved.');
        return $this->back('/dashboard/profile');
    }

    public function business(Request $request): Response
    {
        $business = Business::forUser($this->userId());
        return $this->view('dashboard/business', [
            'title' => 'Business profile',
            'business' => $business,
            'types' => Business::TYPES,
            'states' => State::active(),
            'cities' => $business && $business['state_id'] ? City::forState((int) $business['state_id']) : City::major(30),
        ]);
    }

    public function updateBusiness(Request $request): Response
    {
        $business = Business::forUser($this->userId());
        $validator = $this->validate($request, [
            'name' => 'required|min:2|max:180',
            'business_type' => 'required|in:' . implode(',', array_keys(Business::TYPES)),
            'gstin' => 'nullable|gstin',
            'pan' => 'nullable|pan',
            'contact_mobile' => 'nullable|mobile',
            'contact_email' => 'nullable|email',
            'website' => 'nullable|url',
            'pincode' => 'nullable|pincode',
            'state_id' => 'required|integer|exists:states,id',
            'established_year' => 'nullable|integer|min_value:1900|max_value:' . (int) gmdate('Y'),
            'about' => 'nullable|max:5000',
        ], ['name' => 'Business name', 'gstin' => 'GSTIN', 'pan' => 'PAN']);

        if ($validator->fails()) {
            return $validator->failResponse($request, '/dashboard/business');
        }

        $gstin = $request->input('gstin') ? strtoupper((string) $request->input('gstin')) : null;

        // Guard against two businesses claiming the same GSTIN.
        if ($gstin !== null) {
            $sql = 'SELECT id FROM businesses WHERE gstin = :g AND deleted_at IS NULL';
            $params = ['g' => $gstin];
            if ($business !== null) {
                $sql .= ' AND id <> :id';
                $params['id'] = (int) $business['id'];
            }
            if (Database::instance()->first($sql, $params) !== null) {
                flash('danger', 'That GSTIN is already registered to another business.');
                return $this->back('/dashboard/business');
            }
        }

        $city = $request->int('city_id') ? City::withState($request->int('city_id')) : null;
        $state = State::find($request->int('state_id'));

        $data = [
            'name' => (string) $request->input('name'),
            'business_type' => (string) $request->input('business_type'),
            'about' => $request->input('about') ?: null,
            'established_year' => $request->int('established_year') ?: null,
            'employee_count' => $request->input('employee_count') ?: null,
            'annual_turnover' => $request->input('annual_turnover') ?: null,
            'website' => $request->input('website') ?: null,
            'contact_person' => $request->input('contact_person') ?: null,
            'contact_mobile' => $request->input('contact_mobile') ?: null,
            'contact_email' => $request->input('contact_email') ?: null,
            'gstin' => $gstin,
            'pan' => $request->input('pan') ? strtoupper((string) $request->input('pan')) : null,
            'registration_number' => $request->input('registration_number') ?: null,
            'address_line1' => $request->input('address_line1') ?: null,
            'address_line2' => $request->input('address_line2') ?: null,
            'city_id' => $city['id'] ?? null,
            'state_id' => $state['id'] ?? null,
            'city_name' => $city['name'] ?? null,
            'state_name' => $state['name'] ?? null,
            'pincode' => $request->input('pincode') ?: null,
        ];

        if ($business === null) {
            $data['user_id'] = $this->userId();
            $data['slug'] = Business::uniqueSlug($data['name']);
            $businessId = Business::create($data);
        } else {
            $businessId = (int) $business['id'];
            // Changing verified identity fields resets verification.
            if ($gstin !== ($business['gstin'] ?? null)) {
                $data['gst_verified'] = 0;
            }
            Business::updateById($businessId, $data);
            if (isset($data['gst_verified'])) {
                Database::instance()->update('businesses', ['gst_verified' => 0], ['id' => $businessId]);
            }
        }

        foreach (['logo' => 600, 'cover_image' => 1600] as $field => $width) {
            $file = $request->file($field);
            if ($file === null) {
                continue;
            }
            $uploader = Uploader::images('logos', 3);
            $path = $uploader->store($file, $width);
            if ($path !== null) {
                Uploader::delete($business[$field] ?? null);
                Database::instance()->update('businesses', [$field => $path, 'updated_at' => now()], ['id' => $businessId]);
            } else {
                flash('warning', (string) $uploader->firstError());
            }
        }

        AuditService::log('business_updated', 'business', $businessId);
        flash('success', 'Business profile saved.');
        return $this->redirect('/dashboard/business');
    }

    public function security(Request $request): Response
    {
        $userId = $this->userId();
        return $this->view('dashboard/security', [
            'title' => 'Security & API access',
            'user' => $this->user(),
            'logins' => User::loginHistory($userId, 20),
            'tokens' => Database::instance()->select(
                'SELECT id, name, abilities, last_used_at, expires_at, revoked_at, created_at
                 FROM api_tokens WHERE user_id = :u ORDER BY id DESC LIMIT 25',
                ['u' => $userId]
            ),
            'new_token' => Session::get('_new_api_token'),
        ]);
    }

    public function createToken(Request $request): Response
    {
        $name = trim((string) $request->input('name', 'Mobile app'));
        if ($name === '') {
            $name = 'API token';
        }

        $active = (int) Database::instance()->scalar(
            'SELECT COUNT(*) FROM api_tokens WHERE user_id = :u AND revoked_at IS NULL',
            ['u' => $this->userId()],
            0
        );
        if ($active >= 10) {
            flash('danger', 'You already have 10 active tokens. Revoke one first.');
            return $this->back('/dashboard/security');
        }

        $token = Auth::issueToken($this->userId(), $name, gmdate('Y-m-d H:i:s', strtotime('+1 year')));
        AuditService::log('api_token_created', 'user', $this->userId(), null, ['name' => $name]);

        // Shown exactly once.
        Session::flash('_new_api_token_value', $token);
        $_SESSION['_new_api_token'] = $token;

        flash('success', 'Token created. Copy it now — it will not be shown again.');
        return $this->redirect('/dashboard/security');
    }

    public function revokeToken(Request $request): Response
    {
        $tokenId = $request->paramInt('id');
        $affected = Database::instance()->statement(
            'UPDATE api_tokens SET revoked_at = :n WHERE id = :id AND user_id = :u AND revoked_at IS NULL',
            ['n' => now(), 'id' => $tokenId, 'u' => $this->userId()]
        );

        flash($affected > 0 ? 'success' : 'warning', $affected > 0 ? 'Token revoked.' : 'That token was not found.');
        return $this->back('/dashboard/security');
    }
}
