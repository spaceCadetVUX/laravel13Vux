<?php

namespace App\Traits;

use App\Models\Seo\GeoEntityProfile;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasGeoProfile
{
    public function geoProfile(): MorphOne
    {
        return $this->morphOne(GeoEntityProfile::class, 'model', 'model_type', 'model_id');
    }
}
