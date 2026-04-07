<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llms_entries', function (Blueprint $table) {
            $table->bigIncrements('id');

            // FK → llms_documents.id — entry dies with document (CASCADE)
            $table->unsignedBigInteger('llms_document_id');
            $table->foreign('llms_document_id')
                ->references('id')
                ->on('llms_documents')
                ->cascadeOnDelete();

            // CRITICAL: string(36) — CLAUDE.md polymorphic model_id rule
            $table->string('model_type');
            $table->string('model_id', 36);

            $table->string('title', 255);

            // Canonical public URL of the entity
            $table->string('url', 500);

            // Pre-flattened plain text from geo_entity_profiles — no JSON parsing at serve time
            $table->text('summary')->nullable();
            $table->text('key_facts_text')->nullable();
            $table->text('faq_text')->nullable();

            $table->boolean('is_active')->default(true);

            // No created_at — only updated_at needed (matches ERD)
            $table->timestamp('updated_at');

            // One entry per model per document
            $table->unique(['llms_document_id', 'model_type', 'model_id']);

            $table->index('llms_document_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llms_entries');
    }
};
