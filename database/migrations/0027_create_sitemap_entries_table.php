<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sitemap_entries', function (Blueprint $table) {
            $table->bigIncrements('id');

            // FK → sitemap_indexes.id — entry dies with index (CASCADE)
            $table->unsignedBigInteger('sitemap_index_id');
            $table->foreign('sitemap_index_id')
                ->references('id')
                ->on('sitemap_indexes')
                ->cascadeOnDelete();

            // CRITICAL: string(36) — CLAUDE.md polymorphic model_id rule
            $table->string('model_type');
            $table->string('model_id', 36);

            // Absolute canonical URL of the entity
            $table->string('url', 500);

            // Sitemap protocol values
            $table->string('changefreq', 20)->nullable()->default('weekly');
            $table->decimal('priority', 2, 1)->nullable()->default(0.8);

            // Synced from model updated_at by SyncSitemapEntry job
            $table->timestamp('last_modified')->nullable();

            $table->boolean('is_active')->default(true);

            // No created_at — only updated_at needed (matches ERD)
            $table->timestamp('updated_at');

            // One entry per model per sitemap index
            $table->unique(['sitemap_index_id', 'model_type', 'model_id']);

            $table->index('sitemap_index_id');
            $table->index('is_active');
            $table->index('last_modified');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sitemap_entries');
    }
};
