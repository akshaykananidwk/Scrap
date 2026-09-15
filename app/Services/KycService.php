<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Model;
use App\Core\Paginator;
use App\Models\Business;
use App\Models\User;

/**
 * KYC verification.
 *
 * Manual admin review works today. GST/PAN verification APIs can be plugged in
 * later via verifyGstinOnline()/verifyPanOnline(); until credentials exist they
 * report "not configured" rather than silently claiming a document is verified.
 */
final class KycService
{
    public const DOC_TYPES = [
        'gst_certificate' => 'GST Certificate',
        'pan_card' => 'PAN Card',
        'business_registration' => 'Business Registration / Udyam',
        'shop_license' => 'Shop & Establishment Licence',
        'address_proof' => 'Address Proof',
        'bank_proof' => 'Cancelled Cheque / Bank Proof',
        'aadhaar' => 'Aadhaar (proprietor)',
        'other' => 'Other Supporting Document',
    ];

    public const REQUIRED_DOCS = ['gst_certificate', 'pan_card', 'business_registration', 'address_proof'];

    public static function documents(int $businessId): array
    {
        return Database::instance()->select(
            'SELECT d.*, u.full_name AS reviewer_name FROM business_documents d
             LEFT JOIN users u ON u.id = d.reviewed_by
             WHERE d.business_id = :b ORDER BY d.id DESC',
            ['b' => $businessId]
        );
    }

