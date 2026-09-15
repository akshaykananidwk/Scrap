<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\User;
use App\Services\AuditService;
use App\Services\SettingsService;

final class LoginController extends Controller
{
    protected string $layout = 'layouts/auth';

    public function show(Request $request): Response
    {
        return $this->view('auth/login', [
            'title' => 'Sign in',
            'meta_description' => 'Sign in to your ScrapX account to buy, sell, bid and manage orders.',
        ]);
    }

    public function login(Request $request): Response
    {
        $identifier = trim((string) $request->input('identifier', ''));
        $password = (string) $request->raw('password', '');

        if ($identifier === '' || $password === '') {
            return $this->failed($request, 'Enter your mobile number or email and your password.');
        }

        // Throttle per identifier+IP so one attacker cannot lock out everyone.
        $maxAttempts = SettingsService::int('login_max_attempts', 5);
        $decay = SettingsService::int('login_decay_seconds', 900);
        $key = 'login:' . strtolower($identifier) . ':' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, $maxAttempts, $decay)) {
            $minutes = (int) ceil($decay / 60);
            User::recordLogin(null, $identifier, false, $request->ip(), $request->userAgent());
            return $this->failed(
                $request,
                "Too many failed attempts. Please wait {$minutes} minutes or reset your password.",
                429
            );
        }

        $user = Auth::attempt($identifier, $password);

        if ($user === null) {
            RateLimiter::hit($key, $decay);
            $remaining = max(0, $maxAttempts - RateLimiter::attempts($key, $decay));
            User::recordLogin(null, $identifier, false, $request->ip(), $request->userAgent());
            return $this->failed(
                $request,
                'Those details did not match our records.' . ($remaining > 0 && $remaining <= 2 ? " {$remaining} attempt(s) left." : '')
            );
        }

        // Account state gates access with an explanation rather than a silent failure.
        $stateMessage = match ($user['status']) {
            'pending' => 'Your account is awaiting approval. We will notify you once it is active.',
            'suspended' => 'Your account is suspended. Contact support for help.',
            'blocked' => 'Your account has been blocked. Contact support for help.',
            'rejected' => 'Your registration was not approved. ' . ($user['rejection_reason'] ?: 'Contact support for details.'),
            default => null,
        };
        if ($stateMessage !== null) {
            User::recordLogin((int) $user['id'], $identifier, false, $request->ip(), $request->userAgent());
            return $this->failed($request, $stateMessage, 403);
        }

        // Upgrade the hash if the cost factor changed since they last signed in.
        if (Auth::needsRehash((string) $user['password_hash'])) {
            User::updateById((int) $user['id'], ['password_hash' => Auth::hash($password)], true);
        }

        RateLimiter::clear($key, $decay);
        Auth::login($user);
        User::recordLogin((int) $user['id'], $identifier, true, $request->ip(), $request->userAgent());
        User::updateById((int) $user['id'], ['last_active_at' => now()], true);
        AuditService::log('login', 'user', (int) $user['id']);

        $intended = Session::get('intended_url');
        Session::forget('intended_url');
        $destination = $intended ?: (Auth::isStaff() ? '/admin' : '/dashboard');

        if ($request->wantsJson()) {
            return $this->ok(['redirect' => $destination], 'Signed in.');
        }

        flash('success', 'Welcome back, ' . $user['full_name'] . '.');
        return $this->redirect($destination);
    }

    public function logout(Request $request): Response
    {
        Auth::logout();
        flash('success', 'You have been signed out.');
        return $this->redirect('/');
    }

    private function failed(Request $request, string $message, int $status = 422): Response
    {
        if ($request->wantsJson()) {
            return $this->fail($message, $status);
        }
        Session::flashInput($request->all());
        flash('danger', $message);
        return $this->redirect('/login');
    }
}
