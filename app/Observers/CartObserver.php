<?php

namespace App\Observers;

use App\Models\Cart;

class CartObserver
{
    /**
     * Set expiry on cart creation.
     * Auth users get 30 days (persistent cart).
     * Guests get 7 days (session-like cart).
     */
    public function creating(Cart $cart): void
    {
        $cart->expires_at = $cart->user_id !== null
            ? now()->addDays(30)
            : now()->addDays(7);
    }

    /**
     * Reset the expiry timer on every cart update.
     * This extends the cart's life whenever the user modifies it
     * (add item, change quantity, apply coupon, etc.).
     * Uses the same rules as creating(): auth = 30 days, guest = 7 days.
     */
    public function updating(Cart $cart): void
    {
        $cart->expires_at = $cart->user_id !== null
            ? now()->addDays(30)
            : now()->addDays(7);
    }
}
