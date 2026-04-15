<?php

namespace App\Models;

use App\Traits\HasActivityLog;
use App\Traits\HasGeoProfile;
use App\Traits\HasJsonldSchemas;
use App\Traits\HasLlmsEntry;
use App\Traits\HasMedia;
use App\Traits\HasSeoMeta;
use App\Traits\HasSitemapEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;
    use Searchable;
    use HasSeoMeta;
    use HasGeoProfile;
    use HasJsonldSchemas;
    use HasSitemapEntry;
    use HasLlmsEntry;
    use HasMedia;
    use HasActivityLog;

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
     * Index name in Meilisearch (prefix from scout.prefix config).
     */
    public function searchableAs(): string
    {
        return config('scout.prefix') . 'products';
    }

    /**
     * Fields sent to Meilisearch on every save.
     * Includes all filterable and sortable attributes required by the search index.
     */
    public function toSearchableArray(): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'sku'               => $this->sku,
            'short_description' => $this->short_description,
            'category_id'       => $this->category_id,
            'category'          => $this->relationLoaded('category') ? $this->category?->name : null,
            'price'             => (float) $this->price,
            'sale_price'        => $this->sale_price ? (float) $this->sale_price : null,
            'stock_quantity'    => $this->stock_quantity,
            'is_active'         => $this->is_active,
            'created_at'        => $this->created_at?->timestamp,
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

    /**
     * Chỉ lấy ảnh đầu tiên (sort_order nhỏ nhất) — dùng cho thumbnail ở list view.
     * Tránh load toàn bộ images collection chỉ để lấy ->first().
     */
    public function thumbnail(): HasOne
    {
        return $this->hasOne(ProductImage::class)->orderBy('sort_order');
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
