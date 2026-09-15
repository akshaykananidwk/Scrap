<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Uploader;
use App\Services\ExportService;
use App\Services\ImportService;
use Database\Seeders\DemoSeeder;

final class DataController extends Controller
{
    protected string $layout = 'layouts/admin';

    public function index(Request $request): Response
    {
        $counts = [];
        foreach (array_keys(ExportService::DATASETS) as $dataset) {
            $counts[$dataset] = ExportService::rowCount($dataset);
        }

        return $this->view('admin/data', [
            'title' => 'Import & export',
            'datasets' => ExportService::DATASETS,
            'counts' => $counts,
            'import_types' => ImportService::TYPES,
            'demo_counts' => [
                'users' => (int) Database::instance()->scalar('SELECT COUNT(*) FROM users WHERE is_demo = 1', [], 0),
                'listings' => (int) Database::instance()->scalar('SELECT COUNT(*) FROM listings WHERE is_demo = 1', [], 0),
                'auctions' => (int) Database::instance()->scalar('SELECT COUNT(*) FROM auctions WHERE is_demo = 1', [], 0),
                'requirements' => (int) Database::instance()->scalar('SELECT COUNT(*) FROM wanted_requirements WHERE is_demo = 1', [], 0),
            ],
            'result' => \App\Core\Session::get('_import_result'),
        ]);
    }

    public function export(Request $request): Response
    {
        $dataset = (string) $request->param('dataset');
        if (!isset(ExportService::DATASETS[$dataset])) {
            return $this->fail('Unknown dataset.', 404);
        }

        return ExportService::csv($dataset, [
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
        ]);
    }

    public function import(Request $request): Response
    {
        $type = (string) $request->input('type', '');
        if (!isset(ImportService::TYPES[$type])) {
            flash('danger', 'Choose a valid import type.');
            return $this->back('/admin/export');
        }

        $file = $request->file('file');
        if ($file === null) {
            flash('danger', 'Select a CSV file to import.');
            return $this->back('/admin/export');
        }

        $uploader = Uploader::csv('imports');
        $path = $uploader->store($file, null);
        if ($path === null) {
            flash('danger', (string) $uploader->firstError());
            return $this->back('/admin/export');
        }

        $dryRun = $request->bool('dry_run');
        $result = ImportService::import($type, UPLOAD_PATH . '/' . $path, $dryRun);

        // The uploaded CSV is not kept after processing.
        Uploader::delete($path);

        $_SESSION['_import_result'] = array_merge($result, ['type' => $type]);

        if ($dryRun) {
            flash('info', sprintf(
                'Dry run: %d row(s) would import, %d would be skipped.',
                $result['imported'],
                $result['skipped']
            ));
        } else {
            flash(
                $result['imported'] > 0 ? 'success' : 'warning',
                sprintf('%d row(s) imported, %d skipped.', $result['imported'], $result['skipped'])
            );
        }

        return $this->redirect('/admin/export');
    }

    public function template(Request $request): Response
    {
        $type = (string) $request->param('type');
        if (!isset(ImportService::TYPES[$type])) {
            return $this->fail('Unknown import type.', 404);
        }

        $path = STORAGE_PATH . '/tmp/import_template_' . $type . '.csv';
        if (!is_dir(dirname($path))) {
            @mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, ImportService::template($type));

        return Response::download($path, 'scrapx_' . $type . '_template.csv', 'text/csv; charset=UTF-8');
    }

    public function purgeDemo(Request $request): Response
    {
        if (strtoupper((string) $request->input('confirm', '')) !== 'DELETE') {
            flash('danger', 'Type DELETE in the confirmation box to remove demo data.');
            return $this->back('/admin/export');
        }

        $removed = DemoSeeder::purge(Database::instance());
        \App\Services\AuditService::log('demo_data_purged', 'system', null, null, $removed);

        flash('success', 'Demo data removed: ' . implode(', ', array_map(
            static fn (string $table, int $count): string => "{$count} {$table}",
            array_keys($removed),
            array_values($removed)
        )) . '.');
        return $this->redirect('/admin/export');
    }
}
