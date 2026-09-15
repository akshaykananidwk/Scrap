<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Migrator;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\BackupService;
use App\Services\CronService;
use App\Services\HealthService;
use App\Services\SettingsService;

final class SystemController extends Controller
{
    protected string $layout = 'layouts/admin';

    public function audit(Request $request): Response
    {
        $filters = array_filter([
            'user_id' => $request->int('user_id'),
            'action' => (string) $request->query('action', ''),
            'entity_type' => (string) $request->query('entity_type', ''),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
        ], static fn ($v): bool => $v !== '' && $v !== 0);

        return $this->view('admin/audit', [
            'title' => 'Audit log',
            'logs' => AuditService::paginate($filters, $request->page(), 50),
            'filters' => $filters,
            'actions' => AuditService::actions(),
        ]);
    }

    public function logs(Request $request): Response
    {
        $logger = Logger::instance();
        $name = (string) $request->query('file', '');
        $files = $logger->files();

        if ($name === '' && $files !== []) {
            $name = (string) $files[0]['name'];
        }

        return $this->view('admin/logs', [
            'title' => 'Error logs',
            'files' => $files,
            'current' => $name,
            'contents' => $name !== '' ? $logger->read($name) : '',
        ]);
    }

    public function deleteLog(Request $request): Response
    {
        $name = (string) $request->param('name');
        $deleted = Logger::instance()->purge($name);

        flash($deleted ? 'success' : 'danger', $deleted ? 'Log file deleted.' : 'That log file could not be deleted.');
        return $this->redirect('/admin/logs');
    }

    public function health(Request $request): Response
    {
        $report = HealthService::run();
        $db = Database::instance();
        $migrator = new Migrator($db);

        return $this->view('admin/health', [
            'title' => 'System health',
            'report' => $report,
            'environment' => [
                'php_version' => PHP_VERSION,
                'php_sapi' => PHP_SAPI,
                'app_version' => \App\Services\InstallService::readVersion(),
                'schema_version' => $migrator->currentVersion(),
                'pending_migrations' => array_keys($migrator->pending()),
                'database' => (string) Config::get('database.name', ''),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
                'memory_limit' => ini_get('memory_limit'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_execution_time' => ini_get('max_execution_time'),
                'timezone_display' => (string) Config::get('app.timezone', 'Asia/Kolkata'),
                'server_time_utc' => now(),
                'server_time_local' => fmt_dt(now()),
                'disk_free' => @disk_free_space(ROOT_PATH) !== false ? human_bytes((float) @disk_free_space(ROOT_PATH)) : 'unknown',
                'extensions' => array_values(array_filter(
                    ['pdo_mysql', 'curl', 'gd', 'zip', 'mbstring', 'openssl', 'fileinfo', 'intl', 'exif'],
                    'extension_loaded'
                )),
            ],
            'table_count' => count($db->tables()),
            'backups' => BackupService::diskUsage(),
            'cron' => CronService::isHealthy(),
        ]);
    }

    public function cron(Request $request): Response
    {
        return $this->view('admin/cron', [
            'title' => 'Scheduler',
            'jobs' => CronService::status(),
            'runs' => CronService::recentRuns(40),
            'health' => CronService::isHealthy(),
            'web_enabled' => SettingsService::bool('cron_web_fallback_enabled', true),
            'cron_url' => base_url('cron/run?key=' . SettingsService::get('cron_secret', '')),
            'cli_command' => '* * * * * php ' . ROOT_PATH . '/cli.php cron >> /dev/null 2>&1',
            'last_run' => SettingsService::get('cron_last_run_at', ''),
        ]);
    }

    public function runJob(Request $request): Response
    {
        $job = (string) $request->param('job');
        $result = CronService::runJob($job, 'manual');

        if ($request->wantsJson()) {
            return $this->json(['success' => $result['status'] === 'success', 'result' => $result]);
        }

        flash(
            $result['status'] === 'success' ? 'success' : 'danger',
            label($job) . ': ' . $result['message'] . ' (' . $result['duration_ms'] . 'ms)'
        );
        return $this->back('/admin/cron');
    }
}
