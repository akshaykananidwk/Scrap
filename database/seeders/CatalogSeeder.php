<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database;

/**
 * Seeds units, HSN codes, the scrap category tree, materials and grades.
 * Everything here is editable by an administrator afterwards — nothing in the
 * application hard-codes a category or material id.
 */
final class CatalogSeeder
{
    public const UNITS = [
        ['KG', 'Kilogram', '1', 1, 1],
        ['MT', 'Metric Tonne', '1000', 1, 2],
        ['QTL', 'Quintal', '100', 1, 3],
        ['TON', 'Ton', '907.18474', 1, 4],
        ['GM', 'Gram', '0.001', 1, 5],
        ['PCS', 'Pieces', null, 0, 6],
        ['NOS', 'Numbers', null, 0, 7],
        ['SET', 'Set', null, 0, 8],
        ['BAG', 'Bag', null, 0, 9],
        ['BALE', 'Bale', null, 0, 10],
        ['DRUM', 'Drum', null, 0, 11],
        ['LOT', 'Lot', null, 0, 12],
        ['TRUCK', 'Truck Load', null, 0, 13],
        ['LTR', 'Litre', null, 0, 14],
        ['MTR', 'Metre', null, 0, 15],
    ];

    public const HSN = [
        ['7204', 'Ferrous waste and scrap; remelting scrap ingots of iron or steel', '18.00'],
        ['7404', 'Copper waste and scrap', '18.00'],
        ['7602', 'Aluminium waste and scrap', '18.00'],
        ['7802', 'Lead waste and scrap', '18.00'],
        ['7902', 'Zinc waste and scrap', '18.00'],
        ['8002', 'Tin waste and scrap', '18.00'],
        ['7503', 'Nickel waste and scrap', '18.00'],
        ['3915', 'Waste, parings and scrap of plastics', '18.00'],
        ['4707', 'Recovered (waste and scrap) paper or paperboard', '5.00'],
        ['8548', 'Waste and scrap of primary cells, batteries and accumulators', '18.00'],
        ['8549', 'Electrical and electronic waste and scrap', '18.00'],
        ['4004', 'Waste, parings and scrap of rubber', '18.00'],
        ['7001', 'Cullet and other waste and scrap of glass', '18.00'],
        ['4401', 'Wood waste and scrap', '5.00'],
        ['8479', 'Machinery and mechanical appliances', '18.00'],
    ];

    /**
     * category => [icon, [subcategory => [materials...]]]
     * A material's grades are listed in MATERIAL_GRADES.
     */
    public const TREE = [
        'Metals' => ['bi-nut', '#64748b', [
            'Ferrous Scrap' => ['Iron Scrap', 'MS Scrap', 'HMS 1', 'HMS 2', 'Steel Scrap', 'Mild Steel', 'Cast Iron', 'GI Scrap', 'Sheet Scrap', 'Structural Scrap'],
            'Non-Ferrous Scrap' => ['Copper', 'Copper Wire', 'Copper Cable', 'Aluminium', 'Aluminium Wire', 'Brass', 'Bronze', 'Lead', 'Zinc', 'Nickel', 'Tin', 'Stainless Steel'],
            'Machining Scrap' => ['Metal Turning', 'Metal Boring', 'Metal Borings & Turnings', 'Grinding Dust'],
        ]],
        'E-Waste' => ['bi-cpu', '#0ea5e9', [
            'Computer & IT Scrap' => ['Computer Scrap', 'Laptop Scrap', 'Desktop Scrap', 'Motherboard', 'CPU', 'RAM', 'Hard Disk', 'SSD', 'Server Scrap', 'Network Equipment'],
            'Consumer Electronics' => ['Mobile Scrap', 'Printer Scrap', 'TV Scrap', 'AC Scrap', 'Electronic Components'],
            'Power & Cables' => ['PCB', 'UPS', 'Inverter', 'Battery', 'Cable Scrap'],
        ]],
        'Plastic' => ['bi-recycle', '#22c55e', [
            'Plastic Polymers' => ['PET', 'HDPE', 'LDPE', 'PVC', 'PP', 'ABS', 'PS', 'Nylon'],
            'Plastic Products' => ['Plastic Granules', 'Plastic Components', 'Plastic Containers', 'Plastic Films', 'Plastic Crates'],
        ]],
        'Paper' => ['bi-file-earmark-text', '#f59e0b', [
            'Recovered Paper' => ['Newspaper', 'Cardboard', 'OCC', 'Office Paper', 'Books', 'Magazine', 'Mixed Paper', 'Kraft Paper', 'Duplex Board'],
        ]],
        'Vehicle' => ['bi-truck', '#ef4444', [
            'End-of-Life Vehicles' => ['Car Scrap', 'Bike Scrap', 'Truck Scrap', 'Bus Scrap'],
            'Auto Components' => ['Auto Parts', 'Engine', 'Gearbox', 'Radiator', 'Body Parts', 'Alloy Wheels'],
            'Consumables' => ['Tyres', 'Vehicle Batteries', 'Used Oil'],
        ]],
        'Industrial' => ['bi-gear-wide-connected', '#8b5cf6', [
            'Plant & Machinery' => ['Machinery Scrap', 'Factory Equipment', 'Motors', 'Transformers', 'Compressors', 'Pumps'],
            'Industrial Material' => ['Electrical Equipment', 'Industrial Components', 'Pipes', 'Cables', 'Bearings', 'Industrial Waste'],
        ]],
        'Construction' => ['bi-buildings', '#0f766e', [
            'Demolition Material' => ['Demolition Scrap', 'Construction Steel', 'Construction Aluminium', 'Concrete Debris'],
            'Fixtures & Fittings' => ['Doors', 'Windows', 'Tiles', 'Electrical Material', 'Plumbing Material', 'Wooden Material', 'Glass Scrap'],
        ]],
    ];

