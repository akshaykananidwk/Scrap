<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Models\City;
use App\Models\MarketRate;
use App\Models\Material;
use App\Models\State;
use App\Models\Unit;

final class CatalogApiController extends BaseApiController
{
    public function categories(Request $request): Response
    {
        return $this->data(array_map(static fn (array $category): array => [
            'id' => (int) $category['id'],
            'name' => $category['name'],
            'slug' => $category['slug'],
            'icon' => $category['icon'],
            'listing_count' => (int) $category['listing_count'],
            'url' => base_url('scrap/' . $category['slug']),
            'children' => array_map(static fn (array $child): array => [
                'id' => (int) $child['id'],
                'name' => $child['name'],
                'slug' => $child['slug'],
                'listing_count' => (int) $child['listing_count'],
                'url' => base_url('scrap/' . $child['slug']),
            ], $category['children']),
        ], Category::tree()));
    }

    public function materials(Request $request): Response
    {
        $materials = Material::forCategory($request->paramInt('id'));

        return $this->data(array_map(static fn (array $material): array => [
            'id' => (int) $material['id'],
            'name' => $material['name'],
            'slug' => $material['slug'],
            'default_unit_id' => $material['default_unit_id'] !== null ? (int) $material['default_unit_id'] : null,
            'gst_rate' => (float) $material['default_gst_rate'],
            'listing_count' => (int) $material['listing_count'],
        ], $materials));
    }

    public function grades(Request $request): Response
    {
        return $this->data(array_map(static fn (array $grade): array => [
            'id' => (int) $grade['id'],
            'name' => $grade['name'],
            'description' => $grade['description'],
        ], Material::grades($request->paramInt('id'))));
    }

    public function units(Request $request): Response
    {
        return $this->data(array_map(static fn (array $unit): array => [
            'id' => (int) $unit['id'],
            'code' => $unit['code'],
            'name' => $unit['name'],
            'kg_factor' => $unit['kg_factor'] !== null ? (float) $unit['kg_factor'] : null,
            'is_weight' => (int) $unit['is_weight'] === 1,
        ], Unit::active()));
    }

    public function states(Request $request): Response
    {
        return $this->data(array_map(static fn (array $state): array => [
            'id' => (int) $state['id'],
            'name' => $state['name'],
            'code' => $state['code'],
            'gst_state_code' => $state['gst_state_code'],
        ], State::active()));
    }

    public function cities(Request $request): Response
    {
        return $this->data(array_map(static fn (array $city): array => [
            'id' => (int) $city['id'],
            'name' => $city['name'],
            'slug' => $city['slug'],
        ], City::forState($request->paramInt('id'))));
    }

    public function searchCities(Request $request): Response
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return $this->data([]);
        }

        return $this->data(array_map(static fn (array $city): array => [
            'id' => (int) $city['id'],
            'name' => $city['name'],
            'state' => $city['state_name'],
            'label' => $city['name'] . ', ' . $city['state_name'],
        ], City::search($term, 15)));
    }

    /** Resolve a pincode to city/state so address forms can auto-fill. */
    public function pincode(Request $request): Response
    {
        $pincode = (string) $request->param('pincode');
        $row = City::resolveByPincode($pincode);

        if ($row === null) {
            return $this->error('That pincode is not in our database yet. Select the city manually.', 404);
        }

        return $this->data([
            'pincode' => $pincode,
            'city_id' => $row['city_id'] !== null ? (int) $row['city_id'] : null,
            'city' => $row['city_name'],
            'state_id' => $row['state_id'] !== null ? (int) $row['state_id'] : null,
            'state' => $row['state_name'],
            'area' => $row['area'],
        ]);
    }

    public function marketRates(Request $request): Response
    {
        $rates = MarketRate::latest(array_filter([
            'material_id' => $request->int('material_id'),
            'city_id' => $request->int('city_id'),
            'category_id' => $request->int('category_id'),
        ]), 100);

        return $this->data(array_map(static fn (array $rate): array => [
            'material' => $rate['material_name'],
            'material_slug' => $rate['material_slug'],
            'grade' => $rate['grade_name'],
            'city' => $rate['city_name'],
            'rate' => (float) $rate['rate'],
            'unit' => $rate['unit_code'],
            'date' => $rate['rate_date'],
            'change' => (float) $rate['change_amount'],
            'change_percent' => (float) $rate['change_percent'],
        ], $rates));
    }
}
