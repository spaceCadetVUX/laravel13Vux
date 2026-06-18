<?php

namespace App\Http\Resources\Api\Blog;

use App\Support\LocaleUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlogCategoryDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $t      = $this->resource->translation($locale);
        $slug   = $t?->slug ?? $this->resource->slug;

        return [
            'id'          => $this->id,
            'name'        => $t?->name ?? $this->resource->name,
            'slug'        => $slug,
            'description' => $t?->description ?? null,

            // ── SEO meta ───────────────────────────────────────────────────────
            'seo' => $this->whenLoaded(
                'seoMetas',
                function () use ($locale, $slug) {
                    $seo           = $this->resource->seoMeta();
                    $defaultLocale = config('localeurl.default_locale', 'vi');

                    $hreflang = [];
                    foreach (config('app.supported_locales', ['vi', 'en']) as $l) {
                        $lt           = $this->resource->translation($l);
                        $lSlug        = $lt?->slug ?? $this->resource->slug;
                        $hreflang[$l] = LocaleUrl::for('blog_category', $lSlug, $l);
                    }
                    $hreflang['x-default'] = $hreflang[$defaultLocale];

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
                        // Always computed — never read from DB to avoid stale prefix.
                        'canonical_url'       => LocaleUrl::for('blog_category', $slug, $locale),
                        'hreflang'            => $hreflang,
                    ];
                },
            ),

            // ── JSON-LD schemas ────────────────────────────────────────────────
            'jsonld_schemas' => $this->whenLoaded(
                'activeSchemas',
                fn () => $this->activeSchemas
                    ->where('locale', app()->getLocale())
                    ->map(fn ($schema) => [
                        'type'    => $schema->schema_type?->value,
                        'label'   => $schema->label,
                        'payload' => $schema->payload,
                    ])->values()->all(),
            ),
        ];
    }
}
