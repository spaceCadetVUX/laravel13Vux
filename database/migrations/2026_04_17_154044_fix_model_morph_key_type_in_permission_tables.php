<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Cast model_id from varchar(36) → uuid in spatie permission pivot tables.
     *
     * PostgreSQL cannot compare uuid = varchar without an explicit cast.
     * All models that receive roles/permissions in this project (User) use
     * UUID primary keys, so uuid is the correct column type here.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE model_has_roles ALTER COLUMN model_id TYPE uuid USING model_id::uuid');
        DB::statement('ALTER TABLE model_has_permissions ALTER COLUMN model_id TYPE uuid USING model_id::uuid');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE model_has_roles ALTER COLUMN model_id TYPE varchar(36) USING model_id::varchar');
        DB::statement('ALTER TABLE model_has_permissions ALTER COLUMN model_id TYPE varchar(36) USING model_id::varchar');
    }
};
