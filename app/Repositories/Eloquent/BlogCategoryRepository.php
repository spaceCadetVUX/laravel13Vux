<?php

namespace App\Repositories\Eloquent;

use App\Models\BlogCategory;
use Illuminate\Database\Eloquent\Collection;

class BlogCategoryRepository extends BaseRepository
{
    protected function model(): string
    {
        return BlogCategory::class;
    }

    /**
     * Single active blog category by translation slug.
     * Looks up by slug in any locale translation.
     */
    public function findActiveBySlug(string $slug): ?BlogCategory
    {
        /** @var BlogCategory|null */
        return $this->query()
            ->active()
            ->with(['translations', 'seoMetas', 'activeSchemas'])
            ->whereHas('translations', fn ($q) => $q->where('slug', $slug))
            ->first();
    }

    /**
     * Active root categories with active children, ordered by name.
     */
    public function getActiveTree(): Collection
    {
        return $this->query()
            ->active()
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->active()->orderBy('name')])
            ->orderBy('name')
            ->get();
    }
}
