<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\KycService;

final class KycController extends Controller
{
    protected string $layout = 'layouts/admin';

    public function index(Request $request): Response
    {
        $filters = array_filter([
            'status' => (string) $request->query('status', 'pending'),
            'q' => trim((string) $request->query('q', '')),
        ], static fn ($v): bool => $v !== '');

        return $this->view('admin/kyc', [
            'title' => 'KYC verification',
            'submissions' => KycService::paginate($filters, $request->page(), 20),
            'filters' => $filters,
            'counts' => KycService::counts(),
        ]);
    }

    public function show(Request $request): Response
    {
        $verification = $this->find($request->paramInt('id'));

        return $this->view('admin/kyc_show', [
            'title' => 'KYC ' . ($verification['business_name'] ?? $verification['full_name']),
            'verification' => $verification,
            'documents' => KycService::documents((int) $verification['business_id']),
            'doc_types' => KycService::DOC_TYPES,
            'required_docs' => KycService::REQUIRED_DOCS,
            'business' => \App\Models\Business::find((int) $verification['business_id']),
            'user' => \App\Models\User::find((int) $verification['user_id']),
            'gst_check' => !empty($verification['gstin'])
                ? KycService::verifyGstinOnline((string) $verification['gstin'])
                : null,
        ]);
    }

    public function approve(Request $request): Response
    {
        $result = KycService::approve(
            $request->paramInt('id'),
            $this->userId(),
            (string) $request->input('notes', '')
        );

        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : (string) $result['error']);
        return $this->redirect('/admin/kyc');
    }

    public function reject(Request $request): Response
    {
        $result = KycService::reject(
            $request->paramInt('id'),
            $this->userId(),
            (string) $request->input('reason', '')
        );

        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : (string) $result['error']);
        return $result['ok'] ? $this->redirect('/admin/kyc') : $this->back();
    }

    public function reviewDocument(Request $request): Response
    {
        $result = KycService::reviewDocument(
            $request->paramInt('id'),
            $this->userId(),
            (string) $request->input('status', 'verified'),
            (string) $request->input('notes', '')
        );

        if ($request->wantsJson()) {
            return $result['ok'] ? $this->ok($result, $result['message']) : $this->fail((string) $result['error']);
        }
        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : (string) $result['error']);
        return $this->back();
    }

    /**
     * Serve a KYC document through PHP rather than a public URL.
     * Uploads are already outside the executable path; this also makes every
     * document view auditable and restricted to KYC staff.
     */
    public function viewDocument(Request $request): Response
    {
        $document = Database::instance()->first(
            'SELECT * FROM business_documents WHERE id = :id',
            ['id' => $request->paramInt('id')]
        );
        if ($document === null) {
            throw new HttpException(404, 'Document not found.');
        }

        $base = realpath(UPLOAD_PATH);
        $path = realpath(UPLOAD_PATH . '/' . ltrim((string) $document['file_path'], '/'));
        if ($base === false || $path === false || !str_starts_with($path, $base) || !is_file($path)) {
            throw new HttpException(404, 'The document file is missing.');
        }

        \App\Services\AuditService::log('kyc_document_viewed', 'business_document', (int) $document['id']);

        $mime = (string) ($document['mime_type'] ?: 'application/octet-stream');
        return Response::download($path, (string) ($document['original_name'] ?: basename($path)), $mime);
    }

    private function find(int $id): array
    {
        $verification = Database::instance()->first(
            'SELECT k.*, u.full_name, u.mobile, u.email, u.created_at AS member_since, u.kyc_status,
                    b.name AS business_name, b.gstin, b.pan, b.city_name, b.state_name, b.address_line1,
                    b.business_type, b.registration_number,
                    r.full_name AS reviewer_name
             FROM kyc_verifications k
             INNER JOIN users u ON u.id = k.user_id
             LEFT JOIN businesses b ON b.id = k.business_id
             LEFT JOIN users r ON r.id = k.reviewed_by
             WHERE k.id = :id',
            ['id' => $id]
        );
        if ($verification === null) {
            throw new HttpException(404, 'KYC submission not found.');
        }
        return $verification;
    }
}
