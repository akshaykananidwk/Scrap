<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected string $layout = 'layouts/app';

    protected function view(string $template, array $data = [], ?string $layout = null): Response
    {
        $data['errors'] ??= Session::errors();
        $data['flashes'] ??= Session::flashes();
        return Response::html(View::render($template, $data, $layout ?? $this->layout));
    }

    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function ok(array $data = [], string $message = ''): Response
    {
        return Response::json(array_merge(['success' => true, 'message' => $message], $data));
    }

    protected function fail(string $message, int $status = 422, array $extra = []): Response
    {
        return Response::json(array_merge(['success' => false, 'error' => $message], $extra), $status);
    }

    protected function redirect(string $to): Response
    {
        return Response::redirect($to);
    }

    protected function back(string $fallback = '/'): Response
    {
        return Response::redirect($_SERVER['HTTP_REFERER'] ?? base_url($fallback));
    }

    protected function validate(Request $request, array $rules, array $labels = []): Validator
    {
        return Validator::make($request->all(), $rules, $labels);
    }

    /** Throw 403 unless the condition holds — used for ownership checks (IDOR defence). */
    protected function authorize(bool $condition, string $message = 'You are not allowed to do that.'): void
    {
        if (!$condition) {
            throw new HttpException(403, $message);
        }
    }

    protected function user(): array
    {
        $user = Auth::user();
        if ($user === null) {
            throw new HttpException(401);
        }
        return $user;
    }

    protected function userId(): int
    {
        return (int) $this->user()['id'];
    }
}
