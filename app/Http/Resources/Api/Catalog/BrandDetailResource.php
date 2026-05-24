<?php

namespace App\Http\Resources\Api\Catalog;

use App\Support\LocaleUrl;
use Illuminate\Http\Request;

class BrandDetailResource extends BrandResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $slug   = $this->resource->slug;

        return array_merge(parent::toArray($request), [

            // ── SEO meta ───────────────────────────────────────────────────────
            'seo' => $this->whenLoaded(
                'seoMetas',
                function () use ($locale, $slug) {
                    $seo = $this->resource->seoMeta();
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
                        // Always computed — never read from DB to avoid stale domain / wrong prefix.
                        'canonical_url'       => LocaleUrl::for('brand', $slug, $locale),
                        // hreflang map for Nuxt to render <link rel="alternate"> tags.
                        'hreflang'            => LocaleUrl::hreflang('brand', $slug),
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
                    ])->values(),
            ),

        ]);
    }
}
