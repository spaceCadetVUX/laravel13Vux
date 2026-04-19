<?php

namespace App\Repositories\Eloquent;

use App\Models\BlogTag;
use Illuminate\Database\Eloquent\Collection;

class BlogTagRepository extends BaseRepository
{
    protected function model(): string
    {
        return BlogTag::class;
    }

    /**
     * All tags ordered by name.
     */
    public function getAllOrdered(): Collection
    {
        return $this->query()->orderBy('name')->get();
    }
}
