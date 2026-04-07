<?php

namespace App\Traits;

use App\Models\Seo\SeoMeta;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasSeoMeta
{
    public function seoMeta(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'model', 'model_type', 'model_id');
    }

    /**
     * Best-effort SEO title: custom meta title → name → title → empty string.
     * Safe to call even when seoMeta row does not exist yet.
     */
    public function getSeoTitle(): string
    {
        return $this->seoMeta?->meta_title
            ?? $this->name
            ?? $this->title
            ?? '';
    }
}
