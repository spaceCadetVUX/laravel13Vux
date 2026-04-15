<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    // ── Model events ──────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::deleted(function (ProductImage $image): void {
            if ($image->path && Storage::disk('public')->exists($image->path)) {
                Storage::disk('public')->delete($image->path);
            }
        });
    }

    protected $fillable = [
        'product_id',
        'path',
        'alt_text',
        'sort_order',
        'price',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'price'      => 'decimal:2',
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

    /** Categories this image is tagged with (subset of product's categories). */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_product_image');
    }
}
