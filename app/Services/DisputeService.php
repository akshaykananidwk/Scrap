<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Model;
use App\Core\Paginator;
use App\Models\Order;

/**
 * Disputes and content reports.
 *
 *   Open → Under Review → Evidence Requested → Resolved / Rejected / Escalated
 */
final class DisputeService
{
    public const CATEGORIES = [
        'wrong_material' => 'Wrong material supplied',
        'weight_mismatch' => 'Weight mismatch',
        'quality_issue' => 'Quality / contamination issue',
        'payment_issue' => 'Payment not received / incorrect',
        'non_delivery' => 'Material not delivered / not lifted',
        'fraud' => 'Suspected fraud',
        'damage' => 'Damage in transit',
        'other' => 'Other',
    ];

    public const REPORT_REASONS = [
        'fake_listing' => 'Fake or misleading listing',
        'fake_buyer' => 'Fake buyer',
        'fake_seller' => 'Fake seller',
        'fraud' => 'Fraud / scam attempt',
        'wrong_material' => 'Material does not match the listing',
        'weight_mismatch' => 'Weight mismatch',
        'payment_issue' => 'Payment issue',
        'abuse' => 'Abusive behaviour',
        'spam' => 'Spam',
        'other' => 'Something else',
    ];

    public static function raise(int $orderId, int $raisedBy, array $data): array
    {
        $order = Order::find($orderId);
        if ($order === null) {
            return ['ok' => false, 'error' => 'Order not found.'];
        }
        $role = OrderService::role($order, $raisedBy);
        if ($role === 'observer') {
            return ['ok' => false, 'error' => 'You are not part of this order.'];
        }

        $category = (string) ($data['category'] ?? 'other');
        if (!isset(self::CATEGORIES[$category])) {
            return ['ok' => false, 'error' => 'Choose a valid dispute category.'];
        }
        $subject = trim((string) ($data['subject'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        if ($subject === '' || $description === '') {
            return ['ok' => false, 'error' => 'A subject and a description are required.'];
        }

        $existing = Database::instance()->first(
            "SELECT id FROM disputes WHERE order_id = :o AND raised_by = :u AND status NOT IN ('resolved','rejected','closed')",
            ['o' => $orderId, 'u' => $raisedBy]
        );
        if ($existing !== null) {
            return ['ok' => false, 'error' => 'You already have an open dispute on this order.'];
        }

        $againstId = $role === 'buyer' ? (int) $order['seller_id'] : (int) $order['buyer_id'];
        $db = Database::instance();

        $disputeId = $db->transaction(static function (Database $db) use ($orderId, $raisedBy, $againstId, $category, $subject, $description, $data): int {
            $id = $db->insert('disputes', [
                'reference' => 'DSP' . gmdate('ymd') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)),
                'order_id' => $orderId,
                'raised_by' => $raisedBy,
                'against_user_id' => $againstId,
                'category' => $category,
                'subject' => substr($subject, 0, 190),
                'description' => $description,
                'claimed_amount' => dec($data['claimed_amount'] ?? 0, 2),
                'status' => 'open',
                'priority' => (float) dec($data['claimed_amount'] ?? 0, 2) > 100000 ? 'high' : 'normal',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $db->insert('dispute_messages', [
                'dispute_id' => $id,
                'user_id' => $raisedBy,
                'body' => $description,
                'attachment_path' => $data['attachment_path'] ?? null,
                'is_internal' => 0,
                'created_at' => now(),
            ]);

            $db->update('orders', ['status' => 'disputed', 'updated_at' => now()], ['id' => $orderId]);
            $db->insert('order_status_history', [
                'order_id' => $orderId,
                'from_status' => null,
                'to_status' => 'disputed',
                'changed_by' => $raisedBy,
                'notes' => 'Dispute raised: ' . substr($subject, 0, 180),
                'created_at' => now(),
            ]);

            return $id;
        });

        NotificationService::dispatch($againstId, 'dispute_update', [
            'body' => 'A dispute was raised on order ' . $order['reference'] . ': ' . $subject,
            'link' => '/dashboard/disputes/' . $disputeId,
            'entity_type' => 'dispute',
            'entity_id' => $disputeId,
        ]);

        $staff = Database::instance()->select(
            "SELECT DISTINCT ur.user_id FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id
             WHERE r.slug IN ('super_admin','admin','support_manager')"
        );
        NotificationService::dispatchMany(
            array_map(static fn (array $r): int => (int) $r['user_id'], $staff),
            'dispute_update',
            [
                'body' => 'New dispute on order ' . $order['reference'],
                'link' => '/admin/disputes/' . $disputeId,
                'entity_type' => 'dispute',
                'entity_id' => $disputeId,
                'channels' => [],
            ]
        );

        AuditService::log('dispute_raised', 'dispute', $disputeId, null, ['order_id' => $orderId, 'category' => $category]);
        return ['ok' => true, 'dispute_id' => $disputeId, 'message' => 'Dispute raised. Our support team will review it.'];
    }

    public static function addMessage(int $disputeId, int $userId, string $body, ?string $attachment = null, bool $internal = false): array
    {
        $dispute = Database::instance()->first('SELECT * FROM disputes WHERE id = :id', ['id' => $disputeId]);
        if ($dispute === null) {
            return ['ok' => false, 'error' => 'Dispute not found.'];
        }
        $isParty = in_array($userId, [(int) $dispute['raised_by'], (int) $dispute['against_user_id']], true);
        if (!$isParty && !\App\Core\Auth::isStaff()) {
            return ['ok' => false, 'error' => 'You cannot post on this dispute.'];
        }
        if (trim($body) === '') {
            return ['ok' => false, 'error' => 'Write a message first.'];
        }
        if ($internal && !\App\Core\Auth::isStaff()) {
            $internal = false;
        }

        Database::instance()->insert('dispute_messages', [
            'dispute_id' => $disputeId,
            'user_id' => $userId,
            'body' => $body,
            'attachment_path' => $attachment,
            'is_internal' => $internal ? 1 : 0,
            'created_at' => now(),
        ]);
        Database::instance()->update('disputes', ['updated_at' => now()], ['id' => $disputeId]);

        if (!$internal) {
            $notifyId = $userId === (int) $dispute['raised_by'] ? (int) $dispute['against_user_id'] : (int) $dispute['raised_by'];
            NotificationService::dispatch($notifyId, 'dispute_update', [
                'body' => 'New message on dispute ' . $dispute['reference'] . '.',
                'link' => '/dashboard/disputes/' . $disputeId,
                'entity_type' => 'dispute',
                'entity_id' => $disputeId,
            ]);
        }

        return ['ok' => true, 'message' => 'Message posted.'];
    }

    public static function changeStatus(int $disputeId, string $status, int $actorId, array $data = []): array
    {
        $valid = ['open', 'under_review', 'evidence_requested', 'resolved', 'rejected', 'escalated', 'closed'];
        if (!in_array($status, $valid, true)) {
            return ['ok' => false, 'error' => 'Invalid dispute status.'];
        }
        $dispute = Database::instance()->first('SELECT * FROM disputes WHERE id = :id', ['id' => $disputeId]);
        if ($dispute === null) {
            return ['ok' => false, 'error' => 'Dispute not found.'];
        }

        $update = ['status' => $status, 'updated_at' => now()];
        if (in_array($status, ['resolved', 'rejected'], true)) {
            $update['resolution'] = $data['resolution'] ?? null;
            $update['resolution_amount'] = dec($data['resolution_amount'] ?? 0, 2);
            $update['resolved_by'] = $actorId;
            $update['resolved_at'] = now();
        }
        if (!empty($data['assigned_to'])) {
            $update['assigned_to'] = (int) $data['assigned_to'];
        }
        if (!empty($data['internal_notes'])) {
            $update['internal_notes'] = (string) $data['internal_notes'];
        }
        if (!empty($data['priority'])) {
            $update['priority'] = (string) $data['priority'];
        }

        Database::instance()->update('disputes', $update, ['id' => $disputeId]);

        // A resolved dispute releases the order back to its normal flow.
        if (in_array($status, ['resolved', 'rejected'], true) && !empty($dispute['order_id'])) {
            $open = (int) Database::instance()->scalar(
                "SELECT COUNT(*) FROM disputes WHERE order_id = :o AND status NOT IN ('resolved','rejected','closed')",
                ['o' => (int) $dispute['order_id']],
                0
            );
            if ($open === 0) {
                Database::instance()->update('orders', ['status' => 'payment_pending', 'updated_at' => now()], ['id' => (int) $dispute['order_id']]);
                Database::instance()->insert('order_status_history', [
                    'order_id' => (int) $dispute['order_id'],
                    'from_status' => 'disputed',
                    'to_status' => 'payment_pending',
                    'changed_by' => $actorId,
                    'notes' => 'Dispute ' . $dispute['reference'] . ' ' . $status,
                    'created_at' => now(),
                ]);
            }
        }

        foreach ([(int) $dispute['raised_by'], (int) $dispute['against_user_id']] as $userId) {
            NotificationService::dispatch($userId, 'dispute_update', [
                'body' => 'Dispute ' . $dispute['reference'] . ' is now ' . label($status) . '.',
                'link' => '/dashboard/disputes/' . $disputeId,
                'entity_type' => 'dispute',
                'entity_id' => $disputeId,
                'vars' => ['dispute' => $dispute['reference'], 'status' => label($status)],
            ]);
        }

        AuditService::log('dispute_status', 'dispute', $disputeId, ['status' => $dispute['status']], ['status' => $status]);
        return ['ok' => true, 'message' => 'Dispute marked as ' . label($status) . '.'];
    }

    public static function detail(int $disputeId): ?array
    {
        return Database::instance()->first(
            'SELECT d.*, o.reference AS order_reference, o.final_amount,
                    raiser.full_name AS raised_by_name, against.full_name AS against_name,
                    rb.name AS raised_by_business, ab.name AS against_business,
                    assignee.full_name AS assignee_name
             FROM disputes d
             LEFT JOIN orders o ON o.id = d.order_id
             INNER JOIN users raiser ON raiser.id = d.raised_by
             INNER JOIN users against ON against.id = d.against_user_id
             LEFT JOIN businesses rb ON rb.user_id = d.raised_by AND rb.deleted_at IS NULL
             LEFT JOIN businesses ab ON ab.user_id = d.against_user_id AND ab.deleted_at IS NULL
             LEFT JOIN users assignee ON assignee.id = d.assigned_to
             WHERE d.id = :id LIMIT 1',
            ['id' => $disputeId]
        );
    }

    public static function messages(int $disputeId, bool $includeInternal = false): array
    {
        $sql = 'SELECT m.*, u.full_name, b.name AS business_name FROM dispute_messages m
                INNER JOIN users u ON u.id = m.user_id
                LEFT JOIN businesses b ON b.user_id = m.user_id AND b.deleted_at IS NULL
                WHERE m.dispute_id = :d';
        if (!$includeInternal) {
            $sql .= ' AND m.is_internal = 0';
        }
        return Database::instance()->select($sql . ' ORDER BY m.id', ['d' => $disputeId]);
    }

    public static function paginate(array $filters, int $page, int $perPage = 20): Paginator
    {
        $sql = 'SELECT d.*, o.reference AS order_reference,
                       raiser.full_name AS raised_by_name, against.full_name AS against_name
                FROM disputes d
                LEFT JOIN orders o ON o.id = d.order_id
                INNER JOIN users raiser ON raiser.id = d.raised_by
                INNER JOIN users against ON against.id = d.against_user_id
                WHERE 1 = 1';
        $count = 'SELECT COUNT(*) FROM disputes d WHERE 1 = 1';
        $params = [];

        if (!empty($filters['user_id'])) {
            $sql .= ' AND (d.raised_by = :u OR d.against_user_id = :u2)';
            $count .= ' AND (d.raised_by = :u OR d.against_user_id = :u2)';
            $params['u'] = (int) $filters['user_id'];
            $params['u2'] = (int) $filters['user_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND d.status = :status';
            $count .= ' AND d.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['category'])) {
            $sql .= ' AND d.category = :category';
            $count .= ' AND d.category = :category';
            $params['category'] = $filters['category'];
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (d.reference LIKE :q OR d.subject LIKE :q2)';
            $count .= ' AND (d.reference LIKE :q OR d.subject LIKE :q2)';
            $params['q'] = '%' . $filters['q'] . '%';
            $params['q2'] = '%' . $filters['q'] . '%';
        }

        return Model::paginateQuery($sql . ' ORDER BY FIELD(d.priority, "urgent","high","normal","low"), d.id DESC', $params, $page, $perPage, $count);
    }

    // ---- Content reports (fake listing, abusive user, spam …) ----------------

    public static function report(int $reporterId, string $type, int $targetId, string $reason, string $details = '', ?string $evidence = null): array
    {
        $validTypes = ['listing', 'user', 'business', 'auction', 'message', 'requirement', 'rfq'];
        if (!in_array($type, $validTypes, true) || !isset(self::REPORT_REASONS[$reason])) {
            return ['ok' => false, 'error' => 'Invalid report.'];
        }

        $existing = Database::instance()->first(
            "SELECT id FROM content_reports WHERE reporter_id = :u AND reportable_type = :t AND reportable_id = :i AND status = 'open'",
            ['u' => $reporterId, 't' => $type, 'i' => $targetId]
        );
        if ($existing !== null) {
            return ['ok' => false, 'error' => 'You have already reported this. Our team is reviewing it.'];
        }

        $reportId = Database::instance()->insert('content_reports', [
            'reporter_id' => $reporterId,
            'reportable_type' => $type,
            'reportable_id' => $targetId,
            'reason' => $reason,
            'details' => $details !== '' ? $details : null,
            'evidence_path' => $evidence,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AuditService::log('content_reported', $type, $targetId, null, ['reason' => $reason]);
        return ['ok' => true, 'report_id' => $reportId, 'message' => 'Thank you. Our moderation team will review this.'];
    }

    public static function handleReport(int $reportId, int $actorId, string $status, string $notes = ''): array
    {
        if (!in_array($status, ['open', 'under_review', 'action_taken', 'dismissed'], true)) {
            return ['ok' => false, 'error' => 'Invalid status.'];
        }
        Database::instance()->update('content_reports', [
            'status' => $status,
            'handled_by' => $actorId,
            'handled_at' => now(),
            'admin_notes' => $notes !== '' ? $notes : null,
            'updated_at' => now(),
        ], ['id' => $reportId]);
        AuditService::log('report_handled', 'content_report', $reportId, null, ['status' => $status]);
        return ['ok' => true, 'message' => 'Report marked as ' . label($status) . '.'];
    }

    public static function reports(array $filters, int $page, int $perPage = 25): Paginator
    {
        $sql = 'SELECT r.*, u.full_name AS reporter_name FROM content_reports r
                INNER JOIN users u ON u.id = r.reporter_id WHERE 1 = 1';
        $count = 'SELECT COUNT(*) FROM content_reports r WHERE 1 = 1';
        $params = [];
        if (!empty($filters['status'])) {
            $sql .= ' AND r.status = :status';
            $count .= ' AND r.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['type'])) {
            $sql .= ' AND r.reportable_type = :type';
            $count .= ' AND r.reportable_type = :type';
            $params['type'] = $filters['type'];
        }
        return Model::paginateQuery($sql . ' ORDER BY r.id DESC', $params, $page, $perPage, $count);
    }

    public static function counts(): array
    {
        $db = Database::instance();
        return [
            'open_disputes' => (int) $db->scalar("SELECT COUNT(*) FROM disputes WHERE status NOT IN ('resolved','rejected','closed')", [], 0),
            'total_disputes' => (int) $db->scalar('SELECT COUNT(*) FROM disputes', [], 0),
            'open_reports' => (int) $db->scalar("SELECT COUNT(*) FROM content_reports WHERE status = 'open'", [], 0),
        ];
    }
}
