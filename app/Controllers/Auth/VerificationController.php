<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Services\OtpService;

final class VerificationController extends Controller
{
    protected string $layout = 'layouts/auth';

    public function show(Request $request): Response
    {
        $user = $this->user();
        if (!empty($user['mobile_verified_at'])) {
            flash('success', 'Your mobile number is already verified.');
            return $this->redirect('/dashboard');
        }

        return $this->view('auth/verify', [
            'title' => 'Verify your mobile number',
            'user' => $user,
            'masked' => OtpService::maskDestination((string) $user['mobile'], 'sms'),
        ]);
    }

    public function send(Request $request): Response
    {
        $user = $this->user();
        $channel = $request->input('channel') === 'email' ? 'email' : 'sms';
        $destination = $channel === 'email' ? (string) ($user['email'] ?? '') : (string) $user['mobile'];

        if ($destination === '') {
            return $this->respond($request, false, 'Add an email address to your profile first.');
        }

        $purpose = $channel === 'email' ? 'verify_email' : 'verify_mobile';
        $result = OtpService::send($destination, $purpose, $channel, (int) $user['id'], $request->ip());

        if (!$result['ok']) {
            return $this->respond($request, false, (string) $result['error']);
        }

        if ($request->wantsJson()) {
            return $this->ok(
                array_filter(['debug_code' => $result['debug_code'] ?? null]),
                $result['message']
            );
        }

        flash('info', $result['message']);
        if (!empty($result['debug_code'])) {
            flash('warning', 'No provider configured — your code is ' . $result['debug_code'] . '.');
        }
        return $this->back('/verify/mobile');
    }

    public function confirm(Request $request): Response
    {
        $user = $this->user();
        $channel = $request->input('channel') === 'email' ? 'email' : 'sms';
        $destination = $channel === 'email' ? (string) ($user['email'] ?? '') : (string) $user['mobile'];
        $purpose = $channel === 'email' ? 'verify_email' : 'verify_mobile';

        $result = OtpService::verify($destination, (string) $request->input('code', ''), $purpose);
        if (!$result['ok']) {
            return $this->respond($request, false, (string) $result['error']);
        }

        $column = $channel === 'email' ? 'email_verified_at' : 'mobile_verified_at';
        User::updateById((int) $user['id'], [$column => now()], true);
        Auth::refresh();
        \App\Services\AuditService::log('verified_' . $channel, 'user', (int) $user['id']);

        $message = $channel === 'email'
            ? 'Email address verified.'
            : 'Mobile number verified. You can now bid, make offers and trade.';

        if ($request->wantsJson()) {
            return $this->ok(['redirect' => '/dashboard'], $message);
        }
        flash('success', $message);
        return $this->redirect('/dashboard');
    }

    private function respond(Request $request, bool $ok, string $message): Response
    {
        if ($request->wantsJson()) {
            return $ok ? $this->ok([], $message) : $this->fail($message);
        }
        flash($ok ? 'success' : 'danger', $message);
        return $this->back('/verify/mobile');
    }
}
