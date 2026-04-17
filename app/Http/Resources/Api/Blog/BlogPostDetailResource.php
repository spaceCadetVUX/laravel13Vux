<?php

namespace App\Http\Resources\Api\Blog;

use Illuminate\Http\Request;

class BlogPostDetailResource extends BlogPostResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'content'        => $this->content,
            'seo'            => $this->whenLoaded('seoMeta', fn () => [
                'meta_title'       => $this->seoMeta?->meta_title,
                'meta_description' => $this->seoMeta?->meta_description,
                'og_image'         => $this->seoMeta?->og_image,
                'canonical_url'    => $this->seoMeta?->canonical_url,
            ]),
            'jsonld_schemas' => $this->whenLoaded('activeSchemas', fn () =>
                $this->activeSchemas->pluck('payload')->values()->all()
            ),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ]);
    }
}
