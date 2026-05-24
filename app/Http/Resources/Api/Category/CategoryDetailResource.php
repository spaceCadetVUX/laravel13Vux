<?php

namespace App\Http\Resources\Api\Category;

use App\Support\LocaleUrl;
use Illuminate\Http\Request;

class CategoryDetailResource extends CategoryResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $t      = $this->resource->translation($locale);
        $slug   = $t?->slug ?? $this->resource->slug;

        return array_merge(parent::toArray($request), [

            // ── SEO meta ───────────────────────────────────────────────────────
            'seo' => $this->whenLoaded(
                'seoMetas',
                function () use ($locale, $slug) {
                    $seo = $this->resource->seoMeta();
                    return $seo ? [
                        'meta_title'          => $seo->meta_title,
                        'meta_description'    => $seo->meta_description,
                        'meta_keywords'       => $seo->meta_keywords,
                        'og_title'            => $seo->og_title,
                        'og_description'      => $seo->og_description,
                        'og_image'            => $seo->og_image,
                        'og_type'             => $seo->og_type?->value,
                        'twitter_card'        => $seo->twitter_card,
                        'twitter_title'       => $seo->twitter_title,
                        'twitter_description' => $seo->twitter_description,
                        'robots'              => $seo->robots,
                        // Always computed — never read from DB to avoid stale domain / wrong prefix.
                        'canonical_url'       => LocaleUrl::for('category', $slug, $locale),
                        // hreflang map uses per-locale translation slug (vi=den-led, en=led-lighting).
                        'hreflang'            => $this->buildHreflang(),
                    ] : null;
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

    /**
     * Build hreflang map using locale-specific translation slugs.
     * Each locale gets its own canonical: vi=/danh-muc/den-led, en=/en/categories/led-lighting.
     *
     * @return array<string, string>
     */
    private function buildHreflang(): array
    {
        $locales       = config('localeurl.supported_locales', ['vi', 'en']);
        $defaultLocale = config('localeurl.default_locale', 'vi');

        $map = [];
        foreach ($locales as $locale) {
            $t            = $this->resource->translation($locale);
            $slug         = $t?->slug ?? $this->resource->slug;
            $map[$locale] = LocaleUrl::for('category', $slug, $locale);
        }
        $map['x-default'] = $map[$defaultLocale];

        return $map;
    }
}
