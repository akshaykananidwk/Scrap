<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\SettingsService;
use App\Services\Updates\GitHubClient;
use App\Services\Updates\UpdateService;

/**
 * Admin → System → Updates.
 *
 * The GitHub token is write-only from the UI: it is stored encrypted and never
 * rendered back — the form shows only whether one is saved.
 */
final class UpdateController extends Controller
{
    protected string $layout = 'layouts/admin';

    public function index(Request $request): Response
    {
        $tokenSaved = (string) SettingsService::get('github_token', '') !== '';

        return $this->view('admin/updates', [
            'title' => 'System updates',
            'configured' => UpdateService::isConfigured(),
            'token_saved' => $tokenSaved,
            'settings' => [
                'owner' => (string) SettingsService::get('github_owner', ''),
                'repo' => (string) SettingsService::get('github_repo', ''),
                'branch' => (string) SettingsService::get('github_branch', 'main'),
                'use_releases' => SettingsService::bool('github_use_releases', false),
                'auto_check' => SettingsService::bool('update_auto_check', true),
                'backup_before' => SettingsService::bool('update_backup_before', true),
            ],
            'protected_paths' => UpdateService::protectedPaths(),
            'custom_protected' => SettingsService::json('update_protected_paths', []),
            'current_version' => UpdateService::currentVersion(),
            'current_commit' => UpdateService::currentCommit(),
            'manifest' => UpdateService::localManifest(),
            'last_check' => SettingsService::get('update_last_check_at', ''),
            'latest_version' => SettingsService::get('update_latest_version', ''),
            'history' => UpdateService::history(20),
            'check' => null,
            'zip_available' => class_exists(\ZipArchive::class),
            'curl_available' => function_exists('curl_init'),
            'root_writable' => is_writable(ROOT_PATH),
        ]);
    }