    public static function addDocument(int $businessId, int $userId, string $docType, string $filePath, array $meta = []): int
    {
        return Database::instance()->insert('business_documents', [
            'business_id' => $businessId,
            'user_id' => $userId,
            'doc_type' => $docType,
            'doc_number' => !empty($meta['doc_number']) ? substr((string) $meta['doc_number'], 0, 60) : null,
            'file_path' => $filePath,
            'original_name' => !empty($meta['original_name']) ? substr((string) $meta['original_name'], 0, 190) : null,
            'mime_type' => $meta['mime_type'] ?? null,
            'file_size' => (int) ($meta['file_size'] ?? 0),
            'status' => 'pending',
            'expires_at' => $meta['expires_at'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Submit the business for review once the required documents are present. */
    public static function submit(int $userId): array
    {
        $business = Business::forUser($userId);
        if ($business === null) {
            return ['ok' => false, 'error' => 'Complete your business profile first.'];
        }

        $uploaded = Database::instance()->select(
            'SELECT DISTINCT doc_type FROM business_documents WHERE business_id = :b',
            ['b' => (int) $business['id']]
        );
        $uploadedTypes = array_map(static fn (array $r): string => (string) $r['doc_type'], $uploaded);
        $missing = array_diff(self::REQUIRED_DOCS, $uploadedTypes);

        if ($missing !== []) {
            $labels = array_map(static fn (string $t): string => self::DOC_TYPES[$t] ?? $t, $missing);
            return ['ok' => false, 'error' => 'Please upload: ' . implode(', ', $labels) . '.'];
        }

        $db = Database::instance();
        $existing = $db->first(
            "SELECT id, status FROM kyc_verifications WHERE user_id = :u ORDER BY id DESC LIMIT 1",
            ['u' => $userId]
        );
        if ($existing !== null && in_array($existing['status'], ['pending', 'under_review'], true)) {
            return ['ok' => false, 'error' => 'Your KYC is already under review.'];
        }

        $verificationId = $db->insert('kyc_verifications', [
            'user_id' => $userId,
            'business_id' => (int) $business['id'],
            'status' => 'pending',
            'submitted_at' => now(),
            'verification_method' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        User::updateById($userId, ['kyc_status' => 'pending'], true);

        // Alert the KYC team.
        $reviewers = $db->select(
            "SELECT DISTINCT ur.user_id FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE r.slug IN ('super_admin','admin','kyc_manager')"
        );
        NotificationService::dispatchMany(
            array_map(static fn (array $r): int => (int) $r['user_id'], $reviewers),
            'kyc_submitted',
            [
                'body' => $business['name'] . ' submitted KYC documents for review.',
                'link' => '/admin/kyc/' . $verificationId,
                'entity_type' => 'kyc',
                'entity_id' => $verificationId,
                'channels' => [],
            ]
        );

        NotificationService::dispatch($userId, 'kyc_submitted', [
            'body' => 'Your documents are with our verification team. This usually takes up to two business days.',
            'link' => '/dashboard/kyc',
            'entity_type' => 'kyc',
            'entity_id' => $verificationId,
        ]);

        AuditService::log('kyc_submitted', 'kyc', $verificationId, null, ['business_id' => (int) $business['id']]);
        return ['ok' => true, 'verification_id' => $verificationId, 'message' => 'KYC submitted for review.'];
    }

    public static function approve(int $verificationId, int $reviewerId, string $notes = ''): array
    {
        $db = Database::instance();
        $verification = $db->first('SELECT * FROM kyc_verifications WHERE id = :id', ['id' => $verificationId]);
        if ($verification === null) {
            return ['ok' => false, 'error' => 'KYC record not found.'];
        }
        if ($verification['status'] === 'verified') {
            return ['ok' => false, 'error' => 'This KYC is already verified.'];
        }

        $db->transaction(static function (Database $db) use ($verification, $verificationId, $reviewerId, $notes): void {
            $db->update('kyc_verifications', [
                'status' => 'verified',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
                'internal_notes' => $notes !== '' ? $notes : null,
                'expires_at' => gmdate('Y-m-d', strtotime('+2 years')),
                'updated_at' => now(),
            ], ['id' => $verificationId]);

            $db->statement(
                "UPDATE business_documents SET status = 'verified', reviewed_by = :r, reviewed_at = :n, updated_at = :n2
                 WHERE business_id = :b AND status = 'pending'",
                ['r' => $reviewerId, 'n' => now(), 'n2' => now(), 'b' => (int) $verification['business_id']]
            );

            $db->update('businesses', [
                'kyc_verified' => 1,
                'gst_verified' => 1,
                'pan_verified' => 1,
                'updated_at' => now(),
            ], ['id' => (int) $verification['business_id']]);

            $db->update('users', [
                'kyc_status' => 'verified',
                'status' => 'active',
                'updated_at' => now(),
            ], ['id' => (int) $verification['user_id']]);
        });

        $business = \App\Models\Business::find((int) $verification['business_id']);
        $user = User::find((int) $verification['user_id']);

        NotificationService::dispatch((int) $verification['user_id'], 'kyc_approved', [
            'body' => 'Your business is now KYC verified. The verified badge is live on your profile and listings.',
            'link' => '/dashboard/kyc',
            'entity_type' => 'kyc',
            'entity_id' => $verificationId,
            'vars' => ['name' => $user['full_name'] ?? '', 'business' => $business['name'] ?? ''],
        ]);

        AuditService::log('kyc_approved', 'kyc', $verificationId, ['status' => $verification['status']], ['status' => 'verified']);
        return ['ok' => true, 'message' => 'KYC approved.'];
    }

    public static function reject(int $verificationId, int $reviewerId, string $reason): array
    {
        $db = Database::instance();
        $verification = $db->first('SELECT * FROM kyc_verifications WHERE id = :id', ['id' => $verificationId]);
        if ($verification === null) {
            return ['ok' => false, 'error' => 'KYC record not found.'];
        }
        if ($reason === '') {
            return ['ok' => false, 'error' => 'Give the applicant a reason for rejection.'];
        }

        $db->update('kyc_verifications', [
            'status' => 'rejected',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'rejection_reason' => substr($reason, 0, 255),
            'updated_at' => now(),
        ], ['id' => $verificationId]);

        $db->update('users', ['kyc_status' => 'rejected', 'updated_at' => now()], ['id' => (int) $verification['user_id']]);
        $db->update('businesses', ['kyc_verified' => 0, 'updated_at' => now()], ['id' => (int) $verification['business_id']]);

        $user = User::find((int) $verification['user_id']);
        $business = \App\Models\Business::find((int) $verification['business_id']);

        NotificationService::dispatch((int) $verification['user_id'], 'kyc_rejected', [
            'body' => 'KYC could not be verified. Reason: ' . $reason,
            'link' => '/dashboard/kyc',
            'entity_type' => 'kyc',
            'entity_id' => $verificationId,
            'vars' => [
                'name' => $user['full_name'] ?? '',
                'business' => $business['name'] ?? '',
                'reason' => $reason,
            ],
        ]);

        AuditService::log('kyc_rejected', 'kyc', $verificationId, null, ['reason' => $reason]);
        return ['ok' => true, 'message' => 'KYC rejected and the applicant has been told why.'];
    }

    public static function reviewDocument(int $documentId, int $reviewerId, string $status, string $notes = ''): array
    {
        if (!in_array($status, ['verified', 'rejected', 'pending', 'expired'], true)) {
            return ['ok' => false, 'error' => 'Invalid document status.'];
        }
        Database::instance()->update('business_documents', [
            'status' => $status,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'review_notes' => $notes !== '' ? substr($notes, 0, 255) : null,
            'updated_at' => now(),
        ], ['id' => $documentId]);
        AuditService::log('kyc_document_reviewed', 'business_document', $documentId, null, ['status' => $status]);
        return ['ok' => true, 'message' => 'Document marked as ' . label($status) . '.'];
    }

    public static function paginate(array $filters, int $page, int $perPage = 20): Paginator
    {
        $sql = 'SELECT k.*, u.full_name, u.mobile, u.email, b.name AS business_name, b.gstin, b.city_name,
                       (SELECT COUNT(*) FROM business_documents d WHERE d.business_id = k.business_id) AS document_count
                FROM kyc_verifications k
                INNER JOIN users u ON u.id = k.user_id
                LEFT JOIN businesses b ON b.id = k.business_id
                WHERE 1 = 1';
        $count = 'SELECT COUNT(*) FROM kyc_verifications k WHERE 1 = 1';
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= ' AND k.status = :status';
            $count .= ' AND k.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (u.full_name LIKE :q OR b.name LIKE :q2 OR b.gstin LIKE :q3 OR u.mobile LIKE :q4)';
            $params['q'] = '%' . $filters['q'] . '%';
            $params['q2'] = '%' . $filters['q'] . '%';
            $params['q3'] = '%' . $filters['q'] . '%';
            $params['q4'] = '%' . $filters['q'] . '%';
        }

        return Model::paginateQuery($sql . ' ORDER BY k.id DESC', $params, $page, $perPage, $count);
    }

    public static function statusFor(int $userId): array
    {
        $business = Business::forUser($userId);
        $verification = Database::instance()->first(
            'SELECT * FROM kyc_verifications WHERE user_id = :u ORDER BY id DESC LIMIT 1',
            ['u' => $userId]
        );
        $documents = $business !== null ? self::documents((int) $business['id']) : [];

        $uploadedTypes = array_map(static fn (array $d): string => (string) $d['doc_type'], $documents);
        $missing = array_values(array_diff(self::REQUIRED_DOCS, $uploadedTypes));

        return [
            'business' => $business,
            'verification' => $verification,
            'documents' => $documents,
            'missing' => $missing,
            'can_submit' => $business !== null && $missing === []
                && ($verification === null || !in_array($verification['status'], ['pending', 'under_review', 'verified'], true)),
            'status' => $verification['status'] ?? 'none',
        ];
    }

    /**
     * GST verification API hook.
     * Returns "not configured" until an operator supplies a provider — it never
     * fakes a verification result.
     */
    public static function verifyGstinOnline(string $gstin): array
    {
        if (!\App\Core\Validator::validGstin($gstin)) {
            return ['ok' => false, 'verified' => false, 'error' => 'GSTIN format/checksum is invalid.'];
        }
        if (!SettingsService::configured('gst_api_url', 'gst_api_key')) {
            return [
                'ok' => false,
                'verified' => false,
                'error' => 'No GST verification provider is configured. The format and checksum are valid; a human still needs to review the certificate.',
            ];
        }
        return ['ok' => false, 'verified' => false, 'error' => 'GST provider integration is not implemented on this installation.'];
    }

    public static function counts(): array
    {
        $row = Database::instance()->first(
            "SELECT COUNT(*) AS total,
                    SUM(status = 'pending') AS pending,
                    SUM(status = 'under_review') AS under_review,
                    SUM(status = 'verified') AS verified,
                    SUM(status = 'rejected') AS rejected
             FROM kyc_verifications"
        ) ?? [];
        return array_map(static fn ($v) => (int) $v, $row);
    }
}
