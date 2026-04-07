<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_meta', function (Blueprint $table) {
            $table->bigIncrements('id');

            // CRITICAL: string(36) — handles uuid PKs ("550e8400-...") and bigint PKs ("12")
            // NEVER use uuid() here — CLAUDE.md polymorphic model_id rule
            $table->string('model_type');
            $table->string('model_id', 36);

            $table->string('meta_title', 160)->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('meta_keywords', 500)->nullable();

            $table->string('og_title', 160)->nullable();
            $table->string('og_description', 320)->nullable();
            $table->string('og_image', 500)->nullable();
            $table->string('og_type', 50)->nullable()->default('website');

            $table->string('twitter_card', 50)->nullable()->default('summary_large_image');
            $table->string('twitter_title', 160)->nullable();
            $table->string('twitter_description', 320)->nullable();

            $table->string('canonical_url', 500)->nullable();
            $table->string('robots', 100)->nullable()->default('index, follow');

            $table->timestamps();

            // One SEO meta row per model instance
            $table->unique(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_meta');
    }
};
