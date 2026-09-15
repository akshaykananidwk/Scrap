<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Category;
use App\Models\City;
use App\Models\Material;
use App\Models\MarketRate;
use App\Models\Unit;

/**
 * CSV bulk import with per-row validation and an error report, so one bad row
 * never silently corrupts a batch.
 */
final class ImportService
{
    public const TYPES = [
        'categories' => ['label' => 'Categories', 'columns' => ['name', 'parent_name', 'icon', 'sort_order', 'is_active']],
        'materials' => ['label' => 'Materials', 'columns' => ['name', 'category_name', 'unit_code', 'hsn_code', 'gst_rate', 'is_active']],
        'grades' => ['label' => 'Material grades', 'columns' => ['name', 'material_name', 'description', 'sort_order']],
        'market_rates' => ['label' => 'Market rates', 'columns' => ['material_name', 'city_name', 'rate', 'unit_code', 'rate_date', 'grade_name', 'source']],
        'cities' => ['label' => 'Cities', 'columns' => ['name', 'state_name', 'latitude', 'longitude', 'is_major']],
        'pincodes' => ['label' => 'Pincodes', 'columns' => ['pincode', 'city_name', 'state_name', 'area', 'latitude', 'longitude']],
    ];

    /**
     * @return array{ok: bool, imported: int, skipped: int, errors: array<int, string>, preview?: array}
     */
    public static function import(string $type, string $csvPath, bool $dryRun = false): array
    {
        if (!isset(self::TYPES[$type])) {
            return ['ok' => false, 'imported' => 0, 'skipped' => 0, 'errors' => ['Unknown import type.']];
        }
        if (!is_file($csvPath)) {
            return ['ok' => false, 'imported' => 0, 'skipped' => 0, 'errors' => ['The uploaded file could not be read.']];
        }

        $handle = fopen($csvPath, 'rb');
        if ($handle === false) {
            return ['ok' => false, 'imported' => 0, 'skipped' => 0, 'errors' => ['Could not open the CSV file.']];
        }

        // Strip a UTF-8 BOM if Excel added one.
        $first = fgets($handle);
        if ($first !== false && str_starts_with($first, "\xEF\xBB\xBF")) {
            $first = substr($first, 3);
        }
        rewind($handle);
        if ($first !== false && str_starts_with((string) fgets($handle), "\xEF\xBB\xBF")) {
            fseek($handle, 3);
        } else {
            rewind($handle);
        }

        // PHP 8.4 deprecates the implicit escape character; pass it explicitly
        // and use '' so the file is parsed as plain RFC-4180 CSV.
        $header = fgetcsv($handle, 0, ',', '"', '');
        if ($header === false) {
            fclose($handle);
            return ['ok' => false, 'imported' => 0, 'skipped' => 0, 'errors' => ['The CSV file is empty.']];
        }
        $header = array_map(
            static fn ($h): string => strtolower(trim(str_replace([' ', '-'], '_', (string) $h))),
            $header
        );

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $preview = [];
        $line = 1;

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $line++;
            if ($row === [null] || $row === []) {
                continue;
            }
            $data = [];
            foreach ($header as $index => $column) {
                $data[$column] = isset($row[$index]) ? trim((string) $row[$index]) : '';
            }
            if (implode('', $data) === '') {
                continue;
            }

            try {
                $result = match ($type) {
                    'categories' => self::importCategory($data, $dryRun),
                    'materials' => self::importMaterial($data, $dryRun),
                    'grades' => self::importGrade($data, $dryRun),
                    'market_rates' => self::importMarketRate($data, $dryRun),
                    'cities' => self::importCity($data, $dryRun),
                    'pincodes' => self::importPincode($data, $dryRun),
                    default => ['ok' => false, 'error' => 'Unsupported type'],
                };

                if ($result['ok']) {
                    $imported++;
                    if (count($preview) < 10) {
                        $preview[] = $result['summary'] ?? $data;
                    }
                } else {
                    $skipped++;
                    if (count($errors) < 100) {
                        $errors[] = 'Row ' . $line . ': ' . $result['error'];
                    }
                }
            } catch (\Throwable $e) {
                $skipped++;
                if (count($errors) < 100) {
                    $errors[] = 'Row ' . $line . ': ' . $e->getMessage();
                }
            }
        }

