<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\MiddlewarePipeline;
use App\Services\SettingsService;
use Throwable;

final class Kernel
{
    private static ?Router $router = null;

    public static function router(): Router
    {
        return self::$router ??= new Router();
    }

    public static function run(): void
    {
        Config::load();
        self::registerErrorHandlers();
        self::sendSecurityHeaders();
        Session::start();
        Session::rotateFlashBuckets();

        $request = new Request();

        try {
            $response = self::handle($request);
        } catch (HttpException $e) {
            $response = self::renderError($request, $e->getStatusCode(), $e->getMessage());
        } catch (Throwable $e) {
            Logger::instance()->exception($e);
            $message = Config::get('app.debug')
                ? $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()
                : HttpException::defaultMessage(500);
            $response = self::renderError($request, 500, $message);
        }

        $response->send();
    }

    private static function handle(Request $request): Response
    {
        $installed = is_installed();
        $path = $request->path();

        // The installer owns every route until installation completes.
        if (!$installed) {
            require ROOT_PATH . '/routes/install.php';
            if (!str_starts_with($path, '/install')) {
                return Response::redirect('/install');
            }
        } else {
            if (str_starts_with($path, '/install')) {
                throw new HttpException(403, 'The installer is locked. Remove storage/installed.lock to reinstall.');
            }
            require ROOT_PATH . '/routes/web.php';
            require ROOT_PATH . '/routes/api.php';
            Auth::restoreSession();
            Lang::boot();
            self::enforceMaintenanceMode($request);
        }

        $match = self::router()->match($request->method(), $path);
        if ($match === null) {
            throw new HttpException(404);
        }

        $request->setRouteParams($match['params']);
        $route = $match['route'];

        return MiddlewarePipeline::run($request, $route['middleware'], static function (Request $request) use ($route): Response {
            return self::callAction($route['action'], $request);
        });
    }

    private static function callAction(array|callable $action, Request $request): Response
    {
        if (is_callable($action)) {
            $result = $action($request);
        } else {
            [$class, $method] = $action;
            if (!class_exists($class)) {
                throw new HttpException(500, 'Controller not found: ' . $class);
            }
            $controller = new $class();
            if (!method_exists($controller, $method)) {
                throw new HttpException(500, 'Action not found: ' . $class . '@' . $method);
            }
            $result = $controller->$method($request);
        }

        if ($result instanceof Response) {
            return $result;
        }
        if (is_array($result)) {
            return Response::json($result);
        }
        return Response::html((string) $result);
    }

    private static function enforceMaintenanceMode(Request $request): void
    {
        if (str_starts_with($request->path(), '/admin') || str_starts_with($request->path(), '/cron')) {
            return;
        }
        if (!SettingsService::bool('maintenance_mode', false)) {
            return;
        }
        if (Auth::check() && Auth::isStaff()) {
            return;
        }
        $allowed = array_filter(array_map('trim', explode(',', (string) SettingsService::get('maintenance_allowed_ips', ''))));
        if (in_array($request->ip(), $allowed, true)) {
            return;
        }
        throw new HttpException(503, (string) SettingsService::get('maintenance_message', 'Website is temporarily under maintenance.'));
    }

    private static function renderError(Request $request, int $status, string $message): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['success' => false, 'error' => $message, 'status' => $status], $status);
        }
        try {
            $html = View::render('errors/error', [
                'status' => $status,
                'message' => $message,
                'title' => $status . ' — ' . HttpException::defaultMessage($status),
            ], is_installed() ? 'layouts/app' : 'layouts/bare');
            return Response::html($html, $status);
        } catch (Throwable $e) {
            Logger::instance()->exception($e);
            return Response::html(
                '<!doctype html><meta charset="utf-8"><title>Error ' . $status . '</title>'
                . '<div style="font-family:system-ui;padding:3rem;text-align:center">'
                . '<h1>' . $status . '</h1><p>' . e($message) . '</p></div>',
                $status
            );
        }
    }

    private static function sendSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(self), microphone=(), camera=()');
        header_remove('X-Powered-By');

        $csp = "default-src 'self'; "
            . "img-src 'self' data: blob: https:; "
            . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
            . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
            . "font-src 'self' data: https://cdn.jsdelivr.net; "
            . "connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'";
        header('Content-Security-Policy: ' . $csp);

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        if ($https) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    private static function registerErrorHandlers(): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        register_shutdown_function(static function (): void {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                Logger::instance()->error('Fatal: ' . $error['message'], [
                    'file' => $error['file'],
                    'line' => $error['line'],
                ]);
            }
        });
    }
}
