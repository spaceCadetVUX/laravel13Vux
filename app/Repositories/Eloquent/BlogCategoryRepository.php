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
