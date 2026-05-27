<?php

namespace App\Http\Resources\Api\Blog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlogPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'title'          => $this->resource->translation(app()->getLocale())?->title,
            'slug'           => $this->resource->translation(app()->getLocale())?->slug,
            'excerpt'        => $this->resource->translation(app()->getLocale())?->excerpt,
            'featured_image' => $this->featured_image,
            'author'         => $this->whenLoaded('author', fn () => $this->author ? [
                'id'     => $this->author->id,
                'name'   => $this->author->name,
                'slug'   => $this->author->slug,
                'title'  => $this->author->title,
                'avatar' => $this->author->avatar_url,   // computed full URL
            ] : null),
            'category'     => new BlogCategoryResource($this->whenLoaded('blogCategory')),
            'tags'         => BlogTagResource::collection($this->whenLoaded('tags')),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
