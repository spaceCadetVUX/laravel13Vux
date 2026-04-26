<?php

namespace App\Traits;

use App\Models\Seo\LlmsEntry;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasLlmsEntry
{
    public function llmsEntries(): MorphMany
    {
        return $this->morphMany(LlmsEntry::class, 'model', 'model_type', 'model_id');
    }

    public function llmsEntry(string $locale = null): ?LlmsEntry
    {
        $locale ??= app()->getLocale();
        return $this->llmsEntries->firstWhere('locale', $locale)
            ?? $this->llmsEntries->firstWhere('locale', config('app.fallback_locale', 'vi'));
    }
}
