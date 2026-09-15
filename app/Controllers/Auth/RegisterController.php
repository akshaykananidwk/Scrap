<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Uploader;
use App\Models\Business;
use App\Models\City;
use App\Models\State;
use App\Models\User;
use App\Services\AuditService;
use App\Services\FraudService;
use App\Services\InstallService;
use App\Services\NotificationService;
use App\Services\OtpService;
use App\Services\SettingsService;

final class RegisterController extends Controller
{
    protected string $layout = 'layouts/auth';

    public function show(Request $request): Response
    {
        return $this->view('auth/register', [
            'title' => 'Create your business account',
            'meta_description' => 'Register your scrap business free — list material, post requirements, bid in auctions.',
            'business_types' => Business::TYPES,
            'states' => State::active(),
        ]);
    }

    public function register(Request $request): Response
    {
        $validator = $this->validate($request, [
            'full_name' => 'required|min:3|max:150',
            'business_name' => 'required|min:2|max:180',
            'mobile' => 'required|mobile|unique:users,mobile',
            'email' => 'nullable|email|max:190|unique:users,email',
            'password' => 'required|password|confirmed',
            'business_type' => 'required|in:' . implode(',', array_keys(Business::TYPES)),
            'account_type' => 'required|in:buyer,seller,both',
            'gstin' => 'nullable|gstin',
            'pan' => 'nullable|pan',
            'registration_number' => 'nullable|max:60',
            'address_line1' => 'nullable|max:255',
            'city_id' => 'nullable|integer',
            'state_id' => 'required|integer|exists:states,id',
            'pincode' => 'nullable|pincode',
            'terms' => 'required',
        ], [
            'full_name' => 'Full name',
            'business_name' => 'Business name',
            'mobile' => 'Mobile number',
            'gstin' => 'GSTIN',
            'pan' => 'PAN',
            'terms' => 'Terms acceptance',
        ]);

        if ($validator->fails()) {
            return $validator->failResponse($request, '/register');
        }

        $mobile = substr(preg_replace('/\D/', '', (string) $request->input('mobile')) ?? '', -10);
        $email = $request->input('email') ? strtolower((string) $request->input('email')) : null;
        $gstin = $request->input('gstin') ? strtoupper((string) $request->input('gstin')) : null;

        // A GSTIN uniquely identifies a business — do not allow a silent duplicate.
        if ($gstin !== null) {
            $existingGstin = Database::instance()->first(
                'SELECT b.id, u.full_name FROM businesses b INNER JOIN users u ON u.id = b.user_id
                 WHERE b.gstin = :g AND b.deleted_at IS NULL',
                ['g' => $gstin]
            );
            if ($existingGstin !== null) {
                flash('danger', 'That GSTIN is already registered on ScrapX. Contact support if this is your business.');
                \App\Core\Session::flashInput($request->all());
                return $this->redirect('/register');
            }
        }

        $requiresApproval = SettingsService::bool('require_admin_approval', false);
        $requiresMobileVerification = SettingsService::bool('require_mobile_verification', true);

        $db = Database::instance();
        $accountType = (string) $request->input('account_type');
        $city = $request->int('city_id') ? City::withState($request->int('city_id')) : null;
        $state = State::find($request->int('state_id'));

        $userId = $db->transaction(function (Database $db) use ($request, $mobile, $email, $gstin, $accountType, $city, $state, $requiresApproval): int {
            $userId = $db->insert('users', [
                'uuid' => InstallService::uuid(),
                'full_name' => (string) $request->input('full_name'),
                'email' => $email,
                'mobile' => $mobile,
                'password_hash' => Auth::hash((string) $request->raw('password')),
                'account_type' => $accountType,
                'status' => $requiresApproval ? 'pending' : 'active',
                'kyc_status' => 'none',
                'designation' => $request->input('designation') ?: null,
                'preferred_language' => \App\Core\Lang::locale(),
                'created_ip' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (User::rolesForAccountType($accountType) as $roleSlug) {
                User::assignRole($userId, $roleSlug);
            }

            $businessName = (string) $request->input('business_name');
            $db->insert('businesses', [
                'user_id' => $userId,
                'name' => $businessName,
                'slug' => Business::uniqueSlug($businessName),
                'business_type' => (string) $request->input('business_type'),
                'contact_person' => (string) $request->input('full_name'),
                'contact_mobile' => $mobile,
                'contact_email' => $email,
                'gstin' => $gstin,
                'pan' => $request->input('pan') ? strtoupper((string) $request->input('pan')) : null,
                'registration_number' => $request->input('registration_number') ?: null,
                'address_line1' => $request->input('address_line1') ?: null,
                'city_id' => $city['id'] ?? null,
                'state_id' => $state['id'] ?? null,
                'city_name' => $city['name'] ?? null,
                'state_name' => $state['name'] ?? null,
                'pincode' => $request->input('pincode') ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $userId;
        });

        // Optional media, stored after the account exists so failures are non-fatal.
        $this->storeProfileMedia($request, $userId);

        $user = User::find($userId);
        AuditService::log('register', 'user', $userId, null, ['account_type' => $accountType]);
        FraudService::evaluate($userId);

        NotificationService::dispatch($userId, 'welcome', [
            'body' => 'Your ScrapX account is ready. Complete KYC to unlock verified badges and restricted auctions.',
            'link' => '/dashboard',
            'vars' => ['name' => (string) $request->input('full_name')],
        ]);

        if ($requiresApproval) {
            flash('success', 'Registration received. An administrator will review and activate your account shortly.');
            return $this->redirect('/login');
        }

        Auth::login($user);
        User::recordLogin($userId, $mobile, true, $request->ip(), $request->userAgent());

        if ($requiresMobileVerification) {
            $otp = OtpService::send($mobile, 'verify_mobile', 'sms', $userId, $request->ip());
            flash('info', $otp['message']);
            if (!empty($otp['debug_code'])) {
                flash('warning', 'No SMS provider is configured. Your verification code is ' . $otp['debug_code'] . ' (also written to storage/logs).');
            }
            return $this->redirect('/verify/mobile');
        }

        flash('success', 'Welcome to ScrapX. Your account is ready.');
        return $this->redirect('/dashboard');
    }

    private function storeProfileMedia(Request $request, int $userId): void
    {
        $avatar = $request->file('avatar');
        if ($avatar !== null) {
            $path = Uploader::images('avatars', 2)->store($avatar, 400);
            if ($path !== null) {
                User::updateById($userId, ['avatar' => $path], true);
            }
        }

        $logo = $request->file('logo');
        if ($logo !== null) {
            $path = Uploader::images('logos', 2)->store($logo, 600);
            if ($path !== null) {
                Database::instance()->update('businesses', ['logo' => $path, 'updated_at' => now()], ['user_id' => $userId]);
            }
        }
    }
}
