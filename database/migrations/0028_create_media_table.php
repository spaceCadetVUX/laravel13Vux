<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->bigIncrements('id');

            // CRITICAL: string(36) — CLAUDE.md polymorphic model_id rule
            $table->string('model_type', 255);
            $table->string('model_id', 36);

            // e.g. 'default', 'gallery', 'thumbnail'
            $table->string('collection', 100)->default('default');

            // Relative path on disk — e.g. 'products/abc123/image.webp'
            $table->string('path', 500);

            // e.g. 'public', 's3'
            $table->string('disk', 50)->default('public');

            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->timestamps();

            // Composite index for polymorphic lookup
            $table->index(['model_type', 'model_id']);
            $table->index('collection');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
