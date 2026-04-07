<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasUuids;

    // ── PK config ─────────────────────────────────────────────────────────────

    protected $keyType    = 'string';
    public    $incrementing = false;

    // ── Mass assignment ───────────────────────────────────────────────────────

    protected $fillable = [
        'user_id',
        'session_id',
        'expires_at',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    // ── Computed attributes ───────────────────────────────────────────────────

    /**
     * Sum of all item subtotals.
     * Eager-load `items.product` before accessing to avoid N+1.
     */
    protected function total(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->items->sum(fn (CartItem $item) => $item->subtotal),
        );
    }

    /**
     * Total number of units in the cart (sum of quantities, not distinct items).
     */
    protected function itemCount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->items->sum('quantity'),
        );
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    /** Nullable — guest carts have no user. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
