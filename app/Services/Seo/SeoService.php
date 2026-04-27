<?php

namespace App\Services\Seo;

use Illuminate\Database\Eloquent\Model;

class SeoService
{
    public function alternateUrls(Model $model, string $routeName): array
    {
        $urls = [];
        foreach (config('app.supported_locales') as $locale) {
            $translation = $model->translation($locale);
            if ($translation) {
                $urls[$locale] = route($routeName, [
                    'locale' => $locale,
                    'slug'   => $translation->slug,
                ]);
            }
        }
        return $urls;
    }
}
