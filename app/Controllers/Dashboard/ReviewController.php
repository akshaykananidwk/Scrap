<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Order;
use App\Services\ReviewService;

final class ReviewController extends Controller
{
    protected string $layout = 'layouts/dashboard';

    public function index(Request $request): Response
    {
        $direction = $request->query('direction') === 'given' ? 'given' : 'received';

        return $this->view('dashboard/reviews', [
            'title' => 'Reviews',
            'reviews' => ReviewService::forUser($this->userId(), $request->page(), 15, $direction),
            'direction' => $direction,
            'summary' => ReviewService::summary($this->userId()),
            'pending' => ReviewService::pendingForUser($this->userId()),
            'criteria' => ReviewService::CRITERIA,
        ]);
    }

    public function store(Request $request): Response
    {
        $orderId = $request->paramInt('id');
        $order = Order::find($orderId);
        if ($order === null) {
            throw new HttpException(404, 'Order not found.');
        }

        $result = ReviewService::submit($orderId, $this->userId(), [
            'overall_rating' => $request->int('overall_rating'),
            'communication_rating' => $request->int('communication_rating'),
            'material_accuracy_rating' => $request->int('material_accuracy_rating'),
            'payment_rating' => $request->int('payment_rating'),
            'delivery_rating' => $request->int('delivery_rating'),
            'professionalism_rating' => $request->int('professionalism_rating'),
            'title' => $request->input('title'),
            'comment' => $request->input('comment'),
        ]);

        if ($request->wantsJson()) {
            return $result['ok'] ? $this->ok($result, $result['message']) : $this->fail((string) $result['error']);
        }
        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : (string) $result['error']);
        return $this->back('/dashboard/orders/' . $orderId);
    }

    public function respond(Request $request): Response
    {
        $result = ReviewService::respond(
            $request->paramInt('id'),
            $this->userId(),
            (string) $request->input('response', '')
        );

        flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? $result['message'] : (string) $result['error']);
        return $this->back('/dashboard/reviews');
    }
}
