<?php

namespace App\Http\Resources\Api\Category;

use Illuminate\Http\Request;

class CategoryDetailResource extends CategoryResource
{
    /**
     * Full category detail representation — extends the list resource with
     * SEO meta and JSON-LD schemas for category detail pages.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [

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
