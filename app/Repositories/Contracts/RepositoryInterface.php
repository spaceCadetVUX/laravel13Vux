<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface RepositoryInterface
{
    public function findById(string|int $id): ?Model;

    public function findByIdOrFail(string|int $id): Model;

    public function findBySlug(string $slug): ?Model;

    public function findBySlugOrFail(string $slug): Model;

    public function all(array $with = []): Collection;

    public function paginate(int $perPage = 15, array $filters = [], array $with = []): LengthAwarePaginator;

    public function create(array $data): Model;

    public function update(Model $model, array $data): Model;

    public function delete(Model $model): void;
}
