<?php

namespace App\Http\Resources\Api\Category;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryTreeResource extends JsonResource
{
    /**
     * Lightweight recursive tree representation.
     * Children are already pre-loaded by CategoryService::getTree().
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $t = $this->resource->translation(app()->getLocale());

        return [
            'id'         => $this->id,
            'name'       => $t?->name ?? $this->name,
            'slug'       => $t?->slug ?? $this->slug,
            'image_url'  => $this->image_path
                ? asset('storage/' . $this->image_path)
                : null,
            'sort_order' => $this->sort_order,
            'children'   => CategoryTreeResource::collection(
                $this->whenLoaded('children', $this->children ?? collect())
            ),
        ];
    }
}
