<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    // ── Mass assignment ───────────────────────────────────────────────────────

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_sku',
        'quantity',
        'unit_price',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
        ];
    }

    // ── Computed attributes ───────────────────────────────────────────────────

    /**
     * Line subtotal — uses the snapshotted unit_price (not live product price).
     * Safe even when the product is soft-deleted or repriced.
     */
    protected function subtotal(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->quantity * (float) $this->unit_price,
        );
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Nullable — product can be soft-deleted; order history is preserved
     * via snapshot columns (product_name, product_sku, unit_price).
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
