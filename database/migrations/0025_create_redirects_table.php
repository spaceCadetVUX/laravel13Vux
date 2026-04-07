<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->bigIncrements('id');

            // e.g. '/old-product-slug' — unique, used as Redis cache lookup key
            $table->string('from_path', 500)->unique();

            // e.g. '/products/new-slug'
            $table->string('to_path', 500);

            // 301 (permanent) | 302 (temporary)
            $table->smallInteger('type')->default(301);

            // Track how many times this redirect was hit
            $table->integer('hits')->default(0);

            // Incremented by RedirectObserver on any write → busts Redis cache
            // Cache key: redirects:v{max(cache_version)} — TTL 60 min fallback
            $table->integer('cache_version')->default(1);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};
