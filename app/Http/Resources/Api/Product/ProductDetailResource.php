<?php

namespace App\Http\Resources\Api\Product;

use App\Support\LocaleUrl;
use Illuminate\Http\Request;

class ProductDetailResource extends ProductResource
{
    /**
     * Full product detail representation — extends list resource with
     * rich content, media, and SEO data for product detail pages.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [

            'description' => $this->description,

            // ── Localized pricing ──────────────────────────────────────────────
            'pricing' => $this->whenLoaded(
                'translations',
                function () {
                    $out = [];
                    foreach ($this->translations as $t) {
                        if (filled($t->price) || filled($t->sale_price) || filled($t->currency)) {
                            $out[$t->locale] = [
                                'price'      => $t->price !== null ? (string) $t->price : null,
                                'sale_price' => $t->sale_price !== null ? (string) $t->sale_price : null,
                                'currency'   => $t->currency,
                            ];
                        }
                    }
                    return $out;
                },
            ),

            // ── Media ──────────────────────────────────────────────────────────
            'images' => $this->whenLoaded(
                'images',
                fn () => $this->images->map(fn ($img) => [
                    'url'        => $img->url,
                    'alt_text'   => $img->alt_text,
                    'sort_order' => $img->sort_order,
                ])->values(),
            ),

            'videos' => $this->whenLoaded(
                'videos',
                fn () => $this->videos->map(fn ($vid) => [
                    'url'           => $vid->url,
                    'thumbnail_url' => $vid->thumbnail_url,
                ])->values(),
            ),

            // ── SEO meta ───────────────────────────────────────────────────────
            'seo' => $this->whenLoaded(
                'seoMetas',
                function () {
                    $locale  = app()->getLocale();
                    $product = $this->resource;
                    $seo     = $product->seoMeta();

                    // Resolve locale-specific slug from translations (eager-loaded).
                    $baseSlug    = (string) $product->slug;
                    $localeSlug  = $product->translation($locale)?->slug ?? $baseSlug;

                    // Build hreflang map — one entry per supported locale.
                    $hreflang      = [];
                    $defaultLocale = config('localeurl.default_locale', 'vi');
                    foreach (config('app.supported_locales', ['vi', 'en']) as $l) {
                        $lSlug        = $product->translation($l)?->slug ?? $baseSlug;
                        $hreflang[$l] = LocaleUrl::for('product', $lSlug, $l);
                    }
                    $hreflang['x-default'] = $hreflang[$defaultLocale]
                        ?? LocaleUrl::for('product', $baseSlug, $defaultLocale);

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
                        'canonical_url'       => LocaleUrl::for('product', $localeSlug, $locale),
                        'hreflang'            => $hreflang,
                    ];
                },
            ),

            // ── JSON-LD schemas ────────────────────────────────────────────────
            // Only active schemas, pre-ordered by sort_order (loaded via activeSchemas scope).
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
