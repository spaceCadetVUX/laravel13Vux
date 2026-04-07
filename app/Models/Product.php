<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;
    use Searchable;

    // ── PK config ─────────────────────────────────────────────────────────────

    protected $keyType    = 'string';
    public    $incrementing = false;

    // ── Mass assignment ───────────────────────────────────────────────────────

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'sku',
        'short_description',
        'description',
        'price',
        'sale_price',
        'stock_quantity',
        'is_active',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'price'          => 'decimal:2',
            'sale_price'     => 'decimal:2',
            'is_active'      => 'boolean',
            'deleted_at'     => 'datetime',
        ];
    }

    // ── Scout — Meilisearch ───────────────────────────────────────────────────

    /**
     * Index name in Meilisearch.
     * Defaults to 'products' (table name) — explicit for clarity.
     */
    public function searchableAs(): string
    {
        return 'products';
    }

    /**
     * Fields sent to Meilisearch on every save.
     * Loads category relationship eagerly to include category name.
     */
    public function toSearchableArray(): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'sku'               => $this->sku,
            'short_description' => $this->short_description,
            'category'          => $this->category?->name,
            'price'             => (float) $this->price,
            'is_active'         => $this->is_active,
        ];
    }

    /**
     * Only index active, non-deleted products.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->is_active && ! $this->trashed();
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(ProductVideo::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
