<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Models\Material;
use App\Models\Unit;
use App\Services\AuditService;
use Database\Seeders\CatalogSeeder;

final class CatalogController extends Controller
{
    protected string $layout = 'layouts/admin';

    public function index(Request $request): Response
    {
        return $this->view('admin/catalog', [
            'title' => 'Categories',
            'tree' => Category::tree(false),
            'roots' => Category::roots(false),
            'edit' => $request->int('edit') ? Category::find($request->int('edit')) : null,
        ]);
    }

    public function saveCategory(Request $request): Response
    {
        $validator = $this->validate($request, [
            'name' => 'required|min:2|max:120',
            'parent_id' => 'nullable|integer',
            'sort_order' => 'nullable|integer|min_value:0',
            'meta_title' => 'nullable|max:190',
            'meta_description' => 'nullable|max:300',
        ], ['name' => 'Category name']);
        if ($validator->fails()) {
            return $validator->failResponse($request, '/admin/catalog');
        }

        $id = $request->int('id') ?: null;
        $parentId = $request->int('parent_id') ?: null;

        // A category cannot be its own parent, nor its child's child.
        if ($id !== null && $parentId === $id) {
            return $this->fail('A category cannot be its own parent.');
        }
        if ($id !== null && $parentId !== null) {
            $parent = Category::find($parentId);
            if ($parent !== null && (int) ($parent['parent_id'] ?? 0) === $id) {
                return $this->fail('That would create a circular category tree.');
            }
        }

        $data = [
            'parent_id' => $parentId,
            'name' => (string) $request->input('name'),
            'icon' => $request->input('icon') ?: null,
            'description' => $request->input('description') ?: null,
            'meta_title' => $request->input('meta_title') ?: null,
            'meta_description' => $request->input('meta_description') ?: null,
            'sort_order' => $request->int('sort_order'),
            'is_active' => $request->bool('is_active') ? 1 : 0,
            'is_featured' => $request->bool('is_featured') ? 1 : 0,
        ];

        if ($id !== null) {
            Category::updateById($id, $data);
            AuditService::log('category_updated', 'category', $id, null, $data);
            $message = 'Category updated.';
        } else {
            $data['slug'] = Category::uniqueSlug($data['name']);
            $id = Category::create($data);
            AuditService::log('category_created', 'category', $id, null, $data);
            $message = 'Category created.';
        }

        flash('success', $message);
        return $this->redirect('/admin/catalog');
    }

    public function deleteCategory(Request $request): Response
    {
        $id = $request->paramInt('id');
        $db = Database::instance();

        $listings = (int) $db->scalar(
            'SELECT COUNT(*) FROM listings WHERE (category_id = :c OR subcategory_id = :c2) AND deleted_at IS NULL',
            ['c' => $id, 'c2' => $id],
            0
        );
        if ($listings > 0) {
            flash('danger', "This category has {$listings} listing(s). Deactivate it instead of deleting.");
            return $this->back('/admin/catalog');
        }

        $children = (int) $db->scalar('SELECT COUNT(*) FROM categories WHERE parent_id = :c', ['c' => $id], 0);
        if ($children > 0) {
            flash('danger', "Remove the {$children} subcategory(ies) first.");
            return $this->back('/admin/catalog');
        }

        Category::destroy($id);
        AuditService::log('category_deleted', 'category', $id);
        flash('success', 'Category deleted.');
        return $this->redirect('/admin/catalog');
    }

    public function materials(Request $request): Response
    {
        $categoryId = $request->int('category_id');
        $search = trim((string) $request->query('q', ''));

        $sql = 'SELECT m.*, c.name AS category_name, u.code AS unit_code, h.code AS hsn_code
                FROM materials m
                INNER JOIN categories c ON c.id = m.category_id
                LEFT JOIN units u ON u.id = m.default_unit_id
                LEFT JOIN hsn_codes h ON h.id = m.hsn_id
                WHERE 1 = 1';
        $count = 'SELECT COUNT(*) FROM materials m WHERE 1 = 1';
        $params = [];

        if ($categoryId > 0) {
            $ids = Category::withDescendantIds($categoryId);
            $ph = [];
            foreach ($ids as $i => $cid) {
                $ph[] = ':c' . $i;
                $params['c' . $i] = $cid;
            }
            $sql .= ' AND m.category_id IN (' . implode(',', $ph) . ')';
            $count .= ' AND m.category_id IN (' . implode(',', $ph) . ')';
        }
        if ($search !== '') {
            $sql .= ' AND m.name LIKE :q';
            $count .= ' AND m.name LIKE :q';
            $params['q'] = '%' . $search . '%';
        }

        return $this->view('admin/materials', [
            'title' => 'Materials & grades',
            'materials' => \App\Core\Model::paginateQuery($sql . ' ORDER BY c.name, m.sort_order, m.name', $params, $request->page(), 40, $count),
            'categories' => Category::selectOptions(),
            'units' => Unit::active(),
            'hsn_codes' => Database::instance()->select('SELECT * FROM hsn_codes WHERE is_active = 1 ORDER BY code'),
            'filters' => ['category_id' => $categoryId, 'q' => $search],
            'edit' => $request->int('edit') ? Material::find($request->int('edit')) : null,
            'grades' => $request->int('edit') ? Material::grades($request->int('edit')) : [],
        ]);
    }

