<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Uploader;
use App\Models\Order;
use App\Services\CommissionService;
use App\Services\InvoiceService;
use App\Services\LogisticsService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\ReviewService;
use App\Services\WeighmentService;

final class OrderController extends Controller
{
    protected string $layout = 'layouts/dashboard';

    public function index(Request $request): Response
    {
        $filters = array_filter([
            'user_id' => $this->userId(),
            'status' => (string) $request->query('status', ''),
            'payment_status' => (string) $request->query('payment_status', ''),
            'q' => trim((string) $request->query('q', '')),
            'sort' => (string) $request->query('sort', 'newest'),
        ], static fn ($v): bool => $v !== '' && $v !== 0);

        // Role tab: buying vs selling.
        $role = (string) $request->query('role', '');
        if ($role === 'buying') {
            unset($filters['user_id']);
            $filters['buyer_id'] = $this->userId();
        } elseif ($role === 'selling') {
            unset($filters['user_id']);
            $filters['seller_id'] = $this->userId();
        }

        return $this->view('order/index', [
            'title' => 'Orders',
            'orders' => Order::paginate($filters, $request->page(), 20),
            'filters' => $filters,
            'role' => $role,
            'statuses' => Order::FLOW,
            'payment_statuses' => Order::PAYMENT_STATUSES,
            'counts' => $this->counts(),
        ]);
    }

    public function show(Request $request): Response
    {
        $order = $this->visibleOrder($request->paramInt('id'));
        $userId = $this->userId();
        $role = OrderService::role($order, $userId);

        return $this->view('order/show', [
            'title' => 'Order ' . $order['reference'],
            'order' => $order,
            'items' => Order::items((int) $order['id']),
            'history' => Order::history((int) $order['id']),
            'weighments' => Order::weighments((int) $order['id']),
            'deliveries' => Order::deliveries((int) $order['id']),
            'payments' => Order::payments((int) $order['id']),
            'commissions' => CommissionService::forOrder((int) $order['id']),
            'invoices' => InvoiceService::forOrder((int) $order['id']),
            'my_role' => $role,
            'next_statuses' => Order::FLOW[$order['status']][1] ?? [],
            'amount_due' => dec((float) $order['final_amount'] - (float) PaymentService::totalPaid((int) $order['id']), 2),
            'total_paid' => PaymentService::totalPaid((int) $order['id']),
            'payment_methods' => PaymentService::METHODS,
            'online_payment' => PaymentService::onlineAvailable(),
            'can_review' => ReviewService::canReview($order, $userId),
            'my_review' => Database::instance()->first(
                'SELECT * FROM reviews WHERE order_id = :o AND reviewer_id = :u',
                ['o' => (int) $order['id'], 'u' => $userId]
            ),
            'transporters' => LogisticsService::transporters(['is_active' => 1]),
            'vehicles' => LogisticsService::vehicles(null, $userId),
            'vehicle_types' => LogisticsService::VEHICLE_TYPES,
            'dispute_categories' => \App\Services\DisputeService::CATEGORIES,
            'rate_per_kg' => WeighmentService::ratePerKg($order),
        ]);
    }

