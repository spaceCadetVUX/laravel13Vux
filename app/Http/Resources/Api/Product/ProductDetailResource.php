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
                'seoMeta',
                fn () => $this->seoMeta ? [
                    'meta_title'          => $this->seoMeta->meta_title,
                    'meta_description'    => $this->seoMeta->meta_description,
                    'meta_keywords'       => $this->seoMeta->meta_keywords,
                    'og_title'            => $this->seoMeta->og_title,
                    'og_description'      => $this->seoMeta->og_description,
                    'og_image'            => $this->seoMeta->og_image,
                    'og_type'             => $this->seoMeta->og_type?->value,
                    'twitter_card'        => $this->seoMeta->twitter_card,
                    'twitter_title'       => $this->seoMeta->twitter_title,
                    'twitter_description' => $this->seoMeta->twitter_description,
                    'canonical_url'       => $this->seoMeta->canonical_url,
                    'robots'              => $this->seoMeta->robots,
                ] : null,
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
