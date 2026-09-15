<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Uploader;
use App\Services\KycService;

final class KycController extends Controller
{
    protected string $layout = 'layouts/dashboard';

    public function index(Request $request): Response
    {
        $status = KycService::statusFor($this->userId());

        return $this->view('dashboard/kyc', array_merge($status, [
            'title' => 'KYC verification',
            'doc_types' => KycService::DOC_TYPES,
            'required_docs' => KycService::REQUIRED_DOCS,
        ]));
    }

    public function upload(Request $request): Response
    {
        $status = KycService::statusFor($this->userId());
        if ($status['business'] === null) {
            flash('danger', 'Complete your business profile before uploading documents.');
            return $this->redirect('/dashboard/business');
        }

        $docType = (string) $request->input('doc_type', '');
        if (!isset(KycService::DOC_TYPES[$docType])) {
            flash('danger', 'Choose a valid document type.');
            return $this->back('/dashboard/kyc');
        }

        $file = $request->file('document');
        if ($file === null) {
            flash('danger', 'Select a file to upload.');
            return $this->back('/dashboard/kyc');
        }

        $uploader = Uploader::documents('kyc');
        $path = $uploader->store($file, 2000);
        if ($path === null) {
            flash('danger', (string) $uploader->firstError());
            return $this->back('/dashboard/kyc');
        }

        KycService::addDocument(
            (int) $status['business']['id'],
            $this->userId(),
            $docType,
            $path,
            [
                'doc_number' => $request->input('doc_number'),
                'original_name' => $file['name'] ?? null,
                'mime_type' => $file['type'] ?? null,
                'file_size' => (int) ($file['size'] ?? 0),
            ]
        );

        flash('success', KycService::DOC_TYPES[$docType] . ' uploaded.');
        return $this->redirect('/dashboard/kyc');
    }

    public function submit(Request $request): Response
    {
        $result = KycService::submit($this->userId());
        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : $result['error']);
        return $this->redirect('/dashboard/kyc');
    }

    public function deleteDocument(Request $request): Response
    {
        $documentId = $request->paramInt('id');
        $document = Database::instance()->first(
            'SELECT * FROM business_documents WHERE id = :id AND user_id = :u',
            ['id' => $documentId, 'u' => $this->userId()]
        );
        if ($document === null) {
            throw new HttpException(404, 'Document not found.');
        }
        if ($document['status'] === 'verified') {
            flash('danger', 'A verified document cannot be removed. Contact support if it needs replacing.');
            return $this->back('/dashboard/kyc');
        }

        Uploader::delete((string) $document['file_path']);
        Database::instance()->delete('business_documents', ['id' => $documentId]);

        flash('success', 'Document removed.');
        return $this->redirect('/dashboard/kyc');
    }
}