        fclose($handle);

        if (!$dryRun && $imported > 0) {
            AuditService::log('data_imported', $type, null, null, ['imported' => $imported, 'skipped' => $skipped]);
            if (in_array($type, ['categories', 'materials'], true)) {
                \Database\Seeders\CatalogSeeder::refreshCounts(Database::instance());
            }
        }

        return [
            'ok' => $imported > 0,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'preview' => $preview,
            'dry_run' => $dryRun,
        ];
    }

    private static function importCategory(array $data, bool $dryRun): array
    {
        $name = $data['name'] ?? '';
        if ($name === '') {
            return ['ok' => false, 'error' => 'name is required'];
        }

        $parentId = null;
        if (!empty($data['parent_name'])) {
            $parent = Database::instance()->first('SELECT id FROM categories WHERE name = :n', ['n' => $data['parent_name']]);
            if ($parent === null) {
                return ['ok' => false, 'error' => 'parent category "' . $data['parent_name'] . '" not found'];
            }
            $parentId = (int) $parent['id'];
        }

        if ($dryRun) {
            return ['ok' => true, 'summary' => ['name' => $name, 'parent' => $data['parent_name'] ?? '—']];
        }

        $slug = Category::uniqueSlug($name);
        $existing = Database::instance()->first('SELECT id FROM categories WHERE name = :n AND (parent_id <=> :p)', ['n' => $name, 'p' => $parentId]);
        if ($existing !== null) {
            Category::updateById((int) $existing['id'], [
                'icon' => $data['icon'] ?: null,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'is_active' => isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1,
            ]);
            return ['ok' => true, 'summary' => ['name' => $name, 'action' => 'updated']];
        }

        Category::create([
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => $slug,
            'icon' => $data['icon'] ?: null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => isset($data['is_active']) && $data['is_active'] !== '' ? (int) (bool) $data['is_active'] : 1,
        ]);
        return ['ok' => true, 'summary' => ['name' => $name, 'action' => 'created']];
    }

    private static function importMaterial(array $data, bool $dryRun): array
    {
        $name = $data['name'] ?? '';
        if ($name === '') {
            return ['ok' => false, 'error' => 'name is required'];
        }
        $categoryName = $data['category_name'] ?? '';
        if ($categoryName === '') {
            return ['ok' => false, 'error' => 'category_name is required'];
        }

        $category = Database::instance()->first('SELECT id FROM categories WHERE name = :n ORDER BY parent_id IS NOT NULL DESC LIMIT 1', ['n' => $categoryName]);
        if ($category === null) {
            return ['ok' => false, 'error' => 'category "' . $categoryName . '" not found'];
        }

        $unit = !empty($data['unit_code']) ? Unit::byCode($data['unit_code']) : null;
        $hsn = !empty($data['hsn_code'])
            ? Database::instance()->first('SELECT id, gst_rate FROM hsn_codes WHERE code = :c', ['c' => $data['hsn_code']])
            : null;

        if ($dryRun) {
            return ['ok' => true, 'summary' => ['name' => $name, 'category' => $categoryName]];
        }

        $existing = Database::instance()->first('SELECT id FROM materials WHERE name = :n AND category_id = :c', ['n' => $name, 'c' => (int) $category['id']]);
        $payload = [
            'category_id' => (int) $category['id'],
            'name' => $name,
            'default_unit_id' => $unit['id'] ?? null,
            'hsn_id' => $hsn['id'] ?? null,
            'default_gst_rate' => dec($data['gst_rate'] ?? ($hsn['gst_rate'] ?? 18), 2),
            'is_active' => isset($data['is_active']) && $data['is_active'] !== '' ? (int) (bool) $data['is_active'] : 1,
        ];

        if ($existing !== null) {
            Material::updateById((int) $existing['id'], $payload);
            return ['ok' => true, 'summary' => ['name' => $name, 'action' => 'updated']];
        }
        $payload['slug'] = Material::uniqueSlug($name);
        Material::create($payload);
        return ['ok' => true, 'summary' => ['name' => $name, 'action' => 'created']];
    }

    private static function importGrade(array $data, bool $dryRun): array
    {
        $name = $data['name'] ?? '';
        $materialName = $data['material_name'] ?? '';
        if ($name === '' || $materialName === '') {
            return ['ok' => false, 'error' => 'name and material_name are required'];
        }
        $material = Database::instance()->first('SELECT id FROM materials WHERE name = :n', ['n' => $materialName]);
        if ($material === null) {
            return ['ok' => false, 'error' => 'material "' . $materialName . '" not found'];
        }
        if ($dryRun) {
            return ['ok' => true, 'summary' => ['name' => $name, 'material' => $materialName]];
        }

        $existing = Database::instance()->first(
            'SELECT id FROM material_grades WHERE name = :n AND material_id = :m',
            ['n' => $name, 'm' => (int) $material['id']]
        );
        if ($existing !== null) {
            return ['ok' => true, 'summary' => ['name' => $name, 'action' => 'already exists']];
        }

        Database::instance()->insert('material_grades', [
            'material_id' => (int) $material['id'],
            'name' => $name,
            'slug' => slugify($name . '-' . $material['id']),
            'description' => $data['description'] ?: null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => 1,
            'created_at' => now(),
        ]);
        return ['ok' => true, 'summary' => ['name' => $name, 'action' => 'created']];
    }

    private static function importMarketRate(array $data, bool $dryRun): array
    {
        $materialName = $data['material_name'] ?? '';
        $rate = $data['rate'] ?? '';
        if ($materialName === '' || $rate === '' || !is_numeric($rate)) {
            return ['ok' => false, 'error' => 'material_name and a numeric rate are required'];
        }

        $material = Database::instance()->first('SELECT id, default_unit_id FROM materials WHERE name = :n', ['n' => $materialName]);
        if ($material === null) {
            return ['ok' => false, 'error' => 'material "' . $materialName . '" not found'];
        }

        $cityId = null;
        if (!empty($data['city_name'])) {
            $city = Database::instance()->first('SELECT id FROM cities WHERE name = :n LIMIT 1', ['n' => $data['city_name']]);
            if ($city === null) {
                return ['ok' => false, 'error' => 'city "' . $data['city_name'] . '" not found'];
            }
            $cityId = (int) $city['id'];
        }

        $unit = !empty($data['unit_code']) ? Unit::byCode($data['unit_code']) : null;
        $unitId = $unit['id'] ?? $material['default_unit_id'] ?? Unit::defaultId();

        $date = !empty($data['rate_date']) ? date('Y-m-d', strtotime($data['rate_date'])) : gmdate('Y-m-d');
        if ($date === false) {
            return ['ok' => false, 'error' => 'rate_date is not a valid date'];
        }

        $gradeId = null;
        if (!empty($data['grade_name'])) {
            $grade = Database::instance()->first(
                'SELECT id FROM material_grades WHERE name = :n AND material_id = :m',
                ['n' => $data['grade_name'], 'm' => (int) $material['id']]
            );
            $gradeId = $grade['id'] ?? null;
        }

        if ($dryRun) {
            return ['ok' => true, 'summary' => ['material' => $materialName, 'rate' => $rate, 'date' => $date]];
        }

        MarketRate::record([
            'material_id' => (int) $material['id'],
            'grade_id' => $gradeId,
            'city_id' => $cityId,
            'city_name' => $data['city_name'] ?: null,
            'rate' => $rate,
            'unit_id' => (int) $unitId,
            'rate_date' => $date,
            'source' => $data['source'] ?: 'CSV import',
            'created_by' => \App\Core\Auth::id(),
            'is_published' => 1,
        ]);
        return ['ok' => true, 'summary' => ['material' => $materialName, 'rate' => $rate, 'date' => $date]];
    }

    private static function importCity(array $data, bool $dryRun): array
    {
        $name = $data['name'] ?? '';
        $stateName = $data['state_name'] ?? '';
        if ($name === '' || $stateName === '') {
            return ['ok' => false, 'error' => 'name and state_name are required'];
        }
        $state = Database::instance()->first('SELECT id, code FROM states WHERE name = :n', ['n' => $stateName]);
        if ($state === null) {
            return ['ok' => false, 'error' => 'state "' . $stateName . '" not found'];
        }
        if ($dryRun) {
            return ['ok' => true, 'summary' => ['name' => $name, 'state' => $stateName]];
        }

        $slug = slugify($name . '-' . ($state['code'] ?? $state['id']));
        if (Database::instance()->first('SELECT id FROM cities WHERE slug = :s', ['s' => $slug]) !== null) {
            return ['ok' => true, 'summary' => ['name' => $name, 'action' => 'already exists']];
        }

        City::create([
            'state_id' => (int) $state['id'],
            'name' => $name,
            'slug' => $slug,
            'latitude' => is_numeric($data['latitude'] ?? '') ? $data['latitude'] : null,
            'longitude' => is_numeric($data['longitude'] ?? '') ? $data['longitude'] : null,
            'is_major' => isset($data['is_major']) && $data['is_major'] !== '' ? (int) (bool) $data['is_major'] : 0,
            'is_active' => 1,
        ]);
        return ['ok' => true, 'summary' => ['name' => $name, 'action' => 'created']];
    }

    private static function importPincode(array $data, bool $dryRun): array
    {
        $pincode = preg_replace('/\D/', '', $data['pincode'] ?? '') ?? '';
        if (strlen($pincode) !== 6) {
            return ['ok' => false, 'error' => 'pincode must be 6 digits'];
        }

        $cityId = null;
        $stateId = null;
        if (!empty($data['city_name'])) {
            $city = Database::instance()->first('SELECT id, state_id FROM cities WHERE name = :n LIMIT 1', ['n' => $data['city_name']]);
            $cityId = $city['id'] ?? null;
            $stateId = $city['state_id'] ?? null;
        }
        if ($stateId === null && !empty($data['state_name'])) {
            $state = Database::instance()->first('SELECT id FROM states WHERE name = :n', ['n' => $data['state_name']]);
            $stateId = $state['id'] ?? null;
        }

        if ($dryRun) {
            return ['ok' => true, 'summary' => ['pincode' => $pincode, 'city' => $data['city_name'] ?? '—']];
        }

        $existing = Database::instance()->first('SELECT id FROM pincodes WHERE pincode = :p', ['p' => $pincode]);
        if ($existing !== null) {
            return ['ok' => true, 'summary' => ['pincode' => $pincode, 'action' => 'already exists']];
        }

        Database::instance()->insert('pincodes', [
            'pincode' => $pincode,
            'city_id' => $cityId,
            'state_id' => $stateId,
            'area' => $data['area'] ?: null,
            'latitude' => is_numeric($data['latitude'] ?? '') ? $data['latitude'] : null,
            'longitude' => is_numeric($data['longitude'] ?? '') ? $data['longitude'] : null,
            'created_at' => now(),
        ]);
        return ['ok' => true, 'summary' => ['pincode' => $pincode, 'action' => 'created']];
    }

    /** Downloadable header-only template for a given import type. */
    public static function template(string $type): string
    {
        $columns = self::TYPES[$type]['columns'] ?? [];
        return "\xEF\xBB\xBF" . implode(',', $columns) . "\n";
    }
}
