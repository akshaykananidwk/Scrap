<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\InstallService;
use App\Services\SettingsService;

final class ApiController extends BaseApiController
{
    public function index(Request $request): Response
    {
        return $this->data([
            'name' => (string) SettingsService::get('site_name', 'ScrapX') . ' API',
            'version' => 'v1',
            'application_version' => InstallService::readVersion(),
            'authentication' => 'Bearer token — create one in Dashboard → Security.',
            'endpoints' => [
                'auth' => ['POST /api/v1/auth/login', 'POST /api/v1/auth/register', 'GET /api/v1/auth/me'],
                'catalog' => ['GET /api/v1/categories', 'GET /api/v1/units', 'GET /api/v1/states', 'GET /api/v1/market-rates'],
                'listings' => ['GET /api/v1/listings', 'GET /api/v1/listings/{id}', 'POST /api/v1/listings'],
                'auctions' => ['GET /api/v1/auctions', 'GET /api/v1/auctions/{id}/state', 'POST /api/v1/bids'],
                'requirements' => ['GET /api/v1/requirements', 'POST /api/v1/requirements'],
                'offers' => ['GET /api/v1/offers', 'POST /api/v1/offers'],
                'orders' => ['GET /api/v1/orders', 'GET /api/v1/orders/{id}'],
                'notifications' => ['GET /api/v1/notifications'],
            ],
        ]);
    }

    public function health(Request $request): Response
    {
        $databaseOk = true;
        try {
            Database::instance()->scalar('SELECT 1');
        } catch (\Throwable) {
            $databaseOk = false;
        }

        return Response::json([
            'success' => $databaseOk,
            'data' => [
                'status' => $databaseOk ? 'ok' : 'degraded',
                'database' => $databaseOk,
                'version' => InstallService::readVersion(),
                'server_time' => now(),
                'maintenance_mode' => SettingsService::bool('maintenance_mode', false),
            ],
        ], $databaseOk ? 200 : 503);
    }
}
