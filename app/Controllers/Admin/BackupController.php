<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\BackupService;
use App\Services\SettingsService;

final class BackupController extends Controller
{
    protected string $layout = 'layouts/admin';

    public function index(Request $request): Response
    {
        return $this->view('admin/backups', [
            'title' => 'Backups',
            'backups' => BackupService::list(50),
            'usage' => BackupService::diskUsage(),
            'retention' => SettingsService::int('backup_retention_count', 10),
            'auto_enabled' => SettingsService::bool('backup_auto_enabled', false),
            'include_uploads' => SettingsService::bool('backup_include_uploads', false),
            'zip_available' => class_exists(\ZipArchive::class),
        ]);
    }

    public function create(Request $request): Response
    {
        $type = (string) $request->input('type', 'full');
        if (!in_array($type, ['full', 'database', 'files'], true)) {
            return $this->fail('Invalid backup type.');
        }

        $result = BackupService::create($type, 'manual', $this->userId());
        BackupService::prune();

        if ($request->wantsJson()) {
            return $result['ok']
                ? $this->ok($result, 'Backup created: ' . $result['filename'])
                : $this->fail((string) $result['error']);
        }

        flash(
            $result['ok'] ? 'success' : 'danger',
            $result['ok']
                ? 'Backup created: ' . $result['filename'] . ' (' . human_bytes((int) ($result['size'] ?? 0)) . ')'
                : 'Backup failed: ' . ($result['error'] ?? '')
        );
        return $this->redirect('/admin/backups');
    }

    public function download(Request $request): Response
    {
        $backup = BackupService::find($request->paramInt('id'));
        if ($backup === null || !is_file((string) $backup['path'])) {
            throw new HttpException(404, 'That backup file is no longer on disk.');
        }

        \App\Services\AuditService::log('backup_downloaded', 'backup', (int) $backup['id']);
        return Response::download((string) $backup['path'], (string) $backup['filename'], 'application/zip');
    }

    public function restore(Request $request): Response
    {
        $backupId = $request->paramInt('id');

        // Restoring is destructive: require an explicit typed confirmation.
        if (strtoupper((string) $request->input('confirm', '')) !== 'RESTORE') {
            flash('danger', 'Type RESTORE in the confirmation box to proceed.');
            return $this->back('/admin/backups');
        }

        SettingsService::set('maintenance_mode', true, 'boolean', 'security');
        $result = BackupService::restore($backupId, $request->bool('restore_files'));
        SettingsService::set('maintenance_mode', false, 'boolean', 'security');

        flash(
            $result['ok'] ? 'success' : 'danger',
            $result['ok'] ? $result['message'] : 'Restore failed: ' . ($result['error'] ?? '')
        );
        return $this->redirect('/admin/backups');
    }

    public function destroy(Request $request): Response
    {
        $result = BackupService::delete($request->paramInt('id'));
        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : (string) $result['error']);
        return $this->redirect('/admin/backups');
    }
}
