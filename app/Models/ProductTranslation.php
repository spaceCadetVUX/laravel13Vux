<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductTranslation extends Model
{
    // bigint PK, no uuid, no soft deletes (CASCADE from product)
    protected $fillable = [
        'product_id',
        'locale',
        'name',
        'slug',
        'short_description',
        'description',
        'price',
        'sale_price',
        'currency',
        'is_mcp_protected',
    ];

    protected function casts(): array
    {
        return [
            'price'            => 'decimal:2',
            'sale_price'       => 'decimal:2',
            'is_mcp_protected' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
