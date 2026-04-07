<?php

namespace App\Observers;

use App\Models\Cart;

/**
 * Stub — filled in S33.
 * Sets / extends expires_at when a cart is created or updated.
 */
class CartObserver
{
    public function creating(Cart $cart): void
    {
        // TODO S33: set expires_at (guest: +7 days, auth user: +30 days)
    }

    public function updating(Cart $cart): void
    {
        // TODO S33: extend expires_at on activity
    }
}
