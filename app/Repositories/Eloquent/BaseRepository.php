<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

abstract class BaseRepository implements RepositoryInterface
{
    protected Model $model;

    public function __construct()
    {
        $this->model = app($this->model());
    }

    // ── Subclasses declare their model class ──────────────────────────────────

    abstract protected function model(): string;

    // ── Read ──────────────────────────────────────────────────────────────────

    public function findById(string|int $id): ?Model
    {
        return $this->model->find($id);
    }

    public function findByIdOrFail(string|int $id): Model
    {
        return $this->model->findOrFail($id);
    }

    public function findBySlug(string $slug): ?Model
    {
        return $this->model->where('slug', $slug)->first();
    }

    public function findBySlugOrFail(string $slug): Model
    {
        $model = $this->model->where('slug', $slug)->first();

        if (! $model) {
            throw (new ModelNotFoundException())->setModel(get_class($this->model));
        }

        return $model;
    }

    public function all(array $with = []): Collection
    {
        return $this->model->with($with)->get();
    }

    /**
     * Base paginate — concrete repositories override this to add
     * model-specific filters (price range, category, sort, etc).
     */
    public function paginate(int $perPage = 15, array $filters = [], array $with = []): LengthAwarePaginator
    {
        return $this->model->with($with)->paginate($perPage);
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    public function update(Model $model, array $data): Model
    {
        $model->update($data);

        return $model->fresh();
    }

    /**
     * Soft-deletes if the model uses SoftDeletes, hard-deletes otherwise.
     */
    public function delete(Model $model): void
    {
        $model->delete();
    }

    // ── Query builder helpers ─────────────────────────────────────────────────

    /**
     * Returns a fresh query builder scoped to this repository's model.
     * Concrete repositories use this to build complex queries cleanly.
     *
     * Example:
     *   return $this->query()->where('is_active', true)->with('category')->paginate($perPage);
     */
    protected function query(): \Illuminate\Database\Eloquent\Builder
    {
        return $this->model->newQuery();
    }
}
