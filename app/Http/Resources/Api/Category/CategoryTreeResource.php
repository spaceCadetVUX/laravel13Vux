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
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'slug'       => $this->slug,
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
