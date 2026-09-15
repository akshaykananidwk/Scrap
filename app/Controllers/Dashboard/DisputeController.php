<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Uploader;
use App\Services\DisputeService;

final class DisputeController extends Controller
{
    protected string $layout = 'layouts/dashboard';

    public function index(Request $request): Response
    {
        return $this->view('dashboard/disputes', [
            'title' => 'Disputes',
            'disputes' => DisputeService::paginate([
                'user_id' => $this->userId(),
                'status' => (string) $request->query('status', ''),
            ], $request->page(), 15),
            'categories' => DisputeService::CATEGORIES,
        ]);
    }

    public function show(Request $request): Response
    {
        $dispute = $this->visibleDispute($request->paramInt('id'));

        return $this->view('dashboard/dispute_show', [
            'title' => 'Dispute ' . $dispute['reference'],
            'dispute' => $dispute,
            // Internal staff notes are never shown to the parties.
            'messages' => DisputeService::messages((int) $dispute['id'], false),
            'my_role' => (int) $dispute['raised_by'] === $this->userId() ? 'raiser' : 'respondent',
            'categories' => DisputeService::CATEGORIES,
        ]);
    }

    public function store(Request $request): Response
    {
        $orderId = $request->paramInt('id');

        $validator = $this->validate($request, [
            'category' => 'required|in:' . implode(',', array_keys(DisputeService::CATEGORIES)),
            'subject' => 'required|min:5|max:190',
            'description' => 'required|min:20|max:5000',
            'claimed_amount' => 'nullable|numeric|min_value:0',
        ], ['category' => 'Dispute category', 'subject' => 'Subject', 'description' => 'Description']);

        if ($validator->fails()) {
            return $validator->failResponse($request, '/dashboard/orders/' . $orderId);
        }

        $attachment = null;
        $file = $request->file('attachment');
        if ($file !== null) {
            $attachment = Uploader::documents('disputes')->store($file, 1600);
        }

        $result = DisputeService::raise($orderId, $this->userId(), [
            'category' => $request->input('category'),
            'subject' => $request->input('subject'),
            'description' => $request->input('description'),
            'claimed_amount' => $request->input('claimed_amount'),
            'attachment_path' => $attachment,
        ]);

        if ($result['ok']) {
            flash('success', $result['message']);
            return $this->redirect('/dashboard/disputes/' . $result['dispute_id']);
        }
        flash('danger', (string) $result['error']);
        return $this->back('/dashboard/orders/' . $orderId);
    }

    public function addMessage(Request $request): Response
    {
        $dispute = $this->visibleDispute($request->paramInt('id'));

        $attachment = null;
        $file = $request->file('attachment');
        if ($file !== null) {
            $attachment = Uploader::documents('disputes')->store($file, 1600);
        }

        $result = DisputeService::addMessage(
            (int) $dispute['id'],
            $this->userId(),
            (string) $request->input('body', ''),
            $attachment,
            false
        );

        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : (string) $result['error']);
        return $this->back('/dashboard/disputes/' . $dispute['id']);
    }

    private function visibleDispute(int $id): array
    {
        $dispute = DisputeService::detail($id);
        if ($dispute === null) {
            throw new HttpException(404, 'Dispute not found.');
        }
        $this->authorize(
            in_array($this->userId(), [(int) $dispute['raised_by'], (int) $dispute['against_user_id']], true) || Auth::isStaff(),
            'That dispute is between two other businesses.'
        );
        return $dispute;
    }
}
