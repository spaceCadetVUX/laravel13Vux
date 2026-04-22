<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductOptionValue extends Model
{
    protected $fillable = [
        'option_type_id',
        'value',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function optionType(): BelongsTo
    {
        return $this->belongsTo(ProductOptionType::class, 'option_type_id');
    }

    /**
     * All variants that include this option value as part of their combination.
     */
    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductVariant::class,
            'product_variant_options',
            'option_value_id',
            'variant_id',
        )->withTimestamps();
    }
}