    public function saveSettings(Request $request): Response
    {
        $owner = trim((string) $request->input('github_owner', ''));
        $repo = trim((string) $request->input('github_repo', ''));
        $branch = trim((string) $request->input('github_branch', 'main')) ?: 'main';

        if ($owner === '' || $repo === '') {
            return $this->fail('GitHub owner and repository are both required.');
        }
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $owner) || !preg_match('/^[A-Za-z0-9._-]+$/', $repo)) {
            return $this->fail('Owner and repository may only contain letters, numbers, dots, dashes and underscores.');
        }

        SettingsService::set('github_owner', $owner, 'string', 'updates');
        SettingsService::set('github_repo', $repo, 'string', 'updates');
        SettingsService::set('github_branch', $branch, 'string', 'updates');
        SettingsService::set('github_use_releases', $request->bool('github_use_releases'), 'boolean', 'updates');
        SettingsService::set('update_auto_check', $request->bool('update_auto_check'), 'boolean', 'updates');
        SettingsService::set('update_backup_before', $request->bool('update_backup_before'), 'boolean', 'updates');

        // A blank token field means "keep whatever is already stored".
        $token = (string) $request->raw('github_token', '');
        if ($token !== '' && $token !== '__SET__') {
            SettingsService::set('github_token', $token, 'secret', 'updates');
        }
        if ($request->bool('clear_token')) {
            SettingsService::set('github_token', '', 'secret', 'updates');
        }

        // Custom protected paths, one per line.
        $custom = array_values(array_filter(array_map(
            static fn (string $line): string => trim(str_replace('\\', '/', $line), "/ \t"),
            explode("\n", (string) $request->input('protected_paths', ''))
        )));
        SettingsService::set('update_protected_paths', $custom, 'json', 'updates');

        AuditService::log('update_settings_saved', 'settings', null, null, [
            'owner' => $owner,
            'repo' => $repo,
            'branch' => $branch,
            'token' => $token !== '' ? '[set]' : '[unchanged]',
        ]);

        flash('success', 'Update settings saved. The token is stored encrypted and is never displayed again.');
        return $this->redirect('/admin/updates');
    }

    public function testConnection(Request $request): Response
    {
        $client = GitHubClient::fromSettings();
        if ($client === null) {
            return $this->fail('Save the repository details first.');
        }

        $result = $client->testConnection();
        if ($request->wantsJson()) {
            return $result['ok']
                ? $this->ok($result, (string) $result['message'])
                : $this->fail((string) $result['error']);
        }

        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? (string) $result['message'] : (string) $result['error']);
        return $this->back('/admin/updates');
    }

    public function check(Request $request): Response
    {
        $result = UpdateService::check();

        if ($request->wantsJson()) {
            return $result['ok'] ? $this->ok($result) : $this->fail((string) $result['error']);
        }

        if (!$result['ok']) {
            flash('danger', (string) $result['error']);
            return $this->back('/admin/updates');
        }

        // Re-render the page with the check result attached.
        $response = $this->index($request);
        $view = \App\Core\View::render('admin/updates', array_merge([
            'title' => 'System updates',
            'configured' => true,
            'token_saved' => (string) SettingsService::get('github_token', '') !== '',
            'settings' => [
                'owner' => (string) SettingsService::get('github_owner', ''),
                'repo' => (string) SettingsService::get('github_repo', ''),
                'branch' => (string) SettingsService::get('github_branch', 'main'),
                'use_releases' => SettingsService::bool('github_use_releases', false),
                'auto_check' => SettingsService::bool('update_auto_check', true),
                'backup_before' => SettingsService::bool('update_backup_before', true),
            ],
            'protected_paths' => UpdateService::protectedPaths(),
            'custom_protected' => SettingsService::json('update_protected_paths', []),
            'current_version' => UpdateService::currentVersion(),
            'current_commit' => UpdateService::currentCommit(),
            'manifest' => UpdateService::localManifest(),
            'last_check' => SettingsService::get('update_last_check_at', ''),
            'latest_version' => SettingsService::get('update_latest_version', ''),
            'history' => UpdateService::history(20),
            'zip_available' => class_exists(\ZipArchive::class),
            'curl_available' => function_exists('curl_init'),
            'root_writable' => is_writable(ROOT_PATH),
            'check' => $result,
            'flashes' => \App\Core\Session::flashes(),
            'errors' => [],
        ]), 'layouts/admin');

        return Response::html($view);
    }

    public function apply(Request $request): Response
    {
        // Typed confirmation: this replaces application files.
        if (strtoupper((string) $request->input('confirm', '')) !== 'UPDATE') {
            $message = 'Type UPDATE in the confirmation box to start the update.';
            if ($request->wantsJson()) {
                return $this->fail($message);
            }
            flash('danger', $message);
            return $this->back('/admin/updates');
        }

        $result = UpdateService::apply($this->userId());

        if ($request->wantsJson()) {
            return $this->json([
                'success' => $result['ok'],
                'update_id' => $result['update_id'] ?? null,
                'steps' => $result['steps'],
                'error' => $result['error'] ?? null,
                'rolled_back' => $result['rolled_back'] ?? false,
                'rollback_message' => $result['rollback_message'] ?? null,
            ], $result['ok'] ? 200 : 500);
        }

        if ($result['ok']) {
            flash('success', sprintf(
                'Updated from %s to %s — %d files added, %d updated, %d protected. %d migration(s) applied.',
                $result['from_version'],
                $result['to_version'],
                $result['files']['added'],
                $result['files']['updated'],
                $result['files']['skipped'],
                count($result['migrations'])
            ));
        } else {
            flash('danger', 'UPDATE FAILED: ' . $result['error']
                . ($result['rolled_back'] ?? false
                    ? ' — ROLLBACK COMPLETED, the previous version has been restored.'
                    : ' — automatic rollback could not run: ' . ($result['rollback_message'] ?? 'no backup available.')));
        }

        return $this->redirect($result['update_id'] !== null ? '/admin/updates/' . $result['update_id'] : '/admin/updates');
    }

    public function show(Request $request): Response
    {
        $update = UpdateService::detail($request->paramInt('id'));
        if ($update === null) {
            throw new HttpException(404, 'Update record not found.');
        }

        $byAction = [];
        foreach ($update['files'] as $file) {
            $byAction[(string) $file['action']][] = $file;
        }

        return $this->view('admin/update_show', [
            'title' => 'Update ' . $update['from_version'] . ' → ' . $update['to_version'],
            'update' => $update,
            'files_by_action' => $byAction,
        ]);
    }

    public function rollback(Request $request): Response
    {
        if (strtoupper((string) $request->input('confirm', '')) !== 'ROLLBACK') {
            flash('danger', 'Type ROLLBACK in the confirmation box to proceed.');
            return $this->back();
        }

        $result = UpdateService::rollbackUpdate($request->paramInt('id'));
        flash($result['ok'] ? 'success' : 'danger', $result['message']);
        return $this->redirect('/admin/updates');
    }

    public function clearCache(Request $request): Response
    {
        $cleared = UpdateService::clearCache();
        AuditService::log('cache_cleared', 'system');

        if ($request->wantsJson()) {
            return $this->ok(['cleared' => $cleared], $cleared . ' cached item(s) cleared.');
        }
        flash('success', $cleared . ' cached item(s) cleared.');
        return $this->back('/admin/updates');
    }
}
