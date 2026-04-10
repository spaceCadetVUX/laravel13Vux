<?php

namespace App\Http\Resources\Api\Category;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Full category representation — used for list items and detail pages.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'slug'           => $this->slug,
            'description'    => $this->description,
            'image_url'      => $this->image_path
                ? asset('storage/' . $this->image_path)
                : null,
            'sort_order'     => $this->sort_order,
            'is_active'      => $this->is_active,
            'parent'         => $this->whenLoaded('parent', fn () => new CategoryResource($this->parent)),
            'children_count' => $this->when(
                isset($this->children_count),
                $this->children_count,
                fn () => $this->children()->count(),
            ),
            'created_at'     => $this->created_at->toIso8601String(),
        ];
    }
}
