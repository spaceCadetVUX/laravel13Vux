<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * shipping_address stores an encrypted value (base64 string) via the
     * `encrypted:array` cast on the Order model. PostgreSQL jsonb columns
     * reject non-JSON values, so the column must be text.
     *
     * CLAUDE.md: "Use text for encrypted fields (encrypted values are longer
     * than varchar limits)".
     */
    public function up(): void
    {
        // Cast existing jsonb column (likely empty in dev) to text first,
        // then alter to text type.
        DB::statement('ALTER TABLE orders ALTER COLUMN shipping_address TYPE text USING shipping_address::text');
    }

    public function down(): void
    {
        // Revert only works if all values are valid JSON.
        DB::statement('ALTER TABLE orders ALTER COLUMN shipping_address TYPE jsonb USING shipping_address::jsonb');
    }
};
