<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Auth;
use App\Core\Database;
use App\Services\InstallService;

/**
 * Optional demo content for evaluating the platform.
 * Every row it creates is tagged `is_demo = 1` so an administrator can purge it
 * in one click from Admin → System → Demo Data.
 */
final class DemoSeeder
{
    public const PASSWORD = 'Demo@1234';

    /** [name, business, type, account_type, mobile, email, city slug fragment] */
    private const USERS = [
        ['Rajesh Mehta', 'Mehta Metal Traders', 'trader', 'both', '9876500011', 'rajesh@demo-scrapx.test', 'Jamnagar'],
        ['Suresh Patel', 'Patel Scrap Aggregators', 'aggregator', 'seller', '9876500022', 'suresh@demo-scrapx.test', 'Rajkot'],
        ['Anita Sharma', 'Sharma Recycling Industries', 'recycler', 'buyer', '9876500033', 'anita@demo-scrapx.test', 'Ahmedabad'],
        ['Vikram Singh', 'Singh Steel Works', 'manufacturer', 'buyer', '9876500044', 'vikram@demo-scrapx.test', 'Ludhiana'],
        ['Imran Qureshi', 'Qureshi E-Waste Solutions', 'ewaste_dealer', 'both', '9876500055', 'imran@demo-scrapx.test', 'New Delhi'],
        ['Deepak Nair', 'Nair Plastics Recovery', 'plastic_paper_trader', 'seller', '9876500066', 'deepak@demo-scrapx.test', 'Kochi'],
    ];

    /** [title, material slug, grade?, qty, unit, price, basis, type, city, condition] */
    private const LISTINGS = [
        ['HMS 1 Heavy Melting Scrap — 80:20 Ready Stock', 'hms-1', 'HMS 1 - 80:20', '120.000', 'MT', '38500.00', 'per_mt', 'negotiable', 'Jamnagar', 'sorted'],
        ['MS Turning & Boring Scrap — 60 MT Available', 'ms-scrap', 'Turning & Boring', '60.000', 'MT', '31200.00', 'per_mt', 'fixed', 'Rajkot', 'loose'],
        ['Copper Millberry Wire Scrap 99.9%', 'copper-wire', 'Bare Bright', '4.500', 'MT', '742000.00', 'per_mt', 'auction', 'Ahmedabad', 'sorted'],
        ['Aluminium Extrusion (Tense) Scrap', 'aluminium', 'Tense (Extrusion)', '18.000', 'MT', '196500.00', 'per_mt', 'negotiable', 'Ludhiana', 'baled'],
        ['Server Motherboard & PCB Scrap — High Grade', 'pcb', 'High Grade (Server)', '2200.000', 'KG', '1250.00', 'per_kg', 'auction', 'New Delhi', 'sorted'],
        ['PET Bottle Bales — Clear, Hot Wash Grade', 'pet', 'Bottle Bales', '32.000', 'MT', '42000.00', 'per_mt', 'fixed', 'Kochi', 'baled'],
        ['OCC 11 Imported Cardboard Bales', 'occ', 'OCC 11', '85.000', 'MT', '19800.00', 'per_mt', 'negotiable', 'Ahmedabad', 'baled'],
        ['Stainless Steel 304 Sheet Cuttings', 'stainless-steel', 'SS 304', '9.500', 'MT', '148000.00', 'per_mt', 'make_offer', 'Rajkot', 'sorted'],
        ['Lead Acid Battery Scrap — Drained', 'battery', 'Lead Acid', '14.000', 'MT', '112000.00', 'per_mt', 'fixed', 'New Delhi', 'as_is'],
        ['Mixed Laptop & Desktop E-Waste Lot', 'laptop-scrap', null, '3500.000', 'KG', '185.00', 'per_kg', 'auction', 'New Delhi', 'unsorted'],
        ['Cast Iron Borings — Clean, Dry', 'cast-iron', 'CI Borings', '45.000', 'MT', '27400.00', 'per_mt', 'fixed', 'Ludhiana', 'loose'],
        ['HDPE Drum Grade Regrind', 'hdpe', 'Drum Grade', '22.000', 'MT', '58000.00', 'per_mt', 'negotiable', 'Kochi', 'shredded'],
    ];

