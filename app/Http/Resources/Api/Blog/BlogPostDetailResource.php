<?php

namespace App\Http\Resources\Api\Blog;

use Illuminate\Http\Request;

class BlogPostDetailResource extends BlogPostResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'content'        => $this->content,
            'seo'            => $this->whenLoaded('seoMetas', function () {
                $seo = $this->resource->seoMeta();
                return [
                    'meta_title'       => $seo?->meta_title,
                    'meta_description' => $seo?->meta_description,
                    'og_image'         => $seo?->og_image,
                    'canonical_url'    => $seo?->canonical_url,
                ];
            }),
            'jsonld_schemas' => $this->whenLoaded('activeSchemas', fn () =>
                $this->activeSchemas->pluck('payload')->values()->all()
            ),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ]);
    }
}