    /** material name => [grades] */
    public const MATERIAL_GRADES = [
        'MS Scrap' => ['Heavy Melting Scrap', 'Turning & Boring', 'Sheet Cutting', 'Bundle', 'Rerollable'],
        'HMS 1' => ['HMS 1 - 80:20', 'HMS 1 - 90:10', 'HMS 1 Prime'],
        'HMS 2' => ['HMS 2 - 70:30', 'HMS 2 Standard'],
        'Copper' => ['Millberry (99.9%)', 'Berry / Candy', 'Birch Cliff', 'Heavy Copper', 'Copper Utensil Scrap'],
        'Copper Wire' => ['Bare Bright', 'Number 1 Wire', 'Number 2 Wire', 'Insulated Wire'],
        'Aluminium' => ['Tense (Extrusion)', 'Taint / Tabor Sheet', 'Troma (Wheels)', 'Tread (Cast)', 'UBC (Cans)'],
        'Brass' => ['Honey Brass', 'Brass Turnings', 'Radiator Brass', 'Brass Utensil'],
        'Stainless Steel' => ['SS 304', 'SS 316', 'SS 202', 'SS 410', 'SS Turnings'],
        'PET' => ['Clear Flakes', 'Coloured Flakes', 'Bottle Bales', 'Hot Wash Grade'],
        'HDPE' => ['Natural Blow', 'Coloured Blow', 'Drum Grade', 'Pipe Grade'],
        'OCC' => ['OCC 11', 'OCC 12', 'Local OCC', 'Imported OCC'],
        'Newspaper' => ['ONP', 'Old News Local', 'White News'],
        'Battery' => ['Lead Acid', 'Lithium Ion', 'Nickel Cadmium', 'Dry Cell'],
        'PCB' => ['High Grade (Server)', 'Medium Grade (Motherboard)', 'Low Grade (Power)', 'Mixed PCB'],
        'Cable Scrap' => ['Armoured Cable', 'House Wire', 'Data Cable', 'Coaxial'],
        'Iron Scrap' => ['Light Iron', 'Heavy Iron', 'Mixed Iron'],
        'Cast Iron' => ['CI Borings', 'CI Chips', 'Heavy Cast'],
    ];

    public static function run(Database $db): void
    {
        foreach (self::UNITS as [$code, $name, $factor, $isWeight, $sort]) {
            $db->upsert('units', [
                'code' => $code,
                'name' => $name,
                'kg_factor' => $factor,
                'is_weight' => $isWeight,
                'is_active' => 1,
                'sort_order' => $sort,
                'created_at' => now(),
            ], ['name', 'kg_factor', 'is_weight', 'sort_order']);
        }

        foreach (self::HSN as [$code, $description, $rate]) {
            $db->upsert('hsn_codes', [
                'code' => $code,
                'description' => $description,
                'gst_rate' => $rate,
                'is_active' => 1,
                'created_at' => now(),
            ], ['description', 'gst_rate']);
        }

        $unitIds = [];
        foreach ($db->select('SELECT id, code FROM units') as $row) {
            $unitIds[$row['code']] = (int) $row['id'];
        }
        $hsnIds = [];
        foreach ($db->select('SELECT id, code, gst_rate FROM hsn_codes') as $row) {
            $hsnIds[$row['code']] = ['id' => (int) $row['id'], 'gst' => (string) $row['gst_rate']];
        }

        $sort = 0;
        foreach (self::TREE as $categoryName => [$icon, $color, $subcategories]) {
            $categoryId = self::upsertCategory($db, $categoryName, null, $icon, ++$sort);

            $subSort = 0;
            foreach ($subcategories as $subName => $materials) {
                $subId = self::upsertCategory($db, $subName, $categoryId, $icon, ++$subSort);

                $materialSort = 0;
                foreach ($materials as $materialName) {
                    $hsn = self::hsnFor($categoryName, $materialName);
                    $materialId = self::upsertMaterial(
                        $db,
                        $materialName,
                        $subId,
                        $unitIds[self::defaultUnitFor($categoryName)] ?? null,
                        $hsnIds[$hsn]['id'] ?? null,
                        $hsnIds[$hsn]['gst'] ?? '18.00',
                        ++$materialSort
                    );

                    foreach (self::MATERIAL_GRADES[$materialName] ?? [] as $gradeSort => $gradeName) {
                        self::upsertGrade($db, $gradeName, $materialId, $gradeSort + 1);
                    }
                }
            }
        }

        self::refreshCounts($db);
    }

