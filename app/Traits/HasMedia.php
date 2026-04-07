<?php

namespace App\Traits;

use App\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasMedia
{
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'model', 'model_type', 'model_id');
    }

    /**
     * First media item in the given collection, or null if none exists.
     * Avoids N+1 when collection is already eager-loaded.
     */
    public function getFirstMedia(string $collection = 'default'): ?Media
    {
        return $this->media()
            ->where('collection', $collection)
            ->orderBy('id')
            ->first();
    }

    /**
     * Public URL of the first media item in the collection, or null.
     */
    public function getFirstMediaUrl(string $collection = 'default'): ?string
    {
        return $this->getFirstMedia($collection)?->url;
    }
}
