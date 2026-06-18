<?php

namespace App\Http\Resources\Api\Blog;

use App\Support\LocaleUrl;
use Illuminate\Http\Request;

class BlogPostDetailResource extends BlogPostResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'content'        => $this->resource->translation(app()->getLocale())?->body,
            'seo'            => $this->whenLoaded('seoMetas', function () {
                $locale        = app()->getLocale();
                $post          = $this->resource;
                $seo           = $post->seoMeta();
                $defaultLocale = config('localeurl.default_locale', 'vi');

                $hreflang = [];
                foreach (config('app.supported_locales', ['vi', 'en']) as $l) {
                    $url = LocaleUrl::forBlogPost($post, $l);
                    if ($url !== '') {
                        $hreflang[$l] = $url;
                    }
                }
                $hreflang['x-default'] = $hreflang[$defaultLocale] ?? (reset($hreflang) ?: '');

                return [
                    'meta_title'          => $seo?->meta_title,
                    'meta_description'    => $seo?->meta_description,
                    'meta_keywords'       => $seo?->meta_keywords,
                    'og_title'            => $seo?->og_title,
                    'og_description'      => $seo?->og_description,
                    'og_image'            => $seo?->og_image,
                    'og_type'             => $seo?->og_type?->value,
                    'twitter_card'        => $seo?->twitter_card,
                    'twitter_title'       => $seo?->twitter_title,
                    'twitter_description' => $seo?->twitter_description,
                    'robots'              => $seo?->robots,
                    // Always computed — DB override only for syndicated content.
                    'canonical_url'       => $seo?->canonical_url
                        ?: LocaleUrl::forBlogPost($post, $locale),
                    'hreflang'            => $hreflang,
                ];
            }),
            'jsonld_schemas' => $this->whenLoaded('activeSchemas', fn () =>
                $this->activeSchemas
                    ->where('locale', app()->getLocale())
                    ->map(fn ($schema) => [
                        'type'    => $schema->schema_type?->value,
                        'label'   => $schema->label,
                        'payload' => $schema->payload,
                    ])->values()->all()
            ),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ]);
    }
}
