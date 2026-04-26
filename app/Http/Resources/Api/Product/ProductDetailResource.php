<?php

namespace App\Http\Resources\Api\Product;

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
                        'canonical_url'       => $seo->canonical_url,
                        'robots'              => $seo->robots,
                    ] : null;
                },
            ),

            // ── JSON-LD schemas ────────────────────────────────────────────────
            // Only active schemas, pre-ordered by sort_order (loaded via activeSchemas scope).
            'jsonld_schemas' => $this->whenLoaded(
                'activeSchemas',
                fn () => $this->activeSchemas->map(fn ($schema) => [
                    'type'    => $schema->schema_type?->value,
                    'label'   => $schema->label,
                    'payload' => $schema->payload,
                ])->values(),
            ),
        ]);
    }
}
