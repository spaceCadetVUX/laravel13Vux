<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductOptionType extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The selectable values for this option (e.g. Red, Blue, Green).
     * Ordered by sort_order for consistent display and cartesian generation.
     */
    public function values(): HasMany
    {
        return $this->hasMany(ProductOptionValue::class, 'option_type_id')
            ->orderBy('sort_order');
    }
}
