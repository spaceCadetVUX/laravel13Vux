<?php

namespace App\Http\Controllers\Api\V1\Cart;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Cart\CartResource;
use App\Http\Resources\Traits\ApiResponse;
use App\Services\Cart\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CartService $cartService) {}

    /**
     * GET /api/v1/cart
     * Return the current cart. Creates one if none exists.
     */
    public function show(Request $request): JsonResponse
    {
        $cart = $this->cartService->resolveCart($request);

        return $this->success(data: new CartResource($cart));
    }

    /**
     * DELETE /api/v1/cart
     * Remove all items from the cart.
     */
    public function clear(Request $request): JsonResponse
    {
        $cart = $this->cartService->resolveCart($request);
        $this->cartService->clearCart($cart);

        return $this->success(data: null, message: 'Cart cleared');
    }

    /**
     * POST /api/v1/cart/merge  [auth:sanctum]
     * Merge guest cart into the authenticated user's cart.
     */
    public function merge(Request $request): JsonResponse
    {
        $request->validate(['session_id' => ['required', 'uuid']]);

        $cart = $this->cartService->mergeGuestCart(
            $request->user(),
            $request->input('session_id'),
        );

        return $this->success(data: new CartResource($cart), message: 'Cart merged');
    }
}
