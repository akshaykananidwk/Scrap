<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Order;
use App\Services\CommissionService;
use App\Services\InvoiceService;
use App\Services\OrderService;
use App\Services\PaymentService;

final class OrderController extends Controller
{
    protected string $layout = 'layouts/admin';

    public function index(Request $request): Response
    {
        $filters = array_filter([
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'payment_status' => (string) $request->query('payment_status', ''),
            'source_type' => (string) $request->query('source_type', ''),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'sort' => (string) $request->query('sort', 'newest'),
        ], static fn ($v): bool => $v !== '');

        return $this->view('admin/orders', [
            'title' => 'Orders',
            'orders' => Order::paginate($filters, $request->page(), 25),
            'filters' => $filters,
            'counts' => Order::counts(),
            'statuses' => Order::FLOW,
            'payment_statuses' => Order::PAYMENT_STATUSES,
        ]);
    }

    public function show(Request $request): Response
    {
        $order = Order::detail($request->paramInt('id'));
        if ($order === null) {
            throw new HttpException(404, 'Order not found.');
        }

        return $this->view('admin/order_show', [
            'title' => 'Order ' . $order['reference'],
            'order' => $order,
            'items' => Order::items((int) $order['id']),
            'history' => Order::history((int) $order['id']),
            'weighments' => Order::weighments((int) $order['id']),
            'deliveries' => Order::deliveries((int) $order['id']),
            'payments' => Order::payments((int) $order['id']),
            'commissions' => CommissionService::forOrder((int) $order['id']),
            'invoices' => InvoiceService::forOrder((int) $order['id']),
            'total_paid' => PaymentService::totalPaid((int) $order['id']),
            'statuses' => Order::FLOW,
            'next_statuses' => Order::FLOW[$order['status']][1] ?? [],
        ]);
    }

    public function changeStatus(Request $request): Response
    {
        $orderId = $request->paramInt('id');
        $status = (string) $request->input('status', '');
        $force = $request->bool('force');

        // Staff may override the state machine, but it is recorded as an override.
        if ($force) {
            $order = Order::find($orderId);
            if ($order === null) {
                throw new HttpException(404);
            }
            if (!isset(Order::FLOW[$status])) {
                return $this->fail('Unknown order status.');
            }
            \App\Core\Database::instance()->update('orders', ['status' => $status, 'updated_at' => now()], ['id' => $orderId]);
            \App\Core\Database::instance()->insert('order_status_history', [
                'order_id' => $orderId,
                'from_status' => $order['status'],
                'to_status' => $status,
                'changed_by' => $this->userId(),
                'notes' => 'Administrator override: ' . $request->input('notes', ''),
                'created_at' => now(),
            ]);
            \App\Services\AuditService::log('order_status_override', 'order', $orderId, ['status' => $order['status']], ['status' => $status]);
            flash('warning', 'Order status overridden to ' . label($status) . '.');
            return $this->back('/admin/orders/' . $orderId);
        }

        $result = OrderService::changeStatus($orderId, $status, $this->userId(), (string) $request->input('notes', ''));
        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : (string) $result['error']);
        return $this->back('/admin/orders/' . $orderId);
    }
}
