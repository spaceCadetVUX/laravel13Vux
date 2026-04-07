<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    // ── Mass assignment ───────────────────────────────────────────────────────

    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity',
    ];

    // ── Computed attributes ───────────────────────────────────────────────────

    /**
     * Line subtotal — uses sale_price when available, falls back to regular price.
     * Requires product relationship to be loaded (eager or lazy).
     */
    protected function subtotal(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->quantity * (float) ($this->product->sale_price ?? $this->product->price),
        );
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
