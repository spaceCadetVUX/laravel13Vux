<?php

namespace App\Models;

use App\Enums\BlogPostStatus;
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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
// User import removed — author() now points to Author model
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class BlogPost extends Model
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
        'author_id',
        'blog_category_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image',
        'status',
        'published_at',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'status'       => BlogPostStatus::class,
            'published_at' => 'datetime',
            'deleted_at'   => 'datetime',
        ];
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Published posts visible in the storefront:
     * status = published AND published_at is set and in the past.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', BlogPostStatus::Published)
            ->where('published_at', '<=', now());
    }

    // ── Scout — Meilisearch ───────────────────────────────────────────────────

    public function searchableAs(): string
    {
        return config('scout.prefix') . 'blog_posts';
    }

    public function toSearchableArray(): array
    {
        return [
            'id'               => $this->id,
            'title'            => $this->title,
            'excerpt'          => $this->excerpt,
            'author'           => $this->author?->name,
            'blog_category_id' => $this->blog_category_id,
            'category'         => $this->blogCategory?->name,
            'status'           => $this->status?->value,
            'published_at'     => $this->published_at?->timestamp,
        ];
    }

    /**
     * Only index published, non-deleted posts.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->status === BlogPostStatus::Published && ! $this->trashed();
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    /** Author profile — nullable, preserved via SET NULL if author deleted. */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'author_id');
    }

    public function blogCategory(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            BlogTag::class,
            'blog_post_tag',  // pivot table
            'blog_post_id',   // FK on pivot pointing to this model
            'blog_tag_id'     // FK on pivot pointing to the related model
        );
    }

    public function comments(): HasMany
    {
        return $this->hasMany(BlogComment::class);
    }

    // ── Multilingual ──────────────────────────────────────────────────────────

    public function translations(): HasMany
    {
        return $this->hasMany(BlogPostTranslation::class);
    }

    public function translation(string $locale = null): ?BlogPostTranslation
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
