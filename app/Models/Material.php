<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Material extends Model
{
    protected static string $table = 'materials';
    protected static array $fillable = [
        'category_id', 'name', 'slug', 'description', 'image', 'default_unit_id', 'hsn_id',
        'default_gst_rate', 'meta_title', 'meta_description', 'is_active', 'is_featured', 'sort_order',
    ];

    public static function forCategory(int $categoryId, bool $includeChildren = true): array
    {
        if (!$includeChildren) {
            return self::where(['category_id' => $categoryId, 'is_active' => 1], 'sort_order ASC, name ASC');
        }
        $ids = Category::withDescendantIds($categoryId);
        [$where, $params] = self::db()->compileWhere(['category_id' => $ids]);
        return self::db()->select(
            'SELECT * FROM materials WHERE ' . $where . ' AND is_active = 1 ORDER BY sort_order, name',
            $params
        );
    }

    public static function findBySlug(string $slug): ?array
    {
        return self::db()->first(
            'SELECT m.*, c.name AS category_name, c.slug AS category_slug, c.parent_id,
                    u.code AS unit_code, h.code AS hsn_code
             FROM materials m
             INNER JOIN categories c ON c.id = m.category_id
             LEFT JOIN units u ON u.id = m.default_unit_id
             LEFT JOIN hsn_codes h ON h.id = m.hsn_id
             WHERE m.slug = :s LIMIT 1',
            ['s' => $slug]
        );
    }

    public static function popular(int $limit = 12): array
    {
        return self::db()->select(
            'SELECT m.*, c.slug AS category_slug FROM materials m
             INNER JOIN categories c ON c.id = m.category_id
             WHERE m.is_active = 1
             ORDER BY m.listing_count DESC, m.is_featured DESC, m.id
             LIMIT ' . max(1, $limit)
        );
    }

    public static function search(string $term, int $limit = 20): array
    {
        return self::db()->select(
            'SELECT id, name, slug, category_id FROM materials
             WHERE is_active = 1 AND name LIKE :q ORDER BY listing_count DESC, name LIMIT ' . max(1, $limit),
            ['q' => '%' . $term . '%']
        );
    }

    public static function grades(int $materialId): array
    {
        return self::db()->select(
            'SELECT * FROM material_grades WHERE (material_id = :m OR material_id IS NULL) AND is_active = 1
             ORDER BY sort_order, name',
            ['m' => $materialId]
        );
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = slugify($name);
        $slug = $base;
        $i = 1;
        while (true) {
            $sql = 'SELECT id FROM materials WHERE slug = :s';
            $params = ['s' => $slug];
            if ($ignoreId !== null) {
                $sql .= ' AND id <> :i';
                $params['i'] = $ignoreId;
            }
            if (self::db()->first($sql, $params) === null) {
                return $slug;
            }
            $slug = $base . '-' . (++$i);
        }
    }
}
