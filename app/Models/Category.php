<?php

namespace App\Models;

use App\Traits\HasActivityLog;
use App\Traits\HasGeoProfile;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use App\Traits\HasJsonldSchemas;
use App\Traits\HasLlmsEntry;
use App\Traits\HasMedia;
use App\Traits\HasSeoMeta;
use App\Traits\HasSitemapEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasSeoMeta;
    use HasGeoProfile;
    use HasJsonldSchemas;
    use HasSitemapEntry;
    use HasLlmsEntry;
    use HasMedia;
    use HasActivityLog;
    use LogsActivity;

    // keyType must be 'string' so Eloquent binds the PK as a quoted string in
    // PostgreSQL polymorphic queries (seo_meta.model_id, geo_entity_profiles.model_id are varchar(36)).
    public $incrementing = true;

    protected $keyType = 'string';

    // ── Mass assignment ───────────────────────────────────────────────────────

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'image_path',
        'sort_order',
        'is_active',
        'faq_items_vi',
        'faq_items_en',
        'mcp_drafted_at',
        'mcp_token_id',
    ];

    // ── Activity log ──────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('category')
            ->logOnly(['parent_id', 'slug', 'image_path', 'sort_order', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'is_active'      => 'boolean',
            'deleted_at'     => 'datetime',
            'faq_items_vi'   => 'array',
            'faq_items_en'   => 'array',
            'mcp_drafted_at' => 'datetime',
            'mcp_token_id'   => 'integer',
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

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    // ── Multilingual ──────────────────────────────────────────────────────────

    public function translations(): HasMany
    {
        return $this->hasMany(CategoryTranslation::class);
    }

    public function translation(?string $locale = null): ?CategoryTranslation
    {
        $locale ??= app()->getLocale();

        if ($this->relationLoaded('translations')) {
            return $this->translations->firstWhere('locale', $locale)
                ?? $this->translations->firstWhere('locale', config('app.fallback_locale'));
        }

        return $this->translations()->where('locale', $locale)->first()
            ?? $this->translations()->where('locale', config('app.fallback_locale'))->first();
    }
}
