<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\PlaceOrderRequest;
use App\Http\Resources\Api\Order\OrderCollection;
use App\Http\Resources\Api\Order\OrderResource;
use App\Http\Resources\Traits\ApiResponse;
use App\Models\Order;
use App\Services\Order\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly OrderService $orderService) {}

    /**
     * GET /api/v1/orders
     * Paginated order history for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with('items')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->success(
            data: new OrderCollection($orders),
            meta: $this->paginationMeta($orders),
        );
    }

    /**
     * POST /api/v1/orders
     * Place a new order from the current cart.
     */
    public function store(PlaceOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->placeOrder(
            $request->user(),
            $request->validated(),
        );

        return $this->success(
            data: new OrderResource($order),
            message: 'Order placed successfully',
            status: 201,
        );
    }

    /**
     * GET /api/v1/orders/{order}
     * Single order detail. Policy ensures user owns the order.
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return $this->success(
            data: new OrderResource($order->load('items')),
        );
    }

    /**
     * PATCH /api/v1/orders/{order}/cancel
     * Cancel a pending order. Policy ensures user owns the order.
     */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        $this->authorize('cancel', $order);

        $order = $this->orderService->cancel($order);

        return $this->success(
            data: new OrderResource($order->load('items')),
            message: 'Order cancelled',
        );
    }
}
