<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jsonld_schemas', function (Blueprint $table) {
            $table->bigIncrements('id');

            // CRITICAL: string(36) — CLAUDE.md polymorphic model_id rule
            $table->string('model_type');
            $table->string('model_id', 36);

            // e.g. 'Product', 'Article', 'BreadcrumbList', 'FAQPage'
            $table->string('schema_type', 100);

            $table->string('label', 100)->nullable();

            // Final resolved JSON-LD object (template placeholders replaced)
            $table->jsonb('payload');

            $table->boolean('is_active')->default(true);

            // false = manually edited in Filament — Observer NEVER overwrites
            // true  = auto-managed by Observer
            $table->boolean('is_auto_generated')->default(true);

            // Render order in <head> — lower = first
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            // Multiple schemas allowed per model (Product, BreadcrumbList, FAQPage)
            $table->index(['model_type', 'model_id']);
            $table->index('schema_type');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jsonld_schemas');
    }
};
