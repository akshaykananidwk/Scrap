<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Models\Rfq;
use App\Services\RfqService;

final class RfqController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = array_filter([
            'q' => trim((string) $request->query('q', '')),
            'category_id' => $request->int('category_id'),
            'status' => (string) $request->query('status', '') ?: ['open', 'closing_soon'],
            'public_only' => Auth::check() ? 0 : 1,
            'invited_seller_id' => Auth::id(),
        ], static fn ($v): bool => $v !== '' && $v !== 0 && $v !== null);

        $rfqs = Rfq::paginate($filters, $request->page(), 20);

        return $this->view('rfq/index', [
            'title' => 'Open RFQs — Request for Quotation',
            'meta_description' => 'Live requests for quotation from verified scrap buyers. Submit your quote and win the order.',
            'rfqs' => $rfqs,
            'filters' => $filters,
            'categories' => Category::tree(),
            'statuses' => Rfq::STATUSES,
        ]);
    }

    public function show(Request $request): Response
    {
        $rfq = Rfq::detail($request->paramInt('id'));
        if ($rfq === null) {
            throw new HttpException(404, 'That RFQ does not exist.');
        }

        $userId = Auth::id();
        $isBuyer = $userId === (int) $rfq['buyer_id'];

        // Invite-only RFQs are visible to the buyer, invited sellers and staff.
        if ($rfq['visibility'] === 'invited' && !$isBuyer && !Auth::isStaff()) {
            $invited = $userId !== null && Database::instance()->first(
                'SELECT id FROM rfq_invites WHERE rfq_id = :r AND seller_id = :u',
                ['r' => (int) $rfq['id'], 'u' => $userId]
            ) !== null;
            if (!$invited) {
                throw new HttpException(403, 'This RFQ is open to invited suppliers only.');
            }
        }

        $myQuote = null;
        if ($userId !== null && !$isBuyer) {
            $myQuote = Database::instance()->first(
                'SELECT * FROM rfq_quotes WHERE rfq_id = :r AND seller_id = :u',
                ['r' => (int) $rfq['id'], 'u' => $userId]
            );
            if ($myQuote !== null) {
                $myQuote['items'] = Rfq::quoteItems((int) $myQuote['id']);
            }
            Database::instance()->statement(
                "UPDATE rfq_invites SET status = 'viewed', viewed_at = :n
                 WHERE rfq_id = :r AND seller_id = :u AND status = 'invited'",
                ['n' => now(), 'r' => (int) $rfq['id'], 'u' => $userId]
            );
        }

        $canQuote = $userId !== null
            && !$isBuyer
            && in_array($rfq['status'], ['open', 'closing_soon'], true)
            && ($rfq['closes_at'] === null || strtotime((string) $rfq['closes_at'] . ' UTC') > time());

        return $this->view('rfq/show', [
            'title' => 'RFQ ' . $rfq['reference'] . ' — ' . $rfq['title'],
            'meta_description' => mb_substr(strip_tags((string) $rfq['description']), 0, 250),
            'rfq' => $rfq,
            'items' => Rfq::items((int) $rfq['id']),
            'is_buyer' => $isBuyer,
            'my_quote' => $myQuote,
            'can_quote' => $canQuote,
            // Quote values stay private between each seller and the buyer.
            'quote_count' => (int) $rfq['quote_count'],
        ]);
    }

    public function submitQuote(Request $request): Response
    {
        $rfqId = $request->paramInt('id');

        $lines = [];
        foreach ($request->array('items') as $itemId => $line) {
            if (!is_array($line) || empty($line['rate'])) {
                continue;
            }
            $lines[] = [
                'rfq_item_id' => (int) $itemId,
                'offered_quantity' => $line['offered_quantity'] ?? 0,
                'rate' => $line['rate'],
                'gst_rate' => $line['gst_rate'] ?? 18,
                'remarks' => $line['remarks'] ?? null,
            ];
        }

        if ($lines === []) {
            $message = 'Enter a rate for at least one line item.';
            if ($request->wantsJson()) {
                return $this->fail($message);
            }
            flash('danger', $message);
            return $this->back();
        }

        $attachmentPath = null;
        $file = $request->file('attachment');
        if ($file !== null) {
            $uploader = \App\Core\Uploader::documents('rfq');
            $attachmentPath = $uploader->store($file, null);
            if ($attachmentPath === null) {
                flash('danger', (string) $uploader->firstError());
                return $this->back();
            }
        }

        $result = RfqService::submitQuote($rfqId, (int) Auth::id(), $lines, [
            'delivery_included' => $request->bool('delivery_included'),
            'delivery_charges' => $request->input('delivery_charges', 0),
            'delivery_days' => $request->int('delivery_days') ?: null,
            'payment_terms' => $request->input('payment_terms'),
            'validity_days' => $request->int('validity_days', 7),
            'notes' => $request->input('notes'),
            'attachment_path' => $attachmentPath,
        ]);

        if ($request->wantsJson()) {
            return $result['ok'] ? $this->ok($result, $result['message']) : $this->fail($result['error']);
        }

        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : $result['error']);
        return $this->redirect('/rfq/' . $rfqId);
    }
}
