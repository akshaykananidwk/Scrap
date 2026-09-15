<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\InstallService;

/**
 * The installation wizard.
 *
 * CSRF is not enforced on these routes (no session store exists yet on a fresh
 * upload, and there is no authenticated user to impersonate). The installer is
 * reachable ONLY while storage/installed.lock is absent — once installation
 * finishes, Kernel returns 403 for anything under /install.
 */
final class InstallController extends Controller
{
    protected string $layout = 'layouts/install';

    public function welcome(Request $request): Response
    {
        return $this->view('install/welcome', [
            'title' => 'Install ScrapX',
            'step' => 0,
            'php_version' => PHP_VERSION,
        ]);
    }

    public function requirements(Request $request): Response
    {
        $checks = InstallService::requirements();
        $passed = InstallService::requirementsPassed();

        return $this->view('install/requirements', [
            'title' => 'System requirements',
            'step' => 1,
            'checks' => $checks,
            'passed' => $passed,
        ]);
    }

    public function database(Request $request): Response
    {
        if (!InstallService::requirementsPassed()) {
            flash('danger', 'Please fix the failed requirements before continuing.');
            return $this->redirect('/install/requirements');
        }

        return $this->view('install/database', [
            'title' => 'Database connection',
            'step' => 2,
            'saved' => Session::get('install_db', []),
        ]);
    }

    /** AJAX "Test connection" button. */
    public function testDatabase(Request $request): Response
    {
        $credentials = $this->databaseInput($request);
        if ($credentials['name'] === '' || $credentials['user'] === '') {
            return $this->fail('Database name and username are required.');
        }

        $result = InstallService::testDatabase($credentials);
        if (!$result['ok']) {
            return $this->fail($result['message']);
        }

        return $this->ok(
            ['server_version' => $result['server_version'] ?? ''],
            'Connected successfully to ' . $credentials['name'] . ' (' . ($result['server_version'] ?? '') . ').'
        );
    }

    public function saveDatabase(Request $request): Response
    {
        $credentials = $this->databaseInput($request);
        $result = InstallService::testDatabase($credentials);

        if (!$result['ok']) {
            Session::flashInput($request->all());
            flash('danger', $result['message']);
            return $this->redirect('/install/database');
        }

        Session::put('install_db', $credentials);
        return $this->redirect('/install/application');
    }

    public function application(Request $request): Response
    {
        if (Session::get('install_db') === null) {
            flash('warning', 'Enter your database details first.');
            return $this->redirect('/install/database');
        }

        return $this->view('install/application', [
            'title' => 'Application settings',
            'step' => 3,
            'suggested_url' => $this->guessBaseUrl($request),
            'saved' => Session::get('install_app', []),
        ]);
    }

    public function saveApplication(Request $request): Response
    {
        $validator = $this->validate($request, [
            'site_name' => 'required|min:2|max:100',
            'site_url' => 'required|url',
            'admin_name' => 'required|min:3|max:150',
            'admin_email' => 'required|email',
            'admin_mobile' => 'required|mobile',
            'admin_password' => 'required|password|confirmed',
        ], [
            'site_name' => 'Site name',
            'site_url' => 'Site URL',
            'admin_name' => 'Administrator name',
            'admin_email' => 'Administrator email',
            'admin_mobile' => 'Administrator mobile',
            'admin_password' => 'Administrator password',
        ]);

        if ($validator->fails()) {
            Session::flashErrors($validator->errors());
            Session::flashInput($request->all());
            flash('danger', (string) $validator->firstError());
            return $this->redirect('/install/application');
        }

        Session::put('install_app', [
            'site_name' => (string) $request->input('site_name'),
            'site_url' => rtrim((string) $request->input('site_url'), '/'),
            'admin_name' => (string) $request->input('admin_name'),
            'admin_email' => strtolower((string) $request->input('admin_email')),
            'admin_mobile' => (string) $request->input('admin_mobile'),
            'admin_password' => (string) $request->raw('admin_password'),
            'demo_data' => $request->bool('demo_data'),
        ]);

        return $this->redirect('/install/run');
    }

    /** Step 4: create the schema, seed, and lock the installer. */
    public function runInstall(Request $request): Response
    {
        $database = Session::get('install_db');
        $app = Session::get('install_app');

        if ($database === null || $app === null) {
            flash('warning', 'Installation details are incomplete. Please start again.');
            return $this->redirect('/install/database');
        }

        // GET renders the progress page; the POST does the work.
        if ($request->method() === 'GET' && !$request->wantsJson()) {
            return $this->view('install/run', [
                'title' => 'Installing',
                'step' => 4,
                'site_name' => $app['site_name'],
                'demo_data' => !empty($app['demo_data']),
            ]);
        }

        @set_time_limit(600);
        $result = InstallService::install($database, $app);

        if ($result['ok']) {
            Session::put('install_result', [
                'steps' => $result['steps'],
                'site_url' => $app['site_url'],
                'admin_email' => $app['admin_email'],
                'demo_data' => !empty($app['demo_data']),
            ]);
            Session::forget('install_db');
            Session::forget('install_app');
        }

        if ($request->wantsJson()) {
            return $this->json([
                'success' => $result['ok'],
                'steps' => $result['steps'],
                'error' => $result['error'] ?? null,
                'redirect' => $result['ok'] ? base_url('install/complete') : null,
            ], $result['ok'] ? 200 : 500);
        }

        if (!$result['ok']) {
            flash('danger', 'Installation failed: ' . ($result['error'] ?? 'unknown error'));
            return $this->view('install/run', [
                'title' => 'Installation failed',
                'step' => 4,
                'steps' => $result['steps'],
                'error' => $result['error'] ?? null,
                'site_name' => $app['site_name'],
            ]);
        }

        return $this->redirect('/install/complete');
    }

    public function complete(Request $request): Response
    {
        $result = Session::get('install_result');
        if ($result === null && !is_installed()) {
            return $this->redirect('/install');
        }
        Session::forget('install_result');

        return $this->view('install/complete', [
            'title' => 'Installation complete',
            'step' => 5,
            'steps' => $result['steps'] ?? [],
            'site_url' => $result['site_url'] ?? base_url('/'),
            'admin_email' => $result['admin_email'] ?? '',
            'demo_data' => $result['demo_data'] ?? false,
            'cron_command' => 'php ' . ROOT_PATH . '/cli.php cron',
        ]);
    }

    private function databaseInput(Request $request): array
    {
        return [
            'host' => trim((string) $request->input('db_host', '127.0.0.1')) ?: '127.0.0.1',
            'port' => trim((string) $request->input('db_port', '3306')) ?: '3306',
            'name' => trim((string) $request->input('db_name', '')),
            'user' => trim((string) $request->input('db_user', '')),
            'pass' => (string) $request->raw('db_pass', ''),
            'charset' => 'utf8mb4',
        ];
    }

    private function guessBaseUrl(Request $request): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        return $scheme . '://' . $host . ($scriptDir !== '/' ? $scriptDir : '');
    }
}
