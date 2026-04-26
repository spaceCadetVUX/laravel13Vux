<?php

namespace App\Traits;

use App\Models\Seo\JsonldSchema;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasJsonldSchemas
{
    public function jsonldSchemas(): MorphMany
    {
        return $this->morphMany(JsonldSchema::class, 'model', 'model_type', 'model_id');
    }

    public function activeSchemas(): MorphMany
    {
        return $this->jsonldSchemas()
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    public function jsonldSchema(string $locale = null): ?JsonldSchema
    {
        $locale ??= app()->getLocale();
        return $this->jsonldSchemas->firstWhere('locale', $locale)
            ?? $this->jsonldSchemas->firstWhere('locale', config('app.fallback_locale', 'vi'));
    }
}
