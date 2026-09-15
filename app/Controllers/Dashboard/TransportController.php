<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\LogisticsService;

final class TransportController extends Controller
{
    protected string $layout = 'layouts/dashboard';

    public function index(Request $request): Response
    {
        $userId = $this->userId();

        return $this->view('dashboard/transport', [
            'title' => 'Transport & vehicles',
            'transporters' => LogisticsService::transporters(['user_id' => $userId]),
            'all_transporters' => LogisticsService::transporters(['is_active' => 1]),
            'vehicles' => LogisticsService::vehicles(null, $userId),
            'vehicle_types' => LogisticsService::VEHICLE_TYPES,
            'deliveries' => Database::instance()->select(
                'SELECT d.*, o.reference AS order_reference, o.status AS order_status,
                        t.name AS transporter_name
                 FROM deliveries d
                 INNER JOIN orders o ON o.id = d.order_id
                 LEFT JOIN transporters t ON t.id = d.transporter_id
                 WHERE o.buyer_id = :u OR o.seller_id = :u2
                 ORDER BY d.id DESC LIMIT 50',
                ['u' => $userId, 'u2' => $userId]
            ),
            'statuses' => LogisticsService::DELIVERY_STATUSES,
        ]);
    }

    public function storeTransporter(Request $request): Response
    {
        $result = LogisticsService::saveTransporter(
            $request->all(),
            $request->int('transporter_id') ?: null,
            $this->userId()
        );

        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : (string) $result['error']);
        return $this->back('/dashboard/transport');
    }

    public function storeVehicle(Request $request): Response
    {
        $data = $request->all();
        $data['owner_user_id'] = $this->userId();

        $result = LogisticsService::saveVehicle($data, $request->int('vehicle_id') ?: null);
        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : (string) $result['error']);
        return $this->back('/dashboard/transport');
    }
}
