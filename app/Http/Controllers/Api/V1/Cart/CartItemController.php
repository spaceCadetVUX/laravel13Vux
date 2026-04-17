<?php

namespace App\Http\Controllers\Api\V1\Cart;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\Api\Cart\CartResource;
use App\Http\Resources\Traits\ApiResponse;
use App\Models\CartItem;
use App\Services\Cart\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartItemController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CartService $cartService) {}

    /**
     * POST /api/v1/cart/items
     * Add a product to the cart. Increments quantity if already present.
     */
    public function store(AddCartItemRequest $request): JsonResponse
    {
        $cart = $this->cartService->resolveCart($request);

        $cart = $this->cartService->addItem(
            $cart,
            $request->validated('product_id'),
            (int) $request->validated('quantity'),
        );

        return $this->success(data: new CartResource($cart), message: 'Item added to cart', status: 201);
    }

    /**
     * PUT /api/v1/cart/items/{cartItem}
     * Set an item's quantity to an exact value.
     */
    public function update(UpdateCartItemRequest $request, CartItem $cartItem): JsonResponse
    {
        $this->cartService->authorizeItem($request, $cartItem);

        $cart = $this->cartService->updateItem($cartItem, (int) $request->validated('quantity'));

        return $this->success(data: new CartResource($cart), message: 'Cart item updated');
    }

    /**
     * DELETE /api/v1/cart/items/{cartItem}
     * Remove a single item from the cart.
     */
    public function destroy(Request $request, CartItem $cartItem): JsonResponse
    {
        $this->cartService->authorizeItem($request, $cartItem);

        $cart = $this->cartService->removeItem($cartItem);

        return $this->success(data: new CartResource($cart), message: 'Item removed from cart');
    }
}
