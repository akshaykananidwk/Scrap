<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Order;
use App\Services\CommissionService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\WeighmentService;

final class OrderApiController extends BaseApiController
{
    public function index(Request $request): Response
    {
        $filters = ['user_id' => Auth::id()];
        $role = (string) $request->query('role', '');
        if ($role === 'buying') {
            $filters = ['buyer_id' => Auth::id()];
        } elseif ($role === 'selling') {
            $filters = ['seller_id' => Auth::id()];
        }
        if ($request->query('status')) {
            $filters['status'] = (string) $request->query('status');
        }

        return $this->paginated(
            Order::paginate($filters, $request->page(), min(50, max(5, $request->int('per_page', 20)))),
            fn (array $order): array => $this->presentOrder($order)
        );
    }

    public function show(Request $request): Response
    {
        $order = Order::detail($request->paramInt('id'));
        if ($order === null) {
            return $this->error('Order not found.', 404);
        }
        if (!OrderService::canView($order, Auth::user())) {
            return $this->error('That order belongs to two other businesses.', 403);
        }

        $data = $this->presentOrder($order);
        $data['items'] = array_map(static fn (array $item): array => [
            'description' => $item['description'],
            'quantity' => (float) $item['quantity'],
            'unit' => $item['unit_code'],
            'rate' => (float) $item['rate'],
            'amount' => (float) $item['amount'],
            'gst_rate' => (float) $item['gst_rate'],
        ], Order::items((int) $order['id']));

        $data['history'] = array_map(static fn (array $row): array => [
            'from' => $row['from_status'],
            'to' => $row['to_status'],
            'by' => $row['full_name'],
            'notes' => $row['notes'],
            'at' => $row['created_at'],
        ], Order::history((int) $order['id']));

        $data['weighments'] = array_map(static fn (array $row): array => [
            'expected_kg' => (float) $row['expected_weight_kg'],
            'actual_kg' => (float) $row['actual_weight_kg'],
            'difference_kg' => (float) $row['difference_kg'],
            'difference_percent' => (float) $row['difference_percent'],
            'settled_amount' => (float) $row['settled_amount'],
            'slip_number' => $row['slip_number'],
            'status' => $row['status'],
            'weighed_at' => $row['weighed_at'],
        ], Order::weighments((int) $order['id']));

        $data['deliveries'] = array_map(static fn (array $row): array => [
            'vehicle_number' => $row['vehicle_number'],
            'driver_name' => $row['driver_name'],
            'status' => $row['status'],
            'lr_number' => $row['lr_number'],
            'eway_bill_number' => $row['eway_bill_number'],
            'pickup_date' => $row['pickup_date'],
            'delivery_date' => $row['delivery_date'],
        ], Order::deliveries((int) $order['id']));

        $data['payments'] = array_map(static fn (array $row): array => [
            'reference' => $row['reference'],
            'amount' => (float) $row['amount'],
            'method' => $row['method'],
            'status' => $row['status'],
            'utr_number' => $row['utr_number'],
            'paid_at' => $row['paid_at'],
        ], Order::payments((int) $order['id']));

        $data['amount_due'] = (float) $order['final_amount'] - (float) PaymentService::totalPaid((int) $order['id']);
        $data['commission_total'] = (float) CommissionService::totalForOrder((int) $order['id']);
        $data['next_statuses'] = Order::FLOW[$order['status']][1] ?? [];

        return $this->data($data);
    }

    public function changeStatus(Request $request): Response
    {
        $order = Order::find($request->paramInt('id'));
        if ($order === null) {
            return $this->error('Order not found.', 404);
        }
        if (OrderService::role($order, (int) Auth::id()) === 'observer' && !Auth::isStaff()) {
            return $this->error('You are not part of this order.', 403);
        }

        $result = OrderService::changeStatus(
            (int) $order['id'],
            (string) $request->input('status', ''),
            (int) Auth::id(),
            (string) $request->input('notes', '')
        );

        return $result['ok']
            ? $this->data(['status' => $result['status'], 'message' => $result['message']])
            : $this->error((string) $result['error']);
    }

    public function weighment(Request $request): Response
    {
        $order = Order::find($request->paramInt('id'));
        if ($order === null) {
            return $this->error('Order not found.', 404);
        }
        if (OrderService::role($order, (int) Auth::id()) === 'observer' && !Auth::isStaff()) {
            return $this->error('You are not part of this order.', 403);
        }

        $result = WeighmentService::record((int) $order['id'], [
            'actual_weight_kg' => (string) $request->input('actual_weight_kg', '0'),
            'gross_weight_kg' => $request->input('gross_weight_kg'),
            'tare_weight_kg' => $request->input('tare_weight_kg'),
            'weighbridge_name' => $request->input('weighbridge_name'),
            'slip_number' => $request->input('slip_number'),
            'operator_name' => $request->input('operator_name'),
            'vehicle_number' => $request->input('vehicle_number'),
            'deduction_amount' => $request->input('deduction_amount'),
            'deduction_reason' => $request->input('deduction_reason'),
        ], (int) Auth::id());

        return $result['ok'] ? $this->data($result) : $this->error((string) $result['error']);
    }
}
