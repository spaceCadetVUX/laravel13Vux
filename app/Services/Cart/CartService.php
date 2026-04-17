<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartService
{
    // ── Cart resolution ───────────────────────────────────────────────────────

    /**
     * Find or create the cart for the current request.
     * Auth users → cart by user_id.
     * Guests     → cart by X-Session-ID header (required).
     *
     * CartObserver handles setting/extending expires_at on create and update.
     */
    public function resolveCart(Request $request): Cart
    {
        // Cart routes are public (no auth:sanctum middleware) so we ask the
        // Sanctum guard directly — it returns the user from the Bearer token
        // without throwing, falling back to null for unauthenticated requests.
        $user = $request->user() ?? auth('sanctum')->user();

        if ($user) {
            $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        } else {
            $sessionId = $request->header('X-Session-ID');

            abort_if(! $sessionId, 400, 'X-Session-ID header is required for guest carts.');

            $cart = Cart::firstOrCreate(['session_id' => $sessionId]);
        }

        // Extend expiry on every access via the CartObserver::updating() hook.
        if (! $cart->wasRecentlyCreated) {
            $cart->touch();
        }

        return $this->loadCartRelations($cart);
    }

    // ── Item management ───────────────────────────────────────────────────────

    /**
     * Add a product to the cart.
     * If the product already exists, quantity is incremented.
     * Throws ValidationException if the combined quantity exceeds stock.
     */
    public function addItem(Cart $cart, string $productId, int $quantity): Cart
    {
        $product = Product::findOrFail($productId);

        $existing   = $cart->items()->where('product_id', $productId)->first();
        $newQuantity = ($existing ? $existing->quantity : 0) + $quantity;

        if ($product->stock_quantity < $newQuantity) {
            throw ValidationException::withMessages([
                'quantity' => ["Only {$product->stock_quantity} units are available in stock."],
            ]);
        }

        $cart->items()->updateOrCreate(
            ['product_id' => $productId],
            ['quantity'   => $newQuantity],
        );

        $cart->touch(); // triggers CartObserver::updating → extends expires_at

        return $this->loadCartRelations($cart);
    }

    /**
     * Set an item's quantity to an exact value.
     * Throws ValidationException if quantity exceeds stock.
     */
    public function updateItem(CartItem $item, int $quantity): Cart
    {
        if ($item->product->stock_quantity < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => ["Only {$item->product->stock_quantity} units are available in stock."],
            ]);
        }

        $item->update(['quantity' => $quantity]);
        $item->cart->touch();

        return $this->loadCartRelations($item->cart);
    }

    /**
     * Remove a single item from the cart.
     */
    public function removeItem(CartItem $item): Cart
    {
        $cart = $item->cart;
        $item->delete();
        $cart->touch();

        return $this->loadCartRelations($cart);
    }

    /**
     * Remove all items from the cart.
     */
    public function clearCart(Cart $cart): void
    {
        $cart->items()->delete();
        $cart->touch();
    }

    // ── Merge ─────────────────────────────────────────────────────────────────

    /**
     * Merge a guest cart into the authenticated user's cart.
     * Existing items are incremented; new items are moved across.
     * The guest cart is deleted after merging.
     */
    public function mergeGuestCart(User $user, string $sessionId): Cart
    {
        $guestCart = Cart::where('session_id', $sessionId)->first();
        $userCart  = Cart::firstOrCreate(['user_id' => $user->id]);

        if ($guestCart) {
            foreach ($guestCart->items as $guestItem) {
                $existing = $userCart->items()
                    ->where('product_id', $guestItem->product_id)
                    ->first();

                if ($existing) {
                    $existing->increment('quantity', $guestItem->quantity);
                } else {
                    $userCart->items()->create([
                        'product_id' => $guestItem->product_id,
                        'quantity'   => $guestItem->quantity,
                    ]);
                }
            }

            $guestCart->delete();
        }

        $userCart->touch();

        return $this->loadCartRelations($userCart);
    }

    // ── Authorization ─────────────────────────────────────────────────────────

    /**
     * Verify the CartItem belongs to the cart identified by this request.
     * Aborts with 403 if ownership cannot be confirmed.
     */
    public function authorizeItem(Request $request, CartItem $item): void
    {
        $cart = $item->cart;

        $user = $request->user() ?? auth('sanctum')->user();

        $owns = $user
            ? (string) $cart->user_id === (string) $user->id
            : $cart->session_id === $request->header('X-Session-ID');

        abort_unless($owns, 403, 'This item does not belong to your cart.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function loadCartRelations(Cart $cart): Cart
    {
        return $cart->load('items.product.thumbnail');
    }
}
