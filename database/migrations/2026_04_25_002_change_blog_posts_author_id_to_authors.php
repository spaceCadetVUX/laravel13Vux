<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Switch blog_posts.author_id from uuid FK → users.id
     * to unsignedBigInteger FK → authors.id.
     *
     * Old type (uuid) cannot be cast to bigint, so:
     * 1. Nullify all existing author_id values (no data loss — dev env).
     * 2. Drop the old FK constraint.
     * 3. Drop and re-add the column with the correct type.
     * 4. Add new FK → authors.id.
     */
    public function up(): void
    {
        // Step 1 — Nullify existing values (uuid → bigint cast is impossible)
        \DB::statement('UPDATE blog_posts SET author_id = NULL');

        Schema::table('blog_posts', function (Blueprint $table) {
            // Step 2 — Drop old FK and index
            $table->dropForeign(['author_id']);
            $table->dropIndex(['author_id']);

            // Step 3 — Drop old uuid column, add new bigint column
            $table->dropColumn('author_id');
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            // Must be in a separate Schema::table call after dropColumn
            $table->unsignedBigInteger('author_id')->nullable()->after('id');

            $table->foreign('author_id')
                ->references('id')
                ->on('authors')
                ->nullOnDelete();

            $table->index('author_id');
        });
    }

    public function down(): void
    {
        // Nullify again (bigint → uuid cast is impossible)
        \DB::statement('UPDATE blog_posts SET author_id = NULL');

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropForeign(['author_id']);
            $table->dropIndex(['author_id']);
            $table->dropColumn('author_id');
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->uuid('author_id')->nullable()->after('id');

            $table->foreign('author_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index('author_id');
        });
    }
};
