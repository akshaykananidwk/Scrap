<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Category extends Model
{
    protected static string $table = 'categories';
    protected static array $fillable = [
        'parent_id', 'name', 'slug', 'icon', 'image', 'description', 'meta_title',
        'meta_description', 'sort_order', 'is_active', 'is_featured',
    ];

    public static function roots(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM categories WHERE parent_id IS NULL';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        return self::db()->select($sql . ' ORDER BY sort_order, name');
    }

    public static function children(int $parentId, bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM categories WHERE parent_id = :p';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        return self::db()->select($sql . ' ORDER BY sort_order, name', ['p' => $parentId]);
    }

    /** Full tree used by navigation, the sell wizard and admin. */
    public static function tree(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM categories';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $rows = self::db()->select($sql . ' ORDER BY sort_order, name');

        $byParent = [];
        foreach ($rows as $row) {
            $byParent[$row['parent_id'] ?? 0][] = $row;
        }
        $tree = [];
        foreach ($byParent[0] ?? [] as $root) {
            $root['children'] = $byParent[$root['id']] ?? [];
            $tree[] = $root;
        }
        return $tree;
    }

    public static function findBySlug(string $slug): ?array
    {
        return self::findBy('slug', $slug);
    }

    /** A category plus every descendant id — used to filter listings by a root category. */
    public static function withDescendantIds(int $categoryId): array
    {
        $ids = [$categoryId];
        foreach (self::children($categoryId, false) as $child) {
            $ids[] = (int) $child['id'];
        }
        return $ids;
    }

    public static function breadcrumb(int $categoryId): array
    {
        $trail = [];
        $current = self::find($categoryId);
        $guard = 0;
        while ($current !== null && $guard++ < 5) {
            array_unshift($trail, $current);
            $current = $current['parent_id'] ? self::find((int) $current['parent_id']) : null;
        }
        return $trail;
    }

    public static function featured(int $limit = 8): array
    {
        return self::db()->select(
            'SELECT * FROM categories WHERE parent_id IS NULL AND is_active = 1
             ORDER BY is_featured DESC, listing_count DESC, sort_order LIMIT ' . max(1, $limit)
        );
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = slugify($name);
        $slug = $base;
        $i = 1;
        while (true) {
            $sql = 'SELECT id FROM categories WHERE slug = :s';
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

    /** Options for a <select>, with subcategories indented. */
    public static function selectOptions(): array
    {
        $options = [];
        foreach (self::tree() as $root) {
            $options[(int) $root['id']] = $root['name'];
            foreach ($root['children'] as $child) {
                $options[(int) $child['id']] = '— ' . $child['name'];
            }
        }
        return $options;
    }
}
