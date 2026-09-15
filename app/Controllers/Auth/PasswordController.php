<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Services\AuditService;
use App\Services\NotificationService;

final class PasswordController extends Controller
{
    protected string $layout = 'layouts/auth';

    public function showForgot(Request $request): Response
    {
        return $this->view('auth/forgot', ['title' => 'Reset your password']);
    }

    public function sendReset(Request $request): Response
    {
        $identifier = trim((string) $request->input('identifier', ''));
        if ($identifier === '') {
            flash('danger', 'Enter the mobile number or email on your account.');
            return $this->redirect('/forgot-password');
        }

        $user = str_contains($identifier, '@')
            ? User::findBy('email', strtolower($identifier))
            : User::findBy('mobile', substr(preg_replace('/\D/', '', $identifier) ?? '', -10));

        // Always answer the same way so this cannot be used to enumerate accounts.
        $genericMessage = 'If an account matches those details, we have sent reset instructions.';

        if ($user !== null && $user['status'] === 'active') {
            $token = str_random(64);
            Database::instance()->insert('password_resets', [
                'user_id' => (int) $user['id'],
                'token_hash' => hash('sha256', $token),
                'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
                'ip' => $request->ip(),
                'created_at' => now(),
            ]);

            NotificationService::dispatch((int) $user['id'], 'welcome', [
                'title' => 'Password reset requested',
                'body' => 'Use the link in your email to set a new password. The link expires in one hour.',
                'link' => '/reset-password/' . $token,
                'channels' => ['email'],
                'vars' => ['link' => base_url('reset-password/' . $token), 'name' => $user['full_name']],
            ]);

            // Also send an OTP so users without email access can still reset.
            \App\Services\OtpService::send((string) $user['mobile'], 'password_reset', 'sms', (int) $user['id'], $request->ip());

            AuditService::log('password_reset_requested', 'user', (int) $user['id']);

            if (!\App\Services\NotificationService::channelEnabled('email')) {
                logger()->warning('Password reset link generated but email is disabled', [
                    'user_id' => (int) $user['id'],
                    'link' => base_url('reset-password/' . $token),
                ]);
            }
        }

        flash('info', $genericMessage);
        return $this->redirect('/forgot-password');
    }

    public function showReset(Request $request): Response
    {
        $token = (string) $request->param('token');
        $reset = $this->findValidToken($token);
        if ($reset === null) {
            throw new HttpException(403, 'This reset link is invalid or has expired. Please request a new one.');
        }

        return $this->view('auth/reset', [
            'title' => 'Set a new password',
            'token' => $token,
        ]);
    }

    public function reset(Request $request): Response
    {
        $token = (string) $request->input('token', '');
        $reset = $this->findValidToken($token);
        if ($reset === null) {
            flash('danger', 'This reset link is invalid or has expired. Please request a new one.');
            return $this->redirect('/forgot-password');
        }

        $validator = $this->validate($request, [
            'password' => 'required|password|confirmed',
        ]);
        if ($validator->fails()) {
            return $validator->failResponse($request, '/reset-password/' . $token);
        }

        $userId = (int) $reset['user_id'];
        User::updateById($userId, ['password_hash' => Auth::hash((string) $request->raw('password'))], true);
        Database::instance()->update('password_resets', ['used_at' => now()], ['id' => (int) $reset['id']]);

        // Invalidate every other outstanding reset and API token for this account.
        Database::instance()->statement(
            'UPDATE password_resets SET used_at = :n WHERE user_id = :u AND used_at IS NULL',
            ['n' => now(), 'u' => $userId]
        );
        Database::instance()->statement(
            'UPDATE api_tokens SET revoked_at = :n WHERE user_id = :u AND revoked_at IS NULL',
            ['n' => now(), 'u' => $userId]
        );

        AuditService::log('password_reset', 'user', $userId);
        NotificationService::dispatch($userId, 'account_status', [
            'title' => 'Your password was changed',
            'body' => 'If this was not you, contact support immediately.',
            'link' => '/dashboard/security',
        ]);

        flash('success', 'Your password has been reset. Please sign in.');
        return $this->redirect('/login');
    }

    private function findValidToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        return Database::instance()->first(
            'SELECT * FROM password_resets WHERE token_hash = :h AND used_at IS NULL AND expires_at > :now LIMIT 1',
            ['h' => hash('sha256', $token), 'now' => now()]
        );
    }
}
