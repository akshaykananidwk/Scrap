<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Model;
use App\Core\Paginator;
use App\Models\Order;

/**
 * Transport: transporters, vehicles and per-order deliveries with LR number,
 * e-way bill reference and proof of delivery.
 */
final class LogisticsService
{
    public const VEHICLE_TYPES = [
        'pickup' => 'Pickup (up to 1 MT)',
        'tata_ace' => 'Tata Ace / Chhota Hathi (1 MT)',
        'truck' => 'Truck (6–16 MT)',
        'trailer' => 'Trailer (20–30 MT)',
        'container' => 'Container',
        'tanker' => 'Tanker',
        'tipper' => 'Tipper / Dumper',
        'other' => 'Other',
    ];

    public const DELIVERY_STATUSES = [
        'assigned' => 'Assigned', 'scheduled' => 'Scheduled', 'picked_up' => 'Picked Up',
        'in_transit' => 'In Transit', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled',
    ];

    public static function createDelivery(int $orderId, int $createdBy, array $data): array
    {
        $order = Order::find($orderId);
        if ($order === null) {
            return ['ok' => false, 'error' => 'Order not found.'];
        }
        if (OrderService::role($order, $createdBy) === 'observer' && !\App\Core\Auth::isStaff()) {
            return ['ok' => false, 'error' => 'You are not part of this order.'];
        }

        $vehicleNumber = strtoupper(trim((string) ($data['vehicle_number'] ?? '')));
        if ($vehicleNumber === '') {
            return ['ok' => false, 'error' => 'Vehicle number is required.'];
        }

        $vehicleId = !empty($data['vehicle_id']) ? (int) $data['vehicle_id'] : null;
        if ($vehicleId === null) {
            $vehicleId = self::ensureVehicle($vehicleNumber, $data, $createdBy);
        }

        $deliveryId = Database::instance()->insert('deliveries', [
            'order_id' => $orderId,
            'transporter_id' => !empty($data['transporter_id']) ? (int) $data['transporter_id'] : null,
            'vehicle_id' => $vehicleId,
            'vehicle_number' => $vehicleNumber,
            'driver_name' => !empty($data['driver_name']) ? substr((string) $data['driver_name'], 0, 120) : null,
            'driver_mobile' => !empty($data['driver_mobile']) ? substr(preg_replace('/\D/', '', (string) $data['driver_mobile']) ?? '', -10) : null,
            'pickup_location' => $data['pickup_location'] ?? $order['pickup_address'],
            'delivery_location' => $data['delivery_location'] ?? $order['delivery_address'],
            'pickup_date' => $data['pickup_date'] ?? null,
            'delivery_date' => $data['delivery_date'] ?? null,
            'weight_kg' => !empty($data['weight_kg']) ? dec($data['weight_kg'], 3) : null,
            'lr_number' => !empty($data['lr_number']) ? substr((string) $data['lr_number'], 0, 60) : null,
            'eway_bill_number' => !empty($data['eway_bill_number']) ? substr((string) $data['eway_bill_number'], 0, 20) : null,
            'eway_bill_date' => $data['eway_bill_date'] ?? null,
            'freight_amount' => dec($data['freight_amount'] ?? 0, 2),
            'freight_paid_by' => $data['freight_paid_by'] ?? $order['transport_by'] ?? 'buyer',
            'status' => 'assigned',
            'notes' => !empty($data['notes']) ? substr((string) $data['notes'], 0, 255) : null,
            'created_by' => $createdBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Freight recorded on the delivery flows into the order total.
        if ((float) dec($data['freight_amount'] ?? 0, 2) > 0 && ($data['freight_paid_by'] ?? 'buyer') === 'buyer') {
            Database::instance()->update('orders', [
                'transport_charges' => dec((float) $order['transport_charges'] + (float) dec($data['freight_amount'], 2), 2),
                'updated_at' => now(),
            ], ['id' => $orderId]);
            OrderService::recalculate($orderId);
        }

        $notifyId = $createdBy === (int) $order['buyer_id'] ? (int) $order['seller_id'] : (int) $order['buyer_id'];
        NotificationService::dispatch($notifyId, 'delivery_update', [
            'body' => 'Vehicle ' . $vehicleNumber . ' assigned to order ' . $order['reference'] . '.',
            'link' => '/dashboard/orders/' . $orderId,
            'entity_type' => 'order',
            'entity_id' => $orderId,
            'vars' => ['order' => $order['reference'], 'status' => 'Assigned', 'vehicle' => $vehicleNumber],
        ]);

        AuditService::log('delivery_created', 'delivery', $deliveryId, null, ['order_id' => $orderId, 'vehicle' => $vehicleNumber]);
        return ['ok' => true, 'delivery_id' => $deliveryId, 'message' => 'Transport assigned.'];
    }

    private static function ensureVehicle(string $vehicleNumber, array $data, int $ownerId): ?int
    {
        $db = Database::instance();
        $existing = $db->first('SELECT id FROM vehicles WHERE vehicle_number = :v', ['v' => $vehicleNumber]);
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        try {
            return $db->insert('vehicles', [
                'transporter_id' => !empty($data['transporter_id']) ? (int) $data['transporter_id'] : null,
                'owner_user_id' => $ownerId,
                'vehicle_number' => $vehicleNumber,
                'vehicle_type' => isset(self::VEHICLE_TYPES[$data['vehicle_type'] ?? '']) ? $data['vehicle_type'] : 'truck',
                'capacity_kg' => !empty($data['capacity_kg']) ? dec($data['capacity_kg'], 2) : null,
                'driver_name' => !empty($data['driver_name']) ? substr((string) $data['driver_name'], 0, 120) : null,
                'driver_mobile' => !empty($data['driver_mobile']) ? substr(preg_replace('/\D/', '', (string) $data['driver_mobile']) ?? '', -10) : null,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function updateStatus(int $deliveryId, string $status, int $actorId, array $data = []): array
    {
        if (!isset(self::DELIVERY_STATUSES[$status])) {
            return ['ok' => false, 'error' => 'Invalid delivery status.'];
        }
        $delivery = Database::instance()->first('SELECT * FROM deliveries WHERE id = :id', ['id' => $deliveryId]);
        if ($delivery === null) {
            return ['ok' => false, 'error' => 'Delivery not found.'];
        }
        $order = Order::find((int) $delivery['order_id']);
        if ($order === null) {
            return ['ok' => false, 'error' => 'Order not found.'];
        }
        if (OrderService::role($order, $actorId) === 'observer' && !\App\Core\Auth::isStaff()) {
            return ['ok' => false, 'error' => 'You are not part of this order.'];
        }

        $update = ['status' => $status, 'updated_at' => now()];
        if ($status === 'picked_up' && empty($delivery['pickup_date'])) {
            $update['pickup_date'] = gmdate('Y-m-d');
        }
        if ($status === 'delivered') {
            $update['delivery_date'] = $data['delivery_date'] ?? gmdate('Y-m-d');
            if (!empty($data['pod_path'])) {
                $update['pod_path'] = $data['pod_path'];
                $update['pod_received_at'] = now();
            }
        }
        foreach (['lr_number', 'eway_bill_number', 'notes'] as $field) {
            if (!empty($data[$field])) {
                $update[$field] = substr((string) $data[$field], 0, 60);
            }
        }
        Database::instance()->update('deliveries', $update, ['id' => $deliveryId]);

        // Keep the order status in step with the transport reality.
        $orderStatusMap = [
            'picked_up' => 'picked_up',
            'in_transit' => 'in_transit',
            'delivered' => 'delivered',
        ];
        if (isset($orderStatusMap[$status]) && Order::canTransitionTo((string) $order['status'], $orderStatusMap[$status])) {
            OrderService::changeStatus((int) $order['id'], $orderStatusMap[$status], $actorId, 'Transport status: ' . label($status));
        }

        $notifyId = $actorId === (int) $order['buyer_id'] ? (int) $order['seller_id'] : (int) $order['buyer_id'];
        NotificationService::dispatch($notifyId, 'delivery_update', [
            'body' => 'Order ' . $order['reference'] . ' delivery is now ' . label($status) . '.',
            'link' => '/dashboard/orders/' . (int) $order['id'],
            'entity_type' => 'order',
            'entity_id' => (int) $order['id'],
            'vars' => ['order' => $order['reference'], 'status' => label($status), 'vehicle' => $delivery['vehicle_number'] ?? ''],
        ]);

        AuditService::log('delivery_status', 'delivery', $deliveryId, ['status' => $delivery['status']], ['status' => $status]);
        return ['ok' => true, 'message' => 'Delivery marked as ' . label($status) . '.'];
    }

    // ---- Transporters --------------------------------------------------------

    public static function saveTransporter(array $data, ?int $transporterId = null, ?int $userId = null): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $mobile = substr(preg_replace('/\D/', '', (string) ($data['mobile'] ?? '')) ?? '', -10);
        if ($name === '' || strlen($mobile) !== 10) {
            return ['ok' => false, 'error' => 'Transporter name and a valid 10-digit mobile number are required.'];
        }

        $row = [
            'user_id' => $userId,
            'name' => substr($name, 0, 150),
            'contact_person' => !empty($data['contact_person']) ? substr((string) $data['contact_person'], 0, 120) : null,
            'mobile' => $mobile,
            'alt_mobile' => !empty($data['alt_mobile']) ? substr(preg_replace('/\D/', '', (string) $data['alt_mobile']) ?? '', -10) : null,
            'email' => !empty($data['email']) ? substr((string) $data['email'], 0, 190) : null,
            'gstin' => !empty($data['gstin']) ? strtoupper(substr((string) $data['gstin'], 0, 15)) : null,
            'address' => !empty($data['address']) ? substr((string) $data['address'], 0, 255) : null,
            'city_name' => !empty($data['city_name']) ? substr((string) $data['city_name'], 0, 100) : null,
            'state_name' => !empty($data['state_name']) ? substr((string) $data['state_name'], 0, 100) : null,
            'service_areas' => !empty($data['service_areas']) ? substr((string) $data['service_areas'], 0, 255) : null,
            'is_active' => isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1,
            'updated_at' => now(),
        ];

        if ($transporterId !== null) {
            Database::instance()->update('transporters', $row, ['id' => $transporterId]);
            return ['ok' => true, 'transporter_id' => $transporterId, 'message' => 'Transporter updated.'];
        }
        $row['created_at'] = now();
        $id = Database::instance()->insert('transporters', $row);
        return ['ok' => true, 'transporter_id' => $id, 'message' => 'Transporter added.'];
    }

    public static function transporters(array $filters = [], ?int $page = null, int $perPage = 25): array|Paginator
    {
        $sql = 'SELECT t.*, (SELECT COUNT(*) FROM vehicles v WHERE v.transporter_id = t.id) AS vehicle_count
                FROM transporters t WHERE 1 = 1';
        $count = 'SELECT COUNT(*) FROM transporters t WHERE 1 = 1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (t.name LIKE :q OR t.mobile LIKE :q2 OR t.city_name LIKE :q3)';
            $count .= ' AND (t.name LIKE :q OR t.mobile LIKE :q2 OR t.city_name LIKE :q3)';
            $params['q'] = '%' . $filters['q'] . '%';
            $params['q2'] = '%' . $filters['q'] . '%';
            $params['q3'] = '%' . $filters['q'] . '%';
        }
        if (isset($filters['is_active'])) {
            $sql .= ' AND t.is_active = :active';
            $count .= ' AND t.is_active = :active';
            $params['active'] = (int) $filters['is_active'];
        }
        if (!empty($filters['user_id'])) {
            $sql .= ' AND t.user_id = :user_id';
            $count .= ' AND t.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }

        $sql .= ' ORDER BY t.is_verified DESC, t.name';
        if ($page === null) {
            return Database::instance()->select($sql . ' LIMIT 200', $params);
        }
        return Model::paginateQuery($sql, $params, $page, $perPage, $count);
    }

    public static function vehicles(?int $transporterId = null, ?int $ownerId = null): array
    {
        $sql = 'SELECT v.*, t.name AS transporter_name FROM vehicles v
                LEFT JOIN transporters t ON t.id = v.transporter_id WHERE v.is_active = 1';
        $params = [];
        if ($transporterId !== null) {
            $sql .= ' AND v.transporter_id = :t';
            $params['t'] = $transporterId;
        }
        if ($ownerId !== null) {
            $sql .= ' AND (v.owner_user_id = :o OR v.transporter_id IN (SELECT id FROM transporters WHERE user_id = :o2))';
            $params['o'] = $ownerId;
            $params['o2'] = $ownerId;
        }
        return Database::instance()->select($sql . ' ORDER BY v.vehicle_number LIMIT 200', $params);
    }

    public static function saveVehicle(array $data, ?int $vehicleId = null): array
    {
        $number = strtoupper(trim((string) ($data['vehicle_number'] ?? '')));
        if ($number === '') {
            return ['ok' => false, 'error' => 'Vehicle number is required.'];
        }

        $row = [
            'transporter_id' => !empty($data['transporter_id']) ? (int) $data['transporter_id'] : null,
            'owner_user_id' => !empty($data['owner_user_id']) ? (int) $data['owner_user_id'] : null,
            'vehicle_number' => substr($number, 0, 20),
            'vehicle_type' => isset(self::VEHICLE_TYPES[$data['vehicle_type'] ?? '']) ? $data['vehicle_type'] : 'truck',
            'capacity_kg' => !empty($data['capacity_kg']) ? dec($data['capacity_kg'], 2) : null,
            'driver_name' => !empty($data['driver_name']) ? substr((string) $data['driver_name'], 0, 120) : null,
            'driver_mobile' => !empty($data['driver_mobile']) ? substr(preg_replace('/\D/', '', (string) $data['driver_mobile']) ?? '', -10) : null,
            'driver_licence' => !empty($data['driver_licence']) ? substr((string) $data['driver_licence'], 0, 40) : null,
            'rc_number' => !empty($data['rc_number']) ? substr((string) $data['rc_number'], 0, 40) : null,
            'is_active' => isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1,
            'updated_at' => now(),
        ];

        try {
            if ($vehicleId !== null) {
                Database::instance()->update('vehicles', $row, ['id' => $vehicleId]);
                return ['ok' => true, 'vehicle_id' => $vehicleId, 'message' => 'Vehicle updated.'];
            }
            $row['created_at'] = now();
            $id = Database::instance()->insert('vehicles', $row);
            return ['ok' => true, 'vehicle_id' => $id, 'message' => 'Vehicle added.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'That vehicle number is already registered.'];
        }
    }
}