    /** [title, material slug, qty, unit, target price, frequency, city] */
    private const REQUIREMENTS = [
        ['Need 50 MT MS Scrap (HMS 1) monthly — Jamnagar', 'hms-1', '50.000', 'MT', '37800.00', 'monthly', 'Jamnagar'],
        ['Wanted: 5 MT Copper Wire Scrap weekly', 'copper-wire', '5.000', 'MT', '735000.00', 'weekly', 'Ahmedabad'],
        ['Required 100 MT OCC Cardboard — continuous supply', 'occ', '100.000', 'MT', '19200.00', 'monthly', 'Ludhiana'],
        ['Buying Aluminium UBC Cans — 10 MT one time', 'aluminium', '10.000', 'MT', '175000.00', 'one_time', 'Kochi'],
    ];

    public static function run(Database $db, int $adminId): string
    {
        $created = ['users' => 0, 'listings' => 0, 'auctions' => 0, 'bids' => 0, 'requirements' => 0, 'rates' => 0];

        $cityIds = [];
        foreach ($db->select('SELECT c.id, c.name, c.state_id, s.name AS state_name FROM cities c INNER JOIN states s ON s.id = c.state_id') as $row) {
            $cityIds[$row['name']] = $row;
        }
        $unitIds = [];
        foreach ($db->select('SELECT id, code FROM units') as $row) {
            $unitIds[$row['code']] = (int) $row['id'];
        }
        $roleIds = [];
        foreach ($db->select('SELECT id, slug FROM roles') as $row) {
            $roleIds[$row['slug']] = (int) $row['id'];
        }

        // ---- Users + businesses -------------------------------------------------
        $userIds = [];
        $businessIds = [];
        foreach (self::USERS as [$name, $businessName, $businessType, $accountType, $mobile, $email, $cityName]) {
            $existing = $db->first('SELECT id FROM users WHERE mobile = :m', ['m' => $mobile]);
            if ($existing !== null) {
                $userIds[$mobile] = (int) $existing['id'];
                $business = $db->first('SELECT id FROM businesses WHERE user_id = :u', ['u' => (int) $existing['id']]);
                $businessIds[$mobile] = $business !== null ? (int) $business['id'] : null;
                continue;
            }
            $city = $cityIds[$cityName] ?? null;
            $userId = $db->insert('users', [
                'uuid' => InstallService::uuid(),
                'full_name' => $name,
                'email' => $email,
                'mobile' => $mobile,
                'password_hash' => Auth::hash(self::PASSWORD),
                'account_type' => $accountType,
                'status' => 'active',
                'kyc_status' => 'verified',
                'email_verified_at' => now(),
                'mobile_verified_at' => now(),
                'approved_at' => now(),
                'is_demo' => 1,
                'created_at' => gmdate('Y-m-d H:i:s', strtotime('-' . random_int(40, 300) . ' days')),
                'updated_at' => now(),
            ]);
            $userIds[$mobile] = $userId;
            $created['users']++;

            foreach (self::rolesFor($accountType) as $roleSlug) {
                if (isset($roleIds[$roleSlug])) {
                    $db->statement(
                        'INSERT IGNORE INTO user_roles (user_id, role_id, assigned_at) VALUES (:u, :r, :a)',
                        ['u' => $userId, 'r' => $roleIds[$roleSlug], 'a' => now()]
                    );
                }
            }

            $businessIds[$mobile] = $db->insert('businesses', [
                'user_id' => $userId,
                'name' => $businessName,
                'slug' => slugify($businessName),
                'business_type' => $businessType,
                'about' => 'Demo business profile created by the ScrapX installer for evaluation purposes.',
                'established_year' => random_int(1995, 2019),
                'contact_person' => $name,
                'contact_mobile' => $mobile,
                'contact_email' => $email,
                'gstin' => null,
                'city_id' => $city['id'] ?? null,
                'state_id' => $city['state_id'] ?? null,
                'city_name' => $cityName,
                'state_name' => $city['state_name'] ?? null,
                'gst_verified' => 1,
                'pan_verified' => 1,
                'kyc_verified' => 1,
                'rating_avg' => number_format(random_int(380, 500) / 100, 2),
                'rating_count' => random_int(4, 40),
                'response_rate' => number_format(random_int(7000, 9900) / 100, 2),
                'avg_response_minutes' => random_int(12, 240),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ---- Listings -----------------------------------------------------------
        $sellerMobiles = array_keys($userIds);
        $listingIds = [];
        foreach (self::LISTINGS as $index => [$title, $materialSlug, $gradeName, $quantity, $unitCode, $price, $basis, $type, $cityName, $condition]) {
            if ($db->first('SELECT id FROM listings WHERE title = :t', ['t' => $title]) !== null) {
                continue;
            }
            $material = $db->first(
                'SELECT m.*, c.parent_id FROM materials m INNER JOIN categories c ON c.id = m.category_id WHERE m.slug = :s',
                ['s' => $materialSlug]
            );
            if ($material === null) {
                continue;
            }
            $gradeId = null;
            if ($gradeName !== null) {
                $grade = $db->first(
                    'SELECT id FROM material_grades WHERE material_id = :m AND name = :n',
                    ['m' => (int) $material['id'], 'n' => $gradeName]
                );
                $gradeId = $grade !== null ? (int) $grade['id'] : null;
            }
            $mobile = $sellerMobiles[$index % count($sellerMobiles)];
            $city = $cityIds[$cityName] ?? null;
            $unitId = $unitIds[$unitCode] ?? array_values($unitIds)[0];
            $pricePerKg = self::perKg($price, $basis);
            $createdAt = gmdate('Y-m-d H:i:s', strtotime('-' . random_int(1, 25) . ' days'));

            $listingId = $db->insert('listings', [
                'reference' => self::reference('LST'),
                'user_id' => $userIds[$mobile],
                'business_id' => $businessIds[$mobile],
                'title' => $title,
                'slug' => slugify($title) . '-' . strtolower(str_random(5)),
                'category_id' => (int) ($material['parent_id'] ?: $material['category_id']),
                'subcategory_id' => (int) $material['category_id'],
                'material_id' => (int) $material['id'],
                'grade_id' => $gradeId,
                'grade_text' => $gradeName,
                'description' => self::description($title, $gradeName, $quantity, $unitCode, $cityName),
                'listing_type' => $type,
                'quantity' => $quantity,
                'unit_id' => $unitId,
                'min_order_quantity' => dec((float) $quantity / 4, 3),
                'estimated_weight_kg' => self::toKg($quantity, $unitCode),
                'price' => $price,
                'price_per_kg' => $pricePerKg,
                'price_per_mt' => dec((float) $pricePerKg * 1000, 2),
                'total_price' => dec(self::toKg($quantity, $unitCode) * (float) $pricePerKg, 2),
                'is_negotiable' => in_array($type, ['negotiable', 'make_offer'], true) ? 1 : 0,
                'gst_applicable' => 1,
                'gst_rate' => $material['default_gst_rate'],
                'material_condition' => $condition,
                'material_source' => 'industrial',
                'pickup_address' => 'Industrial Area, ' . $cityName,
                'city_id' => $city['id'] ?? null,
                'state_id' => $city['state_id'] ?? null,
                'city_name' => $cityName,
                'state_name' => $city['state_name'] ?? null,
                'pickup_available' => 1,
                'delivery_available' => random_int(0, 1),
                'loading_by' => 'seller',
                'transport_by' => 'buyer',
                'payment_terms' => 'advance',
                'inspection_available' => 1,
                'status' => 'active',
                'is_featured' => $index < 3 ? 1 : 0,
                'promotion_tier' => $index < 3 ? 'featured' : 'none',
                'view_count' => random_int(20, 900),
                'published_at' => $createdAt,
                'expires_at' => gmdate('Y-m-d H:i:s', strtotime('+30 days')),
                'is_demo' => 1,
                'created_at' => $createdAt,
                'updated_at' => now(),
            ]);
            $listingIds[] = ['id' => $listingId, 'type' => $type, 'user' => $userIds[$mobile], 'price' => $price,
                'quantity' => $quantity, 'unit_id' => $unitId, 'title' => $title, 'basis' => $basis];
            $created['listings']++;
        }

        // ---- Auctions + bids ----------------------------------------------------
        foreach ($listingIds as $listing) {
            if ($listing['type'] !== 'auction') {
                continue;
            }
            $startPrice = dec((float) $listing['price'] * 0.88, 2);
            $increment = dec(max(100, (float) $listing['price'] * 0.005), 2);
            $startsAt = gmdate('Y-m-d H:i:s', strtotime('-2 days'));
            $endsAt = gmdate('Y-m-d H:i:s', strtotime('+' . random_int(1, 5) . ' days'));

            $auctionId = $db->insert('auctions', [
                'reference' => self::reference('AUC'),
                'listing_id' => $listing['id'],
                'owner_id' => $listing['user'],
                'auction_type' => 'forward',
                'title' => $listing['title'],
                'description' => 'Demo forward auction. The highest valid bid at close wins.',
                'quantity' => $listing['quantity'],
                'unit_id' => $listing['unit_id'],
                'price_basis' => $listing['basis'],
                'starting_price' => $startPrice,
                'reserve_price' => dec((float) $listing['price'] * 0.97, 2),
                'bid_increment' => $increment,
                'current_price' => $startPrice,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'original_ends_at' => $endsAt,
                'extension_window_seconds' => 120,
                'extension_duration_seconds' => 120,
                'max_extensions' => 5,
                'requires_kyc' => 1,
                'mask_bidders' => 1,
                'status' => 'live',
                'is_demo' => 1,
                'created_at' => $startsAt,
                'updated_at' => now(),
            ]);
            $created['auctions']++;

            // A few competing bids from other demo users.
            $bidders = array_values(array_diff(array_values($userIds), [$listing['user']]));
            shuffle($bidders);
            $bidders = array_slice($bidders, 0, min(3, count($bidders)));
            $current = (float) $startPrice;
            $lastBidId = null;
            $lastBidder = null;
            $bidCount = 0;

            foreach ($bidders as $round => $bidderId) {
                $bids = random_int(1, 2);
                for ($i = 0; $i < $bids; $i++) {
                    $current += (float) $increment * random_int(1, 3);
                    $placedAt = gmdate('Y-m-d H:i:s.v', strtotime('-' . (40 - ($bidCount * 3)) . ' hours'));
                    if ($lastBidId !== null) {
                        $db->update('bids', ['status' => 'outbid'], ['id' => $lastBidId]);
                    }
                    $lastBidId = $db->insert('bids', [
                        'bid_uid' => bin2hex(random_bytes(16)),
                        'auction_id' => $auctionId,
                        'user_id' => $bidderId,
                        'amount' => dec($current, 2),
                        'quantity' => $listing['quantity'],
                        'status' => 'winning',
                        'ip' => '127.0.0.1',
                        'user_agent' => 'ScrapX Demo Seeder',
                        'placed_at' => $placedAt,
                        'created_at' => substr($placedAt, 0, 19),
                    ]);
                    $lastBidder = $bidderId;
                    $bidCount++;
                    $created['bids']++;

                    $db->upsert('auction_bidders', [
                        'auction_id' => $auctionId,
                        'user_id' => $bidderId,
                        'bidder_number' => $round + 1,
                        'status' => 'approved',
                        'bid_count' => 1,
                        'last_bid_at' => substr($placedAt, 0, 19),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ], ['bid_count', 'last_bid_at', 'updated_at']);
                }
            }

            $db->update('auctions', [
                'current_price' => dec($current, 2),
                'current_bid_id' => $lastBidId,
                'winning_user_id' => $lastBidder,
                'bid_count' => $bidCount,
                'bidder_count' => count($bidders),
                'reserve_met' => $current >= (float) dec((float) $listing['price'] * 0.97, 2) ? 1 : 0,
                'updated_at' => now(),
            ], ['id' => $auctionId]);

            $db->insert('auction_events', [
                'auction_id' => $auctionId,
                'user_id' => $listing['user'],
                'event_type' => 'started',
                'details' => 'Demo auction opened for bidding',
                'created_at' => gmdate('Y-m-d H:i:s.v'),
            ]);
        }

        // ---- Buyer requirements -------------------------------------------------
        foreach (self::REQUIREMENTS as $index => [$title, $materialSlug, $quantity, $unitCode, $target, $frequency, $cityName]) {
            if ($db->first('SELECT id FROM wanted_requirements WHERE title = :t', ['t' => $title]) !== null) {
                continue;
            }
            $material = $db->first(
                'SELECT m.*, c.parent_id FROM materials m INNER JOIN categories c ON c.id = m.category_id WHERE m.slug = :s',
                ['s' => $materialSlug]
            );
            if ($material === null) {
                continue;
            }
            $mobile = $sellerMobiles[($index + 2) % count($sellerMobiles)];
            $city = $cityIds[$cityName] ?? null;

            $db->insert('wanted_requirements', [
                'reference' => self::reference('REQ'),
                'user_id' => $userIds[$mobile],
                'business_id' => $businessIds[$mobile],
                'title' => $title,
                'slug' => slugify($title) . '-' . strtolower(str_random(5)),
                'category_id' => (int) ($material['parent_id'] ?: $material['category_id']),
                'material_id' => (int) $material['id'],
                'description' => 'Demo buyer requirement. Sellers can submit an offer with price, available quantity and delivery terms.',
                'quantity' => $quantity,
                'unit_id' => $unitIds[$unitCode] ?? array_values($unitIds)[0],
                'min_quantity' => dec((float) $quantity / 5, 3),
                'max_quantity' => dec((float) $quantity * 2, 3),
                'target_price' => $target,
                'price_basis' => $unitCode === 'KG' ? 'per_kg' : 'per_mt',
                'frequency' => $frequency,
                'delivery_required' => 1,
                'city_id' => $city['id'] ?? null,
                'state_id' => $city['state_id'] ?? null,
                'city_name' => $cityName,
                'state_name' => $city['state_name'] ?? null,
                'required_by' => gmdate('Y-m-d', strtotime('+21 days')),
                'payment_terms' => 'after_weighment',
                'status' => 'open',
                'expires_at' => gmdate('Y-m-d H:i:s', strtotime('+30 days')),
                'is_demo' => 1,
                'created_at' => gmdate('Y-m-d H:i:s', strtotime('-' . random_int(1, 12) . ' days')),
                'updated_at' => now(),
            ]);
            $created['requirements']++;
        }

        // ---- Market rates (30-day history for headline materials) ---------------
        $rateMaterials = $db->select(
            "SELECT id, slug, default_unit_id FROM materials WHERE slug IN ('hms-1','ms-scrap','copper','aluminium','brass','pet','occ','stainless-steel')"
        );
        $rateCities = $db->select("SELECT id, name FROM cities WHERE is_major = 1 LIMIT 6");
        $baseRates = [
            'hms-1' => 38000, 'ms-scrap' => 31500, 'copper' => 735000, 'aluminium' => 195000,
            'brass' => 420000, 'pet' => 41500, 'occ' => 19500, 'stainless-steel' => 146000,
        ];
        $mtUnit = $unitIds['MT'] ?? array_values($unitIds)[0];

        foreach ($rateMaterials as $material) {
            $base = (float) ($baseRates[$material['slug']] ?? 30000);
            foreach ($rateCities as $city) {
                $previous = null;
                for ($day = 30; $day >= 0; $day--) {
                    $date = gmdate('Y-m-d', strtotime("-{$day} days"));
                    $rate = round($base * (1 + (random_int(-250, 250) / 10000)), 2);
                    $change = $previous !== null ? $rate - $previous : 0;
                    $changePercent = $previous ? ($change / $previous) * 100 : 0;
                    try {
                        $db->insert('market_rates', [
                            'material_id' => (int) $material['id'],
                            'city_id' => (int) $city['id'],
                            'city_name' => $city['name'],
                            'rate' => dec($rate, 2),
                            'unit_id' => (int) ($material['default_unit_id'] ?: $mtUnit),
                            'rate_date' => $date,
                            'previous_rate' => $previous !== null ? dec($previous, 2) : null,
                            'change_amount' => dec($change, 2),
                            'change_percent' => dec($changePercent, 3),
                            'source' => 'Demo data',
                            'is_published' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $created['rates']++;
                    } catch (\Throwable) {
                        // Unique per material/city/day — ignore duplicates on re-run.
                    }
                    $previous = $rate;
                    $base = $rate;
                }
            }
        }

        CatalogSeeder::refreshCounts($db);

        return sprintf(
            '%d users, %d listings, %d auctions, %d bids, %d requirements, %d market rates (password: %s)',
            $created['users'],
            $created['listings'],
            $created['auctions'],
            $created['bids'],
            $created['requirements'],
            $created['rates'],
            self::PASSWORD
        );
    }

    /** Remove every demo row. Used by Admin → System → Demo Data. */
    public static function purge(Database $db): array
    {
        $removed = [];
        $db->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['bids', 'auction_events', 'auction_bidders', 'auctions'] as $table) {
            $removed[$table] = $db->statement(
                "DELETE FROM `{$table}` WHERE auction_id IN (SELECT id FROM (SELECT id FROM auctions WHERE is_demo = 1) AS a)"
            );
        }
        $removed['auctions'] = $db->statement('DELETE FROM auctions WHERE is_demo = 1');
        $removed['listings'] = $db->statement('DELETE FROM listings WHERE is_demo = 1');
        $removed['wanted_requirements'] = $db->statement('DELETE FROM wanted_requirements WHERE is_demo = 1');
        $removed['rfqs'] = $db->statement('DELETE FROM rfqs WHERE is_demo = 1');
        $removed['orders'] = $db->statement('DELETE FROM orders WHERE is_demo = 1');
        $removed['market_rates'] = $db->statement("DELETE FROM market_rates WHERE source = 'Demo data'");
        $removed['users'] = $db->statement('DELETE FROM users WHERE is_demo = 1');
        $db->exec('SET FOREIGN_KEY_CHECKS = 1');
        CatalogSeeder::refreshCounts($db);
        return $removed;
    }

    private static function rolesFor(string $accountType): array
    {
        return match ($accountType) {
            'seller' => ['seller'],
            'buyer' => ['buyer'],
            default => ['trader', 'buyer', 'seller'],
        };
    }

    private static function perKg(string $price, string $basis): string
    {
        return match ($basis) {
            'per_mt' => dec((float) $price / 1000, 4),
            'per_kg' => dec($price, 4),
            default => dec($price, 4),
        };
    }

    private static function toKg(string $quantity, string $unitCode): float
    {
        return match ($unitCode) {
            'MT' => (float) $quantity * 1000,
            'QTL' => (float) $quantity * 100,
            'KG' => (float) $quantity,
            default => (float) $quantity,
        };
    }

    private static function reference(string $prefix): string
    {
        return $prefix . gmdate('ymd') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    }

    private static function description(string $title, ?string $grade, string $quantity, string $unit, string $city): string
    {
        return "Demo listing for evaluation.\n\n"
            . "Material: {$title}\n"
            . ($grade ? "Grade: {$grade}\n" : '')
            . "Available quantity: {$quantity} {$unit}\n"
            . "Location: {$city}\n\n"
            . "Material is stored under cover and available for inspection during working hours. "
            . "Loading by seller, transport arranged by buyer. Weighment at a nearby weighbridge, "
            . "settlement on actual weight.";
    }
}
