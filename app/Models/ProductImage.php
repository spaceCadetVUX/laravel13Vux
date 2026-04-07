<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'path',
        'alt_text',
        'sort_order',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    // ── Computed attributes ───────────────────────────────────────────────────

    /** Full public URL for the image (read-only). */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => Storage::url($this->path),
        );
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
