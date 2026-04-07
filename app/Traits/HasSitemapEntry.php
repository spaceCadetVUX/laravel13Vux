<?php

namespace App\Traits;

use App\Models\Seo\SitemapEntry;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasSitemapEntry
{
    public function sitemapEntry(): MorphOne
    {
        return $this->morphOne(SitemapEntry::class, 'model', 'model_type', 'model_id');
    }
}
