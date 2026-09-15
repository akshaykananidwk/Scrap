<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Model;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\CommissionService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\WalletService;

final class FinanceController extends Controller
{
    protected string $layout = 'layouts/admin';

    public function payments(Request $request): Response
    {
        $filters = array_filter([
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'method' => (string) $request->query('method', ''),
        ], static fn ($v): bool => $v !== '');

        return $this->view('admin/payments', [
            'title' => 'Payments',
            'payments' => PaymentService::paginate($filters, $request->page(), 30),
            'filters' => $filters,
            'summary' => PaymentService::summary(),
            'methods' => PaymentService::METHODS,
            'gateway' => PaymentService::gateway(),
            'online_available' => PaymentService::onlineAvailable(),
        ]);
    }

    public function confirmPayment(Request $request): Response
    {
        $result = PaymentService::confirm($request->paramInt('id'), $this->userId());
        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : (string) $result['error']);
        return $this->back('/admin/payments');
    }

    public function commissions(Request $request): Response
    {
        $from = (string) $request->query('from', '');
        $to = (string) $request->query('to', '');
        $status = (string) $request->query('status', '');

        $sql = 'SELECT c.*, o.reference AS order_reference, u.full_name, b.name AS business_name
                FROM commissions c
                LEFT JOIN orders o ON o.id = c.order_id
                INNER JOIN users u ON u.id = c.user_id
                LEFT JOIN businesses b ON b.user_id = c.user_id AND b.deleted_at IS NULL
                WHERE 1 = 1';
        $count = 'SELECT COUNT(*) FROM commissions c WHERE 1 = 1';
        $params = [];

        if ($status !== '') {
            $sql .= ' AND c.status = :status';
            $count .= ' AND c.status = :status';
            $params['status'] = $status;
        }
        if ($from !== '') {
            $sql .= ' AND c.created_at >= :from';
            $count .= ' AND c.created_at >= :from';
            $params['from'] = $from . ' 00:00:00';
        }
        if ($to !== '') {
            $sql .= ' AND c.created_at <= :to';
            $count .= ' AND c.created_at <= :to';
            $params['to'] = $to . ' 23:59:59';
        }

        return $this->view('admin/commissions', [
            'title' => 'Commission ledger',
            'commissions' => Model::paginateQuery($sql . ' ORDER BY c.id DESC', $params, $request->page(), 30, $count),
            'filters' => ['from' => $from, 'to' => $to, 'status' => $status],
            'summary' => CommissionService::revenueSummary($from ?: null, $to ?: null),
            'by_type' => CommissionService::byFeeType($from ?: null, $to ?: null),
        ]);
    }

    public function updateCommission(Request $request): Response
    {
        $id = $request->paramInt('id');
        $status = (string) $request->input('status', '');
        if (!in_array($status, ['pending', 'invoiced', 'paid', 'waived', 'cancelled'], true)) {
            return $this->fail('Invalid commission status.');
        }

        $commission = Database::instance()->first('SELECT * FROM commissions WHERE id = :id', ['id' => $id]);
        if ($commission === null) {
            throw new HttpException(404);
        }

        Database::instance()->update('commissions', [
            'status' => $status,
            'notes' => $request->input('notes') ? substr((string) $request->input('notes'), 0, 255) : $commission['notes'],
            'updated_at' => now(),
        ], ['id' => $id]);

        AuditService::log('commission_status', 'commission', $id, ['status' => $commission['status']], ['status' => $status]);
        flash('success', 'Commission marked as ' . label($status) . '.');
        return $this->back('/admin/commissions');
    }

    public function invoices(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'type' => (string) $request->query('type', ''),
            'payment_status' => (string) $request->query('payment_status', ''),
        ];

        $sql = 'SELECT i.*, o.reference AS order_reference FROM invoices i
                LEFT JOIN orders o ON o.id = i.order_id WHERE 1 = 1';
        $count = 'SELECT COUNT(*) FROM invoices i WHERE 1 = 1';
        $params = [];

        if ($filters['q'] !== '') {
            $sql .= ' AND (i.invoice_number LIKE :q OR i.buyer_name LIKE :q2 OR i.seller_name LIKE :q3)';
            $count .= ' AND (i.invoice_number LIKE :q OR i.buyer_name LIKE :q2 OR i.seller_name LIKE :q3)';
            $params['q'] = '%' . $filters['q'] . '%';
            $params['q2'] = '%' . $filters['q'] . '%';
            $params['q3'] = '%' . $filters['q'] . '%';
        }
        if ($filters['type'] !== '') {
            $sql .= ' AND i.invoice_type = :type';
            $count .= ' AND i.invoice_type = :type';
            $params['type'] = $filters['type'];
        }
        if ($filters['payment_status'] !== '') {
            $sql .= ' AND i.payment_status = :ps';
            $count .= ' AND i.payment_status = :ps';
            $params['ps'] = $filters['payment_status'];
        }

        return $this->view('admin/invoices', [
            'title' => 'Invoices',
            'invoices' => Model::paginateQuery($sql . ' ORDER BY i.id DESC', $params, $request->page(), 30, $count),
            'filters' => $filters,
            'totals' => Database::instance()->first(
                "SELECT COUNT(*) AS total,
                        COALESCE(SUM(total), 0) AS value,
                        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END), 0) AS paid
                 FROM invoices WHERE cancelled_at IS NULL"
            ) ?? [],
        ]);
    }

    public function invoice(Request $request): Response
    {
        $invoice = InvoiceService::detail($request->paramInt('id'));
        if ($invoice === null) {
            throw new HttpException(404, 'Invoice not found.');
        }

        return $this->view('admin/invoice_show', [
            'title' => 'Invoice ' . $invoice['invoice_number'],
            'invoice' => $invoice,
        ]);
    }

    public function wallets(Request $request): Response
    {
        if (!WalletService::isEnabled()) {
            return $this->view('admin/wallets', [
                'title' => 'Wallets',
                'enabled' => false,
                'wallets' => null,
                'reconciliation' => ['checked' => 0, 'mismatches' => []],
            ]);
        }

        return $this->view('admin/wallets', [
            'title' => 'Wallets',
            'enabled' => true,
            'wallets' => Model::paginateQuery(
                'SELECT w.*, u.full_name, u.mobile, b.name AS business_name
                 FROM wallets w
                 INNER JOIN users u ON u.id = w.user_id
                 LEFT JOIN businesses b ON b.user_id = u.id AND b.deleted_at IS NULL
                 ORDER BY w.balance DESC',
                [],
                $request->page(),
                30,
                'SELECT COUNT(*) FROM wallets'
            ),
            'reconciliation' => WalletService::reconcileAll(),
            'totals' => Database::instance()->first(
                'SELECT COALESCE(SUM(balance), 0) AS balance, COALESCE(SUM(locked_balance), 0) AS locked FROM wallets'
            ) ?? [],
        ]);
    }

    public function adjustWallet(Request $request): Response
    {
        if (!WalletService::isEnabled()) {
            return $this->fail('Wallets are disabled in settings.');
        }

        $userId = $request->int('user_id');
        $amount = (string) $request->input('amount', '0');
        $direction = $request->input('direction') === 'debit' ? 'debit' : 'credit';
        $description = trim((string) $request->input('description', ''));

        if ($userId <= 0 || (float) $amount <= 0 || $description === '') {
            return $this->fail('User, amount and a description are all required for an adjustment.');
        }

        $result = WalletService::post($userId, $direction, 'adjustment', $amount, ['description' => $description]);
        if ($result['ok']) {
            AuditService::log('wallet_adjusted', 'user', $userId, null, [
                'direction' => $direction,
                'amount' => $amount,
                'description' => $description,
            ]);
        }

        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : (string) $result['error']);
        return $this->back('/admin/wallets');
    }
}
