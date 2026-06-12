<?php

namespace App\Traits;

use App\Models\Seo\GeoEntityProfile;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasGeoProfile
{
    public function geoProfiles(): MorphMany
    {
        return $this->morphMany(GeoEntityProfile::class, 'model', 'model_type', 'model_id');
    }

    public function geoProfileVi(): MorphOne
    {
        return $this->morphOne(GeoEntityProfile::class, 'model', 'model_type', 'model_id')
            ->where('locale', 'vi')
            ->withDefault(['locale' => 'vi']);
    }

    public function geoProfileEn(): MorphOne
    {
        return $this->morphOne(GeoEntityProfile::class, 'model', 'model_type', 'model_id')
            ->where('locale', 'en')
            ->withDefault(['locale' => 'en']);
    }

    public function geoProfile(?string $locale = null): ?GeoEntityProfile
    {
        $locale ??= app()->getLocale();
        return $this->geoProfiles->firstWhere('locale', $locale)
            ?? $this->geoProfiles->firstWhere('locale', config('app.fallback_locale', 'vi'));
    }
}
