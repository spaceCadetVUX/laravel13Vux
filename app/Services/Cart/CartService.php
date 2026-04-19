<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use App\Repositories\Eloquent\CartRepository;
use App\Repositories\Eloquent\ProductRepository;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function __construct(
        private readonly CartRepository    $cartRepository,
        private readonly ProductRepository $productRepository,
    ) {}

    // ── Cart resolution ───────────────────────────────────────────────────────

    public function resolveCart(Request $request): Cart
    {
        $user = $request->user() ?? auth('sanctum')->user();

        if ($user) {
            $cart = $this->cartRepository->firstOrCreateForUser($user);
        } else {
            $sessionId = $request->header('X-Session-ID');
            abort_if(! $sessionId, 400, 'X-Session-ID header is required for guest carts.');
            $cart = $this->cartRepository->firstOrCreateForSession($sessionId);
        }

        if (! $cart->wasRecentlyCreated) {
            $cart->touch();
        }

        return $this->cartRepository->withItems($cart);
    }

    // ── Item management ───────────────────────────────────────────────────────

    public function addItem(Cart $cart, string $productId, int $quantity): Cart
    {
        $product     = $this->productRepository->findByIdOrFail($productId);
        $existing    = $this->cartRepository->findItem($cart, $productId);
        $newQuantity = ($existing ? $existing->quantity : 0) + $quantity;

        if ($product->stock_quantity < $newQuantity) {
            throw ValidationException::withMessages([
                'quantity' => ["Only {$product->stock_quantity} units are available in stock."],
            ]);
        }

        $this->cartRepository->upsertItem($cart, $productId, $newQuantity);
        $cart->touch();

        return $this->cartRepository->withItems($cart);
    }

    public function updateItem(CartItem $item, int $quantity): Cart
    {
        if ($item->product->stock_quantity < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => ["Only {$item->product->stock_quantity} units are available in stock."],
            ]);
        }

        $this->cartRepository->updateItemQuantity($item, $quantity);
        $item->cart->touch();

        return $this->cartRepository->withItems($item->cart);
    }

    public function removeItem(CartItem $item): Cart
    {
        $cart = $item->cart;
        $this->cartRepository->deleteItem($item);
        $cart->touch();

        return $this->cartRepository->withItems($cart);
    }

    public function clearCart(Cart $cart): void
    {
        $this->cartRepository->clearItems($cart);
        $cart->touch();
    }

    // ── Merge ─────────────────────────────────────────────────────────────────

    public function mergeGuestCart(User $user, string $sessionId): Cart
    {
        $guestCart = $this->cartRepository->findBySession($sessionId);
        $userCart  = $this->cartRepository->firstOrCreateForUser($user);

        if ($guestCart) {
            $this->cartRepository->mergeItems($guestCart, $userCart);
            $guestCart->delete();
        }

        $userCart->touch();

        return $this->cartRepository->withItems($userCart);
    }

    // ── Authorization ─────────────────────────────────────────────────────────

    public function authorizeItem(Request $request, CartItem $item): void
    {
        $cart = $item->cart;
        $user = $request->user() ?? auth('sanctum')->user();

        $owns = $user
            ? (string) $cart->user_id === (string) $user->id
            : $cart->session_id === $request->header('X-Session-ID');

        abort_unless($owns, 403, 'This item does not belong to your cart.');
    }
}
