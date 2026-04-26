<?php

namespace App\Traits;

use App\Models\Seo\SitemapEntry;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasSitemapEntry
{
    public function sitemapEntries(): MorphMany
    {
        return $this->morphMany(SitemapEntry::class, 'model', 'model_type', 'model_id');
    }

    public function sitemapEntry(string $locale = null): ?SitemapEntry
    {
        $locale ??= app()->getLocale();
        return $this->sitemapEntries->firstWhere('locale', $locale)
            ?? $this->sitemapEntries->firstWhere('locale', config('app.fallback_locale', 'vi'));
    }
}
