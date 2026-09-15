<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Paginator;

final class MarketRate extends Model
{
    protected static string $table = 'market_rates';
    protected static array $fillable = [
        'material_id', 'grade_id', 'city_id', 'city_name', 'state_id', 'rate', 'unit_id',
        'rate_date', 'previous_rate', 'change_amount', 'change_percent', 'source', 'notes',
        'is_published', 'created_by',
    ];

    /** Latest published rate per material/city, with the day-on-day change. */
    public static function latest(array $filters = [], int $limit = 50): array
    {
        $sql = 'SELECT r.*, m.name AS material_name, m.slug AS material_slug,
                       c.name AS category_name, un.code AS unit_code, g.name AS grade_name
                FROM market_rates r
                INNER JOIN materials m ON m.id = r.material_id
                INNER JOIN categories c ON c.id = m.category_id
                INNER JOIN units un ON un.id = r.unit_id
                LEFT JOIN material_grades g ON g.id = r.grade_id
                INNER JOIN (
                    SELECT material_id, COALESCE(city_id, 0) AS city_key, MAX(rate_date) AS max_date
                    FROM market_rates WHERE is_published = 1
                    GROUP BY material_id, COALESCE(city_id, 0)
                ) latest ON latest.material_id = r.material_id
                        AND latest.city_key = COALESCE(r.city_id, 0)
                        AND latest.max_date = r.rate_date
                WHERE r.is_published = 1';
        $params = [];

        if (!empty($filters['material_id'])) {
            $sql .= ' AND r.material_id = :material_id';
            $params['material_id'] = (int) $filters['material_id'];
        }
        if (!empty($filters['city_id'])) {
            $sql .= ' AND r.city_id = :city_id';
            $params['city_id'] = (int) $filters['city_id'];
        }
        if (!empty($filters['category_id'])) {
            $ids = Category::withDescendantIds((int) $filters['category_id']);
            $ph = [];
            foreach ($ids as $i => $id) {
                $ph[] = ':mc' . $i;
                $params['mc' . $i] = $id;
            }
            $sql .= ' AND m.category_id IN (' . implode(',', $ph) . ')';
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (m.name LIKE :q OR r.city_name LIKE :q2)';
            $params['q'] = '%' . $filters['q'] . '%';
            $params['q2'] = '%' . $filters['q'] . '%';
        }

        $sql .= ' ORDER BY m.name, r.city_name LIMIT ' . max(1, $limit);
        return self::db()->select($sql, $params);
    }

    /** Price history for a chart. */
    public static function history(int $materialId, ?int $cityId, int $days = 30): array
    {
        $sql = 'SELECT rate_date, rate, change_percent FROM market_rates
                WHERE material_id = :m AND is_published = 1 AND rate_date >= :since';
        $params = ['m' => $materialId, 'since' => gmdate('Y-m-d', strtotime("-{$days} days"))];
        if ($cityId !== null) {
            $sql .= ' AND city_id = :c';
            $params['c'] = $cityId;
        } else {
            $sql .= ' AND city_id IS NULL';
        }
        return self::db()->select($sql . ' ORDER BY rate_date ASC', $params);
    }

    public static function paginate(array $filters, int $page, int $perPage = 30): Paginator
    {
        $sql = 'SELECT r.*, m.name AS material_name, un.code AS unit_code, g.name AS grade_name,
                       u.full_name AS created_by_name
                FROM market_rates r
                INNER JOIN materials m ON m.id = r.material_id
                INNER JOIN units un ON un.id = r.unit_id
                LEFT JOIN material_grades g ON g.id = r.grade_id
                LEFT JOIN users u ON u.id = r.created_by
                WHERE 1 = 1';
        $count = 'SELECT COUNT(*) FROM market_rates r WHERE 1 = 1';
        $params = [];

        if (!empty($filters['material_id'])) {
            $sql .= ' AND r.material_id = :material_id';
            $count .= ' AND r.material_id = :material_id';
            $params['material_id'] = (int) $filters['material_id'];
        }
        if (!empty($filters['city_id'])) {
            $sql .= ' AND r.city_id = :city_id';
            $count .= ' AND r.city_id = :city_id';
            $params['city_id'] = (int) $filters['city_id'];
        }
        if (!empty($filters['date'])) {
            $sql .= ' AND r.rate_date = :date';
            $count .= ' AND r.rate_date = :date';
            $params['date'] = $filters['date'];
        }

        return self::paginateQuery($sql . ' ORDER BY r.rate_date DESC, r.id DESC', $params, $page, $perPage, $count);
    }

