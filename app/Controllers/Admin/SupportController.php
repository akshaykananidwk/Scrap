<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Model;
use App\Core\Request;
use App\Core\Response;
use App\Core\Uploader;
use App\Services\DisputeService;
use App\Services\FraudService;
use App\Services\ReviewService;

final class SupportController extends Controller
{
    protected string $layout = 'layouts/admin';

    public function disputes(Request $request): Response
    {
        $filters = array_filter([
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'category' => (string) $request->query('category', ''),
        ], static fn ($v): bool => $v !== '');

        return $this->view('admin/disputes', [
            'title' => 'Disputes',
            'disputes' => DisputeService::paginate($filters, $request->page(), 25),
            'filters' => $filters,
            'counts' => DisputeService::counts(),
            'categories' => DisputeService::CATEGORIES,
        ]);
    }

    public function dispute(Request $request): Response
    {
        $dispute = DisputeService::detail($request->paramInt('id'));
        if ($dispute === null) {
            throw new HttpException(404, 'Dispute not found.');
        }

        return $this->view('admin/dispute_show', [
            'title' => 'Dispute ' . $dispute['reference'],
            'dispute' => $dispute,
            'messages' => DisputeService::messages((int) $dispute['id'], true),
            'order' => !empty($dispute['order_id']) ? \App\Models\Order::detail((int) $dispute['order_id']) : null,
            'categories' => DisputeService::CATEGORIES,
            'staff' => Database::instance()->select(
                "SELECT DISTINCT u.id, u.full_name FROM users u
                 INNER JOIN user_roles ur ON ur.user_id = u.id
                 INNER JOIN roles r ON r.id = ur.role_id
                 WHERE r.is_staff = 1 AND u.status = 'active' ORDER BY u.full_name"
            ),
        ]);
    }

    public function updateDispute(Request $request): Response
    {
        $result = DisputeService::changeStatus(
            $request->paramInt('id'),
            (string) $request->input('status', ''),
            $this->userId(),
            [
                'resolution' => $request->input('resolution'),
                'resolution_amount' => $request->input('resolution_amount'),
                'assigned_to' => $request->int('assigned_to') ?: null,
                'internal_notes' => $request->input('internal_notes'),
                'priority' => $request->input('priority'),
            ]
        );

        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : (string) $result['error']);
        return $this->back('/admin/disputes/' . $request->paramInt('id'));
    }

    public function replyDispute(Request $request): Response
    {
        $attachment = null;
        $file = $request->file('attachment');
        if ($file !== null) {
            $attachment = Uploader::documents('disputes')->store($file, 1600);
        }

        $result = DisputeService::addMessage(
            $request->paramInt('id'),
            $this->userId(),
            (string) $request->input('body', ''),
            $attachment,
            $request->bool('is_internal')
        );

        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : (string) $result['error']);
        return $this->back('/admin/disputes/' . $request->paramInt('id'));
    }

    public function reports(Request $request): Response
    {
        $filters = array_filter([
            'status' => (string) $request->query('status', 'open'),
            'type' => (string) $request->query('type', ''),
        ], static fn ($v): bool => $v !== '');

        return $this->view('admin/reports', [
            'title' => 'Content reports',
            'reports' => DisputeService::reports($filters, $request->page(), 25),
            'filters' => $filters,
            'reasons' => DisputeService::REPORT_REASONS,
            'counts' => DisputeService::counts(),
        ]);
    }

    public function handleReport(Request $request): Response
    {
        $result = DisputeService::handleReport(
            $request->paramInt('id'),
            $this->userId(),
            (string) $request->input('status', 'under_review'),
            (string) $request->input('notes', '')
        );

        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : (string) $result['error']);
        return $this->back('/admin/reports');
    }

    public function reviews(Request $request): Response
    {
        $status = (string) $request->query('status', '');
        $sql = 'SELECT r.*, o.reference AS order_reference,
                       reviewer.full_name AS reviewer_name, reviewee.full_name AS reviewee_name,
                       eb.name AS reviewee_business
                FROM reviews r
                INNER JOIN orders o ON o.id = r.order_id
                INNER JOIN users reviewer ON reviewer.id = r.reviewer_id
                INNER JOIN users reviewee ON reviewee.id = r.reviewee_id
                LEFT JOIN businesses eb ON eb.user_id = r.reviewee_id AND eb.deleted_at IS NULL
                WHERE 1 = 1';
        $count = 'SELECT COUNT(*) FROM reviews r WHERE 1 = 1';
        $params = [];

        if ($status !== '') {
            $sql .= ' AND r.status = :status';
            $count .= ' AND r.status = :status';
            $params['status'] = $status;
        }

        return $this->view('admin/reviews', [
            'title' => 'Reviews',
            'reviews' => Model::paginateQuery($sql . ' ORDER BY r.id DESC', $params, $request->page(), 25, $count),
            'filters' => ['status' => $status],
            'criteria' => ReviewService::CRITERIA,
        ]);
    }

    public function moderateReview(Request $request): Response
    {
        $result = ReviewService::moderate($request->paramInt('id'), (string) $request->input('status', 'hidden'));
        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : (string) $result['error']);
        return $this->back('/admin/reviews');
    }

    public function fraud(Request $request): Response
    {
        $filters = array_filter([
            'status' => (string) $request->query('status', 'open'),
            'severity' => (string) $request->query('severity', ''),
        ], static fn ($v): bool => $v !== '');

        return $this->view('admin/fraud', [
            'title' => 'Risk & fraud',
            'flags' => FraudService::paginate($filters, $request->page(), 30),
            'filters' => $filters,
            'counts' => FraudService::counts(),
            'rules' => FraudService::RULES,
            'high_risk' => Database::instance()->select(
                'SELECT u.id, u.full_name, u.mobile, u.risk_score, u.status, b.name AS business_name
                 FROM users u LEFT JOIN businesses b ON b.user_id = u.id AND b.deleted_at IS NULL
                 WHERE u.risk_score >= 30 AND u.deleted_at IS NULL
                 ORDER BY u.risk_score DESC LIMIT 20'
            ),
        ]);
    }

    public function resolveFlag(Request $request): Response
    {
        $result = FraudService::resolve(
            $request->paramInt('id'),
            $this->userId(),
            (string) $request->input('status', 'reviewed')
        );

        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : (string) $result['error']);
        return $this->back('/admin/fraud');
    }

    public function contactMessages(Request $request): Response
    {
        $status = (string) $request->query('status', '');
        $sql = 'SELECT * FROM contact_messages WHERE 1 = 1';
        $count = 'SELECT COUNT(*) FROM contact_messages WHERE 1 = 1';
        $params = [];

        if ($status !== '') {
            $sql .= ' AND status = :status';
            $count .= ' AND status = :status';
            $params['status'] = $status;
        }

        return $this->view('admin/contact_messages', [
            'title' => 'Contact messages',
            'messages' => Model::paginateQuery($sql . ' ORDER BY id DESC', $params, $request->page(), 30, $count),
            'filters' => ['status' => $status],
        ]);
    }
}
