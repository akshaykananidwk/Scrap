<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use App\Models\MarketRate;
use App\Models\Material;

final class MarketRateController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = array_filter([
            'q' => trim((string) $request->query('q', '')),
            'category_id' => $request->int('category_id'),
            'city_id' => $request->int('city_id'),
            'material_id' => $request->int('material_id'),
        ], static fn ($v): bool => $v !== '' && $v !== 0);

        $rates = MarketRate::latest($filters, 120);

        // Group by material so the table reads material → city rows.
        $grouped = [];
        foreach ($rates as $rate) {
            $grouped[(string) $rate['material_name']][] = $rate;
        }

        return $this->view('market/index', [
            'title' => 'Scrap Rates Today — Live Market Prices in India',
            'meta_description' => 'Today\'s scrap rates for iron, copper, aluminium, brass, plastic and paper across Indian cities, with day-on-day change.',
            'canonical' => base_url('market-rates'),
            'rates' => $rates,
            'grouped' => $grouped,
            'filters' => $filters,
            'categories' => Category::tree(),
            'cities' => City::major(30),
            'movers' => MarketRate::movers(10),
            'updated_at' => $rates !== [] ? $rates[0]['rate_date'] : null,
        ]);
    }

    public function material(Request $request): Response
    {
        $material = Material::findBySlug((string) $request->param('slug'));
        if ($material === null) {
            throw new HttpException(404, 'That material does not exist.');
        }

        $cityId = $request->int('city_id') ?: null;
        $rates = MarketRate::latest(['material_id' => (int) $material['id']], 40);
        $history = MarketRate::history((int) $material['id'], $cityId, 30);

        // Fall back to the first city that has data so the chart is never empty.
        if ($history === [] && $rates !== []) {
            $cityId = $rates[0]['city_id'] !== null ? (int) $rates[0]['city_id'] : null;
            $history = MarketRate::history((int) $material['id'], $cityId, 30);
        }

        return $this->view('market/material', [
            'title' => $material['name'] . ' Rate Today — Price Trend & Live Listings',
            'meta_description' => 'Current ' . $material['name'] . ' scrap rate in Indian cities with a 30-day price trend and live listings.',
            'canonical' => base_url('market-rates/' . $material['slug']),
            'material' => $material,
            'rates' => $rates,
            'history' => $history,
            'selected_city' => $cityId,
            'cities' => City::major(30),
            'listings' => Listing::search(['material_id' => (int) $material['id'], 'sort' => 'newest'], 1, 6)->items,
        ]);
    }

    /** JSON series for the price chart. */
    public function history(Request $request): Response
    {
        $materialId = $request->paramInt('id');
        $cityId = $request->int('city_id') ?: null;
        $days = min(365, max(7, $request->int('days', 30)));

        $history = MarketRate::history($materialId, $cityId, $days);

        return $this->json([
            'success' => true,
            'material_id' => $materialId,
            'city_id' => $cityId,
            'days' => $days,
            'points' => array_map(static fn (array $row): array => [
                'date' => $row['rate_date'],
                'label' => gmdate('d M', strtotime((string) $row['rate_date'])),
                'rate' => (float) $row['rate'],
                'change_percent' => (float) $row['change_percent'],
            ], $history),
        ]);
    }
}