    /**
     * Save a rate, computing the change against the previous published rate for
     * the same material/grade/city.
     */
    public static function record(array $data): int
    {
        $db = self::db();
        $materialId = (int) $data['material_id'];
        $cityId = !empty($data['city_id']) ? (int) $data['city_id'] : null;
        $gradeId = !empty($data['grade_id']) ? (int) $data['grade_id'] : null;
        $date = $data['rate_date'] ?? gmdate('Y-m-d');
        $rate = dec($data['rate'], 2);

        $previous = $db->first(
            'SELECT rate FROM market_rates
             WHERE material_id = :m AND rate_date < :d
               AND (city_id = :c OR (:c2 IS NULL AND city_id IS NULL))
               AND (grade_id = :g OR (:g2 IS NULL AND grade_id IS NULL))
             ORDER BY rate_date DESC LIMIT 1',
            ['m' => $materialId, 'd' => $date, 'c' => $cityId, 'c2' => $cityId, 'g' => $gradeId, 'g2' => $gradeId]
        );

        $previousRate = $previous !== null ? (float) $previous['rate'] : null;
        $change = $previousRate !== null ? (float) $rate - $previousRate : 0.0;
        $changePercent = ($previousRate !== null && $previousRate > 0) ? ($change / $previousRate) * 100 : 0.0;

        $city = $cityId !== null ? City::find($cityId) : null;

        $row = [
            'material_id' => $materialId,
            'grade_id' => $gradeId,
            'city_id' => $cityId,
            'city_name' => $city['name'] ?? ($data['city_name'] ?? null),
            'state_id' => $city['state_id'] ?? null,
            'rate' => $rate,
            'unit_id' => (int) $data['unit_id'],
            'rate_date' => $date,
            'previous_rate' => $previousRate !== null ? dec($previousRate, 2) : null,
            'change_amount' => dec($change, 2),
            'change_percent' => dec($changePercent, 3),
            'source' => $data['source'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_published' => isset($data['is_published']) ? (int) $data['is_published'] : 1,
            'created_by' => $data['created_by'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $existing = $db->first(
            'SELECT id FROM market_rates WHERE material_id = :m AND rate_date = :d
             AND (city_id = :c OR (:c2 IS NULL AND city_id IS NULL))
             AND (grade_id = :g OR (:g2 IS NULL AND grade_id IS NULL))',
            ['m' => $materialId, 'd' => $date, 'c' => $cityId, 'c2' => $cityId, 'g' => $gradeId, 'g2' => $gradeId]
        );

        if ($existing !== null) {
            unset($row['created_at']);
            $db->update('market_rates', $row, ['id' => (int) $existing['id']]);
            return (int) $existing['id'];
        }
        return $db->insert('market_rates', $row);
    }

    /** Biggest movers for the homepage ticker. */
    public static function movers(int $limit = 8): array
    {
        return self::db()->select(
            'SELECT r.*, m.name AS material_name, m.slug AS material_slug, un.code AS unit_code
             FROM market_rates r
             INNER JOIN materials m ON m.id = r.material_id
             INNER JOIN units un ON un.id = r.unit_id
             WHERE r.is_published = 1 AND r.rate_date >= :since
             ORDER BY ABS(r.change_percent) DESC, r.rate_date DESC
             LIMIT ' . max(1, $limit),
            ['since' => gmdate('Y-m-d', strtotime('-3 days'))]
        );
    }
}