    public function saveMaterial(Request $request): Response
    {
        $validator = $this->validate($request, [
            'name' => 'required|min:2|max:140',
            'category_id' => 'required|integer|exists:categories,id',
            'default_gst_rate' => 'nullable|numeric|min_value:0|max_value:50',
        ], ['name' => 'Material name', 'category_id' => 'Category']);
        if ($validator->fails()) {
            return $validator->failResponse($request, '/admin/catalog/materials');
        }

        $id = $request->int('id') ?: null;
        $data = [
            'category_id' => $request->int('category_id'),
            'name' => (string) $request->input('name'),
            'description' => $request->input('description') ?: null,
            'default_unit_id' => $request->int('default_unit_id') ?: null,
            'hsn_id' => $request->int('hsn_id') ?: null,
            'default_gst_rate' => dec($request->input('default_gst_rate', 18), 2),
            'meta_title' => $request->input('meta_title') ?: null,
            'meta_description' => $request->input('meta_description') ?: null,
            'is_active' => $request->bool('is_active') ? 1 : 0,
            'is_featured' => $request->bool('is_featured') ? 1 : 0,
            'sort_order' => $request->int('sort_order'),
        ];

        if ($id !== null) {
            Material::updateById($id, $data);
            $message = 'Material updated.';
        } else {
            $data['slug'] = Material::uniqueSlug($data['name']);
            $id = Material::create($data);
            $message = 'Material created.';
        }

        AuditService::log('material_saved', 'material', $id, null, ['name' => $data['name']]);
        flash('success', $message);
        return $this->redirect('/admin/catalog/materials?edit=' . $id);
    }

    public function deleteMaterial(Request $request): Response
    {
        $id = $request->paramInt('id');
        $listings = (int) Database::instance()->scalar(
            'SELECT COUNT(*) FROM listings WHERE material_id = :m AND deleted_at IS NULL',
            ['m' => $id],
            0
        );
        if ($listings > 0) {
            flash('danger', "This material is used by {$listings} listing(s). Deactivate it instead.");
            return $this->back('/admin/catalog/materials');
        }

        Material::destroy($id);
        AuditService::log('material_deleted', 'material', $id);
        flash('success', 'Material deleted.');
        return $this->redirect('/admin/catalog/materials');
    }

    public function saveGrade(Request $request): Response
    {
        $name = trim((string) $request->input('name', ''));
        $materialId = $request->int('material_id');
        if ($name === '' || $materialId <= 0) {
            return $this->fail('Grade name and material are required.');
        }

        $id = $request->int('id') ?: null;
        $data = [
            'material_id' => $materialId,
            'name' => $name,
            'description' => $request->input('description') ?: null,
            'sort_order' => $request->int('sort_order'),
            'is_active' => $request->bool('is_active') ? 1 : 0,
        ];

        if ($id !== null) {
            Database::instance()->update('material_grades', $data, ['id' => $id]);
        } else {
            $data['slug'] = slugify($name . '-' . $materialId);
            $data['created_at'] = now();
            Database::instance()->insert('material_grades', $data);
        }

        flash('success', 'Grade saved.');
        return $this->back('/admin/catalog/materials?edit=' . $materialId);
    }

    public function deleteGrade(Request $request): Response
    {
        $id = $request->paramInt('id');
        $inUse = (int) Database::instance()->scalar(
            'SELECT COUNT(*) FROM listings WHERE grade_id = :g AND deleted_at IS NULL',
            ['g' => $id],
            0
        );
        if ($inUse > 0) {
            flash('danger', 'This grade is in use by existing listings.');
            return $this->back('/admin/catalog/materials');
        }

        Database::instance()->delete('material_grades', ['id' => $id]);
        flash('success', 'Grade deleted.');
        return $this->back('/admin/catalog/materials');
    }

    public function units(Request $request): Response
    {
        return $this->view('admin/units', [
            'title' => 'Units & HSN codes',
            'units' => Database::instance()->select('SELECT * FROM units ORDER BY sort_order, code'),
            'hsn_codes' => Database::instance()->select('SELECT * FROM hsn_codes ORDER BY code'),
        ]);
    }

    public function saveUnit(Request $request): Response
    {
        $code = strtoupper(trim((string) $request->input('code', '')));
        $name = trim((string) $request->input('name', ''));
        if ($code === '' || $name === '') {
            return $this->fail('Unit code and name are required.');
        }

        $id = $request->int('id') ?: null;
        $data = [
            'code' => substr($code, 0, 12),
            'name' => substr($name, 0, 60),
            'kg_factor' => $request->input('kg_factor') !== '' && $request->input('kg_factor') !== null
                ? dec($request->input('kg_factor'), 6)
                : null,
            'is_weight' => $request->bool('is_weight') ? 1 : 0,
            'is_active' => $request->bool('is_active') ? 1 : 0,
            'sort_order' => $request->int('sort_order'),
        ];

        try {
            if ($id !== null) {
                Database::instance()->update('units', $data, ['id' => $id]);
            } else {
                $data['created_at'] = now();
                Database::instance()->insert('units', $data);
            }
        } catch (\Throwable) {
            return $this->fail('That unit code already exists.');
        }

        flash('success', 'Unit saved.');
        return $this->redirect('/admin/catalog/units');
    }

    public function saveHsn(Request $request): Response
    {
        $code = trim((string) $request->input('code', ''));
        $description = trim((string) $request->input('description', ''));
        if ($code === '' || $description === '') {
            return $this->fail('HSN code and description are required.');
        }

        $id = $request->int('id') ?: null;
        $data = [
            'code' => substr($code, 0, 12),
            'description' => substr($description, 0, 255),
            'gst_rate' => dec($request->input('gst_rate', 18), 2),
            'is_active' => $request->bool('is_active') ? 1 : 0,
        ];

        try {
            if ($id !== null) {
                Database::instance()->update('hsn_codes', $data, ['id' => $id]);
            } else {
                $data['created_at'] = now();
                Database::instance()->insert('hsn_codes', $data);
            }
        } catch (\Throwable) {
            return $this->fail('That HSN code already exists.');
        }

        flash('success', 'HSN code saved.');
        return $this->redirect('/admin/catalog/units');
    }
}
