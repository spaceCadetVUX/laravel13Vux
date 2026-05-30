<?php

namespace App\Services\Seo;

use App\Support\LocaleUrl;
use Illuminate\Database\Eloquent\Model;

class SeoService
{
    public function alternateUrls(Model $model): array
    {
        $morphAlias = $model->getMorphClass();
        $urls       = [];

        foreach (config('app.supported_locales') as $locale) {
            $translation = $model->translation($locale);
            if ($translation) {
                $urls[$locale] = LocaleUrl::for($morphAlias, $translation->slug, $locale);
            }
        }

        return $urls;
    }
}
