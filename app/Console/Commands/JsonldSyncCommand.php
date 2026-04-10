<?php

namespace App\Console\Commands;

use App\Jobs\Seo\SyncJsonldSchema;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class JsonldSyncCommand extends Command
{
    protected $signature = 'jsonld:sync
                            {model : Model morph alias or short class name (e.g. product, blog_post, Product)}
                            {id?   : Primary key of a single record to sync}
                            {--all : Sync every record for the given model}';

    protected $description = 'Dispatch SyncJsonldSchema jobs for one or all records of a model';

    public function handle(): int
    {
        $modelClass = $this->resolveModelClass($this->argument('model'));

        if ($modelClass === null) {
            $this->error("Unknown model: '{$this->argument('model')}'. Use a morph alias (e.g. product) or short name (e.g. Product).");

            return self::FAILURE;
        }

        if ($this->option('all')) {
            return $this->syncAll($modelClass);
        }

        $id = $this->argument('id');

        if ($id === null) {
            $this->error('Provide a record ID or use --all to sync every record.');

            return self::FAILURE;
        }

        return $this->syncOne($modelClass, $id);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function syncAll(string $modelClass): int
    {
        $count = 0;

        /** @var Model $modelClass */
        $modelClass::query()->chunkById(100, function ($records) use (&$count): void {
            foreach ($records as $record) {
                dispatch(new SyncJsonldSchema($record));
                $count++;
            }
        });

        $this->info("Synced {$count} record(s).");

        return self::SUCCESS;
    }

    private function syncOne(string $modelClass, string $id): int
    {
        /** @var Model|null $record */
        $record = $modelClass::find($id);

        if ($record === null) {
            $this->error("Record not found: {$modelClass}#{$id}");

            return self::FAILURE;
        }

        dispatch(new SyncJsonldSchema($record));
        $this->info("Synced 1 record (id={$id}).");

        return self::SUCCESS;
    }

    /**
     * Resolve a user-supplied model name to a fully-qualified class name.
     * Accepts:
     *   - morph alias:  'product', 'blog_post'
     *   - short name:   'Product', 'BlogPost' (looked up in App\Models namespace)
     */
    private function resolveModelClass(string $input): ?string
    {
        // 1. Try morph map alias first (exact match)
        $morphMap = Relation::morphMap();

        if (isset($morphMap[$input])) {
            return $morphMap[$input];
        }

        // 2. Try App\Models\{Input} (short PascalCase name)
        $candidate = 'App\\Models\\' . ltrim($input, '\\');

        if (class_exists($candidate)) {
            return $candidate;
        }

        return null;
    }
}
