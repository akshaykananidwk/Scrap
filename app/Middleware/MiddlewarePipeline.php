<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\SettingsService;

/**
 * Middleware is referenced by string name in route definitions:
 *   'auth', 'guest', 'csrf', 'admin', 'role:seller', 'permission:manage_users',
 *   'throttle:30,60', 'verified', 'api'
 */
final class MiddlewarePipeline
{
    public static function run(Request $request, array $middleware, callable $destination): Response
    {
        // CSRF is applied to every state-changing request that is not API-token based.
        $method = $request->realMethod();
        $isApi = str_starts_with($request->path(), '/api/');
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && !$isApi && !in_array('no_csrf', $middleware, true)) {
            $token = $request->raw('_token') ?? $request->header('X-CSRF-Token');
            if (!Csrf::verify(is_string($token) ? $token : null)) {
                throw new HttpException(419, 'Your session expired or the form token was invalid. Please try again.');
            }
        }

        foreach ($middleware as $definition) {
            $response = self::apply($definition, $request);
            if ($response instanceof Response) {
                return $response;
            }
        }

        return $destination($request);
    }

    private static function apply(string $definition, Request $request): ?Response
    {
        [$name, $param] = array_pad(explode(':', $definition, 2), 2, null);

        return match ($name) {
            'auth' => self::auth($request),
            'guest' => self::guest(),
            'admin' => self::admin($request),
            'role' => self::role($request, (string) $param),
            'permission' => self::permission($request, (string) $param),
            'account' => self::accountType($request, (string) $param),
            'verified' => self::verified($request),
            'kyc' => self::kyc($request),
            'throttle' => self::throttle($request, (string) $param),
            'api' => self::api($request),
            'no_csrf' => null,
            default => null,
        };
    }

    private static function auth(Request $request): ?Response
    {
        if (Auth::check()) {
            return null;
        }
        if ($request->wantsJson()) {
            throw new HttpException(401, 'Please sign in to continue.');
        }
        Session::put('intended_url', $request->fullUrl());
        flash('warning', 'Please sign in to continue.');
        return Response::redirect('/login');
    }

    private static function guest(): ?Response
    {
        return Auth::check() ? Response::redirect('/dashboard') : null;
    }

    private static function admin(Request $request): ?Response
    {
        $response = self::auth($request);
        if ($response !== null) {
            return $response;
        }
        if (!Auth::isStaff()) {
            throw new HttpException(403, 'Administrator access is required.');
        }
        return null;
    }

    private static function role(Request $request, string $roles): ?Response
    {
        $response = self::auth($request);
        if ($response !== null) {
            return $response;
        }
        if (!Auth::hasRole(...array_filter(explode(',', $roles)))) {
            throw new HttpException(403, 'Your account role does not allow this action.');
        }
        return null;
    }

    private static function permission(Request $request, string $permission): ?Response
    {
        $response = self::auth($request);
        if ($response !== null) {
            return $response;
        }
        foreach (explode(',', $permission) as $candidate) {
            if (Auth::can(trim($candidate))) {
                return null;
            }
        }
        throw new HttpException(403, 'You do not have permission for this action.');
    }

    private static function accountType(Request $request, string $types): ?Response
    {
        $response = self::auth($request);
        if ($response !== null) {
            return $response;
        }
        $user = Auth::user() ?? [];
        $allowed = array_filter(explode(',', $types));
        $accountType = (string) ($user['account_type'] ?? '');
        if ($accountType === 'both' || in_array($accountType, $allowed, true)) {
            return null;
        }
        throw new HttpException(403, 'Switch your account type in profile settings to use this feature.');
    }

    private static function verified(Request $request): ?Response
    {
        $response = self::auth($request);
        if ($response !== null) {
            return $response;
        }
        $user = Auth::user() ?? [];
        if (!empty($user['mobile_verified_at'])) {
            return null;
        }
        if (!SettingsService::bool('require_mobile_verification', true)) {
            return null;
        }
        if ($request->wantsJson()) {
            throw new HttpException(403, 'Please verify your mobile number first.');
        }
        flash('warning', 'Please verify your mobile number to continue.');
        return Response::redirect('/verify/mobile');
    }

    private static function kyc(Request $request): ?Response
    {
        $response = self::auth($request);
        if ($response !== null) {
            return $response;
        }
        if (!SettingsService::bool('require_kyc_for_trading', false)) {
            return null;
        }
        $user = Auth::user() ?? [];
        if (($user['kyc_status'] ?? '') === 'verified') {
            return null;
        }
        if ($request->wantsJson()) {
            throw new HttpException(403, 'KYC verification is required for this action.');
        }
        flash('warning', 'KYC verification is required before trading.');
        return Response::redirect('/dashboard/kyc');
    }

    private static function throttle(Request $request, string $param): ?Response
    {
        [$max, $decay] = array_pad(explode(',', $param), 2, '60');
        $key = 'route:' . $request->path() . ':' . $request->ip() . ':' . (Auth::id() ?? 0);
        RateLimiter::enforce($key, max(1, (int) $max), max(1, (int) $decay));
        return null;
    }

    private static function api(Request $request): ?Response
    {
        if (Auth::check()) {
            return null;
        }
        $token = $request->bearerToken();
        if ($token !== null && Auth::authenticateToken($token)) {
            return null;
        }
        throw new HttpException(401, 'A valid API token is required.');
    }
}
