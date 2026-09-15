<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Listing;
use App\Models\Unit;
use App\Services\OfferService;

final class OfferController extends Controller
{
    protected string $layout = 'layouts/dashboard';

    public function index(Request $request): Response
    {
        $filters = array_filter([
            'user_id' => $this->userId(),
            'role' => (string) $request->query('role', ''),
            'status' => (string) $request->query('status', ''),
        ], static fn ($v): bool => $v !== '' && $v !== 0);

        return $this->view('dashboard/offers', [
            'title' => 'Offers',
            'offers' => OfferService::paginate($filters, $request->page(), 20),
            'filters' => $filters,
            'statuses' => OfferService::STATUSES,
        ]);
    }

    public function show(Request $request): Response
    {
        $offer = $this->visibleOffer($request->paramInt('id'));

        return $this->view('dashboard/offer_show', [
            'title' => 'Offer ' . $offer['reference'],
            'offer' => $offer,
            'thread' => OfferService::thread((int) $offer['id']),
            'my_role' => (int) $offer['buyer_id'] === $this->userId() ? 'buyer' : 'seller',
            'can_respond' => $this->canRespond($offer),
        ]);
    }

    public function store(Request $request): Response
    {
        $listingId = $request->int('listing_id');
        $listing = Listing::find($listingId);
        if ($listing === null) {
            return $this->respond($request, false, 'That listing is no longer available.');
        }

        $validator = $this->validate($request, [
            'amount' => 'required|numeric|gt:0',
            'quantity' => 'required|numeric|gt:0',
            'message' => 'nullable|max:2000',
        ], ['amount' => 'Offer amount', 'quantity' => 'Quantity']);

        if ($validator->fails()) {
            return $validator->failResponse($request);
        }

        $result = OfferService::create([
            'listing_id' => $listingId,
            'buyer_id' => $this->userId(),
            'seller_id' => (int) $listing['user_id'],
            'created_by' => $this->userId(),
            'amount' => $request->input('amount'),
            'price_basis' => (string) $request->input('price_basis', 'per_mt'),
            'quantity' => $request->input('quantity'),
            'unit_id' => (int) $listing['unit_id'],
            'gst_included' => $request->bool('gst_included'),
            'transport_included' => $request->bool('transport_included'),
            'payment_terms' => (string) $request->input('payment_terms', $listing['payment_terms']),
            'message' => $request->input('message'),
        ]);

        if ($request->wantsJson()) {
            return $result['ok'] ? $this->ok($result, $result['message']) : $this->fail($result['error']);
        }
        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : $result['error']);
        return $result['ok'] ? $this->redirect('/dashboard/offers/' . $result['offer_id']) : $this->back();
    }

    public function accept(Request $request): Response
    {
        $result = OfferService::accept($request->paramInt('id'), $this->userId());

        if ($request->wantsJson()) {
            return $result['ok'] ? $this->ok($result, $result['message']) : $this->fail($result['error']);
        }
        if ($result['ok']) {
            flash('success', $result['message']);
            return $this->redirect('/dashboard/orders/' . $result['order_id']);
        }
        flash('danger', $result['error']);
        return $this->back('/dashboard/offers');
    }

    public function reject(Request $request): Response
    {
        $result = OfferService::reject(
            $request->paramInt('id'),
            $this->userId(),
            (string) $request->input('reason', '')
        );
        return $this->respond($request, $result['ok'], $result['ok'] ? $result['message'] : $result['error']);
    }

    public function cancel(Request $request): Response
    {
        $result = OfferService::cancel($request->paramInt('id'), $this->userId());
        return $this->respond($request, $result['ok'], $result['ok'] ? $result['message'] : $result['error']);
    }

    /** Counter an offer — creates a new offer in the opposite direction. */
    public function counter(Request $request): Response
    {
        $offer = $this->visibleOffer($request->paramInt('id'));

        if ($offer['status'] !== 'pending') {
            return $this->respond($request, false, 'This offer is ' . label((string) $offer['status']) . ' and cannot be countered.');
        }
        if (!$this->canRespond($offer)) {
            return $this->respond($request, false, 'Only the recipient can counter this offer.');
        }

        $validator = $this->validate($request, [
            'amount' => 'required|numeric|gt:0',
            'quantity' => 'required|numeric|gt:0',
            'message' => 'nullable|max:2000',
        ], ['amount' => 'Counter amount']);
        if ($validator->fails()) {
            return $validator->failResponse($request);
        }

        $userId = $this->userId();
        $result = OfferService::create([
            'listing_id' => $offer['listing_id'] !== null ? (int) $offer['listing_id'] : null,
            'requirement_id' => $offer['requirement_id'] !== null ? (int) $offer['requirement_id'] : null,
            'thread_id' => $offer['thread_id'] !== null ? (int) $offer['thread_id'] : null,
            'parent_offer_id' => (int) $offer['id'],
            'buyer_id' => (int) $offer['buyer_id'],
            'seller_id' => (int) $offer['seller_id'],
            'created_by' => $userId,
            'amount' => $request->input('amount'),
            'price_basis' => (string) $offer['price_basis'],
            'quantity' => $request->input('quantity'),
            'unit_id' => (int) $offer['unit_id'],
            'gst_included' => $request->bool('gst_included'),
            'transport_included' => $request->bool('transport_included'),
            'payment_terms' => (string) $request->input('payment_terms', $offer['payment_terms']),
            'message' => $request->input('message'),
        ]);

        if ($request->wantsJson()) {
            return $result['ok'] ? $this->ok($result, 'Counter-offer sent.') : $this->fail($result['error']);
        }
        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'Counter-offer sent.' : $result['error']);
        return $result['ok'] ? $this->redirect('/dashboard/offers/' . $result['offer_id']) : $this->back();
    }

    private function canRespond(array $offer): bool
    {
        if ($offer['status'] !== 'pending') {
            return false;
        }
        $recipientId = $offer['direction'] === 'buyer_to_seller' ? (int) $offer['seller_id'] : (int) $offer['buyer_id'];
        return $recipientId === $this->userId();
    }

    private function visibleOffer(int $id): array
    {
        $offer = OfferService::detail($id);
        if ($offer === null) {
            throw new HttpException(404, 'Offer not found.');
        }
        $this->authorize(
            in_array($this->userId(), [(int) $offer['buyer_id'], (int) $offer['seller_id']], true) || Auth::isStaff(),
            'That offer is between two other businesses.'
        );
        return $offer;
    }

    private function respond(Request $request, bool $ok, string $message): Response
    {
        if ($request->wantsJson()) {
            return $ok ? $this->ok([], $message) : $this->fail($message);
        }
        flash($ok ? 'success' : 'danger', $message);
        return $this->back('/dashboard/offers');
    }
}
