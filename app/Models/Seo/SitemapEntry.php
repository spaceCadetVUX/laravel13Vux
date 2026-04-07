<?php

namespace App\Models\Seo;

use App\Enums\SitemapChangefreq;
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
        'url',
        'changefreq',
        'priority',
        'last_modified',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'changefreq'    => SitemapChangefreq::class,
            'last_modified' => 'datetime',
            'is_active'     => 'boolean',
        ];
    }

    public function sitemapIndex(): BelongsTo
    {
        return $this->belongsTo(SitemapIndex::class);
    }
}
