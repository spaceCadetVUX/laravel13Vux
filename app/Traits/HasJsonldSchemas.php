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

    /**
     * Only the active schemas, ordered for consistent <head> rendering.
     */
    public function activeSchemas(): MorphMany
    {
        return $this->jsonldSchemas()
            ->where('is_active', true)
            ->orderBy('sort_order');
    }
}
