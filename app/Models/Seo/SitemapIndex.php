<?php

namespace App\Models\Seo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SitemapIndex extends Model
{
    // Laravel would pluralise to 'sitemap_indices' — override to match migration
    protected $table = 'sitemap_indexes';

    protected $fillable = [
        'name',
        'filename',
        'url',
        'entry_count',
        'last_generated_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'last_generated_at' => 'datetime',
            'is_active'         => 'boolean',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(SitemapEntry::class);
    }
}
