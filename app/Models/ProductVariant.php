<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'image_id',
        'sku',
        'price',
        'sale_price',
        'stock_quantity',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price'          => 'decimal:2',
            'sale_price'     => 'decimal:2',
            'stock_quantity' => 'integer',
            'is_active'      => 'boolean',
            'sort_order'     => 'integer',
        ];
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', '>', 0);
    }

    // ── Computed attributes ───────────────────────────────────────────────────

    /** Effective selling price — sale_price if set, otherwise base price. */
    public function getEffectivePriceAttribute(): string
    {
        return $this->sale_price ?? $this->price;
    }

    /**
     * Human-readable combination label: "Red / M / 256GB"
     * Requires optionValues.optionType to be loaded.
     */
    public function getCombinationLabelAttribute(): string
    {
        if (! $this->relationLoaded('optionValues')) {
            return '';
        }

        return $this->optionValues
            ->sortBy(fn ($v) => $v->optionType?->sort_order ?? 0)
            ->pluck('value')
            ->join(' / ');
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class, 'image_id');
    }

    /**
     * The selected option values that make up this variant's combination.
     * e.g. [Color=Red, Size=M] for a "Red / M" variant.
     */
    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductOptionValue::class,
            'product_variant_options',
            'variant_id',
            'option_value_id',
        )->withTimestamps();
    }
}
