<?php

namespace App\Models\Seo;

use App\Enums\SitemapChangefreq;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SitemapEntry extends Model
{
    // No created_at — only updated_at (matches ERD + migration)
    const CREATED_AT = null;

    protected $fillable = [
        'sitemap_index_id',
        'model_type',
        'model_id',
        'locale',
        'url',
        'alternate_urls',
        'changefreq',
        'priority',
        'last_modified',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'alternate_urls' => 'array',
            'changefreq'     => SitemapChangefreq::class,
            'last_modified'  => 'datetime',
            'is_active'      => 'boolean',
        ];
    }

    public function sitemapIndex(): BelongsTo
    {
        return $this->belongsTo(SitemapIndex::class);
    }

    public function scopeForLocale(Builder $q, string $locale): Builder
    {
        return $q->where('locale', $locale);
    }
}