    private static function defaultUnitFor(string $category): string
    {
        return match ($category) {
            'E-Waste', 'Vehicle', 'Industrial' => 'KG',
            default => 'MT',
        };
    }

    private static function hsnFor(string $category, string $material): string
    {
        $map = [
            'Copper' => '7404', 'Copper Wire' => '7404', 'Copper Cable' => '7404',
            'Aluminium' => '7602', 'Aluminium Wire' => '7602', 'Construction Aluminium' => '7602',
            'Lead' => '7802', 'Zinc' => '7902', 'Tin' => '8002', 'Nickel' => '7503',
            'Battery' => '8548', 'Vehicle Batteries' => '8548',
            'Tyres' => '4004', 'Glass Scrap' => '7001', 'Wooden Material' => '4401',
        ];
        if (isset($map[$material])) {
            return $map[$material];
        }
        return match ($category) {
            'E-Waste' => '8549',
            'Plastic' => '3915',
            'Paper' => '4707',
            'Industrial' => '8479',
            default => '7204',
        };
    }

    private static function upsertCategory(Database $db, string $name, ?int $parentId, string $icon, int $sort): int
    {
        $slug = slugify($parentId === null ? $name : $name);
        $existing = $db->first('SELECT id FROM categories WHERE slug = :s', ['s' => $slug]);
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        return $db->insert('categories', [
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => $slug,
            'icon' => $icon,
            'description' => $parentId === null
                ? sprintf('Buy and sell %s scrap from verified Indian businesses.', strtolower($name))
                : null,
            'meta_title' => $name . ' Scrap — Buy & Sell Online',
            'meta_description' => sprintf('Live listings, auctions and buyer requirements for %s scrap across India.', strtolower($name)),
            'sort_order' => $sort,
            'is_active' => 1,
            'is_featured' => $parentId === null ? 1 : 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private static function upsertMaterial(
        Database $db,
        string $name,
        int $categoryId,
        ?int $unitId,
        ?int $hsnId,
        string $gstRate,
        int $sort
    ): int {
        $slug = slugify($name);
        $existing = $db->first('SELECT id FROM materials WHERE slug = :s', ['s' => $slug]);
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        return $db->insert('materials', [
            'category_id' => $categoryId,
            'name' => $name,
            'slug' => $slug,
            'default_unit_id' => $unitId,
            'hsn_id' => $hsnId,
            'default_gst_rate' => $gstRate,
            'meta_title' => $name . ' Price & Listings',
            'meta_description' => sprintf('Current %s rates, live listings and verified buyers on ScrapX.', $name),
            'is_active' => 1,
            'sort_order' => $sort,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private static function upsertGrade(Database $db, string $name, int $materialId, int $sort): void
    {
        $slug = slugify($name . '-' . $materialId);
        $existing = $db->first('SELECT id FROM material_grades WHERE slug = :s', ['s' => $slug]);
        if ($existing !== null) {
            return;
        }
        $db->insert('material_grades', [
            'material_id' => $materialId,
            'name' => $name,
            'slug' => $slug,
            'is_active' => 1,
            'sort_order' => $sort,
            'created_at' => now(),
        ]);
    }

    public static function refreshCounts(Database $db): void
    {
        $db->statement(
            'UPDATE categories c SET listing_count = (
                SELECT COUNT(*) FROM listings l
                WHERE (l.category_id = c.id OR l.subcategory_id = c.id)
                  AND l.status = "active" AND l.deleted_at IS NULL
            )'
        );
        $db->statement(
            'UPDATE materials m SET listing_count = (
                SELECT COUNT(*) FROM listings l
                WHERE l.material_id = m.id AND l.status = "active" AND l.deleted_at IS NULL
            )'
        );
    }
}