    public function changeStatus(Request $request): Response
    {
        $order = $this->visibleOrder($request->paramInt('id'));
        $status = (string) $request->input('status', '');

        $result = OrderService::changeStatus(
            (int) $order['id'],
            $status,
            $this->userId(),
            (string) $request->input('notes', '')
        );

        if ($request->wantsJson()) {
            return $result['ok'] ? $this->ok($result, $result['message']) : $this->fail($result['error']);
        }
        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : $result['error']);
        return $this->back('/dashboard/orders/' . $order['id']);
    }

    /** Seller adjusts transport/loading/other charges or a discount. */
    public function updateCharges(Request $request): Response
    {
        $order = $this->visibleOrder($request->paramInt('id'));
        if (OrderService::role($order, $this->userId()) !== 'seller' && !Auth::isStaff()) {
            return $this->fail('Only the seller can change order charges.');
        }
        if (in_array($order['status'], ['completed', 'cancelled'], true)) {
            return $this->fail('This order is closed and cannot be changed.');
        }

        Database::instance()->update('orders', [
            'transport_charges' => dec($request->input('transport_charges', 0), 2),
            'loading_charges' => dec($request->input('loading_charges', 0), 2),
            'other_charges' => dec($request->input('other_charges', 0), 2),
            'discount' => dec($request->input('discount', 0), 2),
            'updated_at' => now(),
        ], ['id' => (int) $order['id']]);

        $amounts = OrderService::recalculate((int) $order['id']);
        \App\Services\AuditService::log('order_charges_updated', 'order', (int) $order['id'], null, $amounts);

        flash('success', 'Charges updated. Order total is now ' . money($amounts['total']) . '.');
        return $this->back('/dashboard/orders/' . $order['id']);
    }

    // ------------------------------------------------------------ weighment --

    public function recordWeighment(Request $request): Response
    {
        $order = $this->visibleOrder($request->paramInt('id'));

        $validator = $this->validate($request, [
            'actual_weight_kg' => 'required|numeric|gt:0',
            'gross_weight_kg' => 'nullable|numeric|min_value:0',
            'tare_weight_kg' => 'nullable|numeric|min_value:0',
            'deduction_amount' => 'nullable|numeric|min_value:0',
            'vehicle_number' => 'nullable|vehicle',
        ], ['actual_weight_kg' => 'Actual weight']);
        if ($validator->fails()) {
            return $validator->failResponse($request, '/dashboard/orders/' . $order['id']);
        }

        $slipPath = null;
        $file = $request->file('slip');
        if ($file !== null) {
            $uploader = Uploader::documents('weighment');
            $slipPath = $uploader->store($file, 1600);
            if ($slipPath === null) {
                flash('warning', (string) $uploader->firstError());
            }
        }
        $photoPath = null;
        $photo = $request->file('photo');
        if ($photo !== null) {
            $photoPath = Uploader::images('weighment')->store($photo, 1600);
        }

        $result = WeighmentService::record((int) $order['id'], [
            'actual_weight_kg' => $request->input('actual_weight_kg'),
            'gross_weight_kg' => $request->input('gross_weight_kg'),
            'tare_weight_kg' => $request->input('tare_weight_kg'),
            'weighbridge_name' => $request->input('weighbridge_name'),
            'slip_number' => $request->input('slip_number'),
            'slip_path' => $slipPath,
            'photo_path' => $photoPath,
            'operator_name' => $request->input('operator_name'),
            'vehicle_number' => $request->input('vehicle_number'),
            'weighed_at' => to_utc((string) $request->input('weighed_at', '')) ?? now(),
            'deduction_amount' => $request->input('deduction_amount'),
            'deduction_reason' => $request->input('deduction_reason'),
            'delivery_id' => $request->int('delivery_id') ?: null,
        ], $this->userId());

        if ($request->wantsJson()) {
            return $result['ok'] ? $this->ok($result, $result['message']) : $this->fail((string) $result['error']);
        }

        if ($result['ok']) {
            // Move the order into the weighment stage when the flow allows it.
            if (Order::canTransitionTo((string) $order['status'], 'weighment')) {
                OrderService::changeStatus((int) $order['id'], 'weighment', $this->userId(), 'Weighment recorded');
            }
            flash('success', sprintf(
                '%s Settlement: %s (actual %s KG vs expected %s KG).',
                $result['message'],
                money($result['settled_amount']),
                $result['actual_kg'],
                $result['expected_kg']
            ));
        } else {
            flash('danger', (string) $result['error']);
        }
        return $this->back('/dashboard/orders/' . $order['id']);
    }

    public function previewWeighment(Request $request): Response
    {
        $order = $this->visibleOrder($request->paramInt('id'));
        return $this->json([
            'success' => true,
            'preview' => WeighmentService::preview(
                $order,
                (string) $request->input('actual_weight_kg', '0'),
                (string) $request->input('deduction_amount', '0')
            ),
        ]);
    }

    public function acceptWeighment(Request $request): Response
    {
        $result = WeighmentService::accept($request->paramInt('id'), $this->userId());
        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : $result['error']);
        return $this->back();
    }

    // ------------------------------------------------------------- delivery --

    public function createDelivery(Request $request): Response
    {
        $order = $this->visibleOrder($request->paramInt('id'));

        $validator = $this->validate($request, [
            'vehicle_number' => 'required|vehicle',
            'driver_mobile' => 'nullable|mobile',
            'freight_amount' => 'nullable|numeric|min_value:0',
        ], ['vehicle_number' => 'Vehicle number']);
        if ($validator->fails()) {
            return $validator->failResponse($request, '/dashboard/orders/' . $order['id']);
        }

        $result = LogisticsService::createDelivery((int) $order['id'], $this->userId(), $request->all());
        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : $result['error']);
        return $this->back('/dashboard/orders/' . $order['id']);
    }

    public function updateDelivery(Request $request): Response
    {
        $deliveryId = $request->paramInt('id');
        $data = $request->all();

        $podPath = null;
        $pod = $request->file('pod');
        if ($pod !== null) {
            $podPath = Uploader::documents('pod')->store($pod, 1600);
            $data['pod_path'] = $podPath;
        }

        $result = LogisticsService::updateStatus(
            $deliveryId,
            (string) $request->input('status', ''),
            $this->userId(),
            $data
        );

        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : $result['error']);
        return $this->back();
    }

    // -------------------------------------------------------------- payment --

    public function recordPayment(Request $request): Response
    {
        $order = $this->visibleOrder($request->paramInt('id'));

        $validator = $this->validate($request, [
            'amount' => 'required|numeric|gt:0',
            'method' => 'required|in:' . implode(',', array_keys(PaymentService::METHODS)),
        ], ['amount' => 'Payment amount', 'method' => 'Payment method']);
        if ($validator->fails()) {
            return $validator->failResponse($request, '/dashboard/orders/' . $order['id']);
        }

        $proofPath = null;
        $proof = $request->file('proof');
        if ($proof !== null) {
            $proofPath = Uploader::documents('payments')->store($proof, 1600);
        }

        $result = PaymentService::record((int) $order['id'], $this->userId(), [
            'amount' => $request->input('amount'),
            'method' => $request->input('method'),
            'utr_number' => $request->input('utr_number'),
            'proof_path' => $proofPath,
            'notes' => $request->input('notes'),
        ]);

        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : $result['error']);
        return $this->back('/dashboard/orders/' . $order['id']);
    }

    public function confirmPayment(Request $request): Response
    {
        $result = PaymentService::confirm($request->paramInt('id'), $this->userId());
        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : $result['error']);
        return $this->back();
    }

    public function payments(Request $request): Response
    {
        return $this->view('order/payments', [
            'title' => 'Payments',
            'payments' => PaymentService::paginate([
                'user_id' => $this->userId(),
                'status' => (string) $request->query('status', ''),
            ], $request->page(), 25),
            'methods' => PaymentService::METHODS,
        ]);
    }

    // -------------------------------------------------------------- invoice --

    public function generateInvoice(Request $request): Response
    {
        $order = $this->visibleOrder($request->paramInt('id'));
        if (OrderService::role($order, $this->userId()) !== 'seller' && !Auth::isStaff()) {
            return $this->fail('Only the seller can raise the invoice.');
        }

        $result = InvoiceService::generateForOrder((int) $order['id'], $this->userId());
        if ($result['ok']) {
            flash('success', $result['message']);
            return $this->redirect('/dashboard/invoices/' . $result['invoice_id']);
        }
        flash('danger', (string) $result['error']);
        return $this->back('/dashboard/orders/' . $order['id']);
    }

    public function invoice(Request $request): Response
    {
        $invoice = $this->visibleInvoice($request->paramInt('id'));
        return $this->view('order/invoice', [
            'title' => 'Invoice ' . $invoice['invoice_number'],
            'invoice' => $invoice,
        ]);
    }

    /** Print-ready invoice (browser "Save as PDF" works without a PDF library). */
    public function printInvoice(Request $request): Response
    {
        $invoice = $this->visibleInvoice($request->paramInt('id'));
        return Response::html(\App\Core\View::render('order/invoice_print', [
            'invoice' => $invoice,
            'title' => 'Invoice ' . $invoice['invoice_number'],
        ], 'layouts/print'));
    }

    // -------------------------------------------------------------- helpers --

    private function visibleOrder(int $id): array
    {
        $order = Order::detail($id);
        if ($order === null) {
            throw new HttpException(404, 'Order not found.');
        }
        $this->authorize(
            OrderService::canView($order, Auth::user()),
            'That order belongs to two other businesses.'
        );
        return $order;
    }

    private function visibleInvoice(int $id): array
    {
        $invoice = InvoiceService::detail($id);
        if ($invoice === null) {
            throw new HttpException(404, 'Invoice not found.');
        }
        $this->authorize(
            in_array($this->userId(), [(int) $invoice['seller_id'], (int) $invoice['buyer_id']], true) || Auth::isStaff(),
            'That invoice belongs to another business.'
        );
        return $invoice;
    }

    private function counts(): array
    {
        $userId = $this->userId();
        $rows = Database::instance()->select(
            'SELECT status, COUNT(*) AS total FROM orders WHERE buyer_id = :u OR seller_id = :u2 GROUP BY status',
            ['u' => $userId, 'u2' => $userId]
        );
        $counts = ['all' => 0];
        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
            $counts['all'] += (int) $row['total'];
        }
        $counts['buying'] = (int) Database::instance()->scalar('SELECT COUNT(*) FROM orders WHERE buyer_id = :u', ['u' => $userId], 0);
        $counts['selling'] = (int) Database::instance()->scalar('SELECT COUNT(*) FROM orders WHERE seller_id = :u', ['u' => $userId], 0);
        return $counts;
    }
}
