<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory;
    use SoftDeletes;

    // ── PK config (bigint auto-increment — explicit for clarity) ─────────────

    protected $keyType    = 'int';
    public    $incrementing = true;

    // ── Mass assignment ───────────────────────────────────────────────────────

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'image_path',
        'sort_order',
        'is_active',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    /** Parent category (self-referencing). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /** Direct children categories (self-referencing). */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
