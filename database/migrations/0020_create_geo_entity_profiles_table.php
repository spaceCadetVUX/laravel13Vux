<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_entity_profiles', function (Blueprint $table) {
            $table->bigIncrements('id');

            // CRITICAL: string(36) — CLAUDE.md polymorphic model_id rule
            $table->string('model_type');
            $table->string('model_id', 36);

            // AI-readable plain text summary (2–3 sentences)
            $table->text('ai_summary')->nullable();

            // jsonb: [{"label":"...","value":"..."}]
            $table->jsonb('key_facts')->nullable();

            // jsonb: [{"q":"...","a":"..."}]
            $table->jsonb('faq')->nullable();

            $table->text('use_cases')->nullable();
            $table->string('target_audience', 255)->nullable();

            // Extra context written specifically for LLMs
            $table->text('llm_context_hint')->nullable();

            $table->timestamps();

            // One GEO profile per model instance
            $table->unique(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_entity_profiles');
    }
};
