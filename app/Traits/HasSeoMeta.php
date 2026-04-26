<?php

namespace App\Traits;

use App\Models\Seo\SeoMeta;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasSeoMeta
{
    public function seoMetas(): MorphMany
    {
        return $this->morphMany(SeoMeta::class, 'model', 'model_type', 'model_id');
    }

    // Filament bridge — scoped MorphOne for vi locale (used until ML-13/14 rebuilds forms)
    public function seoMetaVi(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'model', 'model_type', 'model_id')
            ->where('locale', 'vi')
            ->withDefault(['locale' => 'vi']);
    }

    public function seoMeta(string $locale = null): ?SeoMeta
    {
        $locale ??= app()->getLocale();
        return $this->seoMetas->firstWhere('locale', $locale)
            ?? $this->seoMetas->firstWhere('locale', config('app.fallback_locale', 'vi'));
    }

    public function getSeoTitle(): string
    {
        return $this->seoMeta()?->meta_title
            ?? $this->name
            ?? $this->title
            ?? '';
    }
}
