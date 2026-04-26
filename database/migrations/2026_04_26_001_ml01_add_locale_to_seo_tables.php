<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── seo_meta ──────────────────────────────────────────────────────────
        Schema::table('seo_meta', function (Blueprint $table) {
            $table->string('locale', 10)->default('vi')->after('model_id');
        });
        Schema::table('seo_meta', function (Blueprint $table) {
            // Drop old single-model unique, replace with per-locale unique
            $table->dropUnique(['model_type', 'model_id']);
            $table->unique(['model_type', 'model_id', 'locale']);
        });

        // ── geo_entity_profiles ───────────────────────────────────────────────
        Schema::table('geo_entity_profiles', function (Blueprint $table) {
            $table->string('locale', 10)->default('vi')->after('model_id');
        });
        Schema::table('geo_entity_profiles', function (Blueprint $table) {
            $table->dropUnique(['model_type', 'model_id']);
            $table->unique(['model_type', 'model_id', 'locale']);
        });

        // ── jsonld_schemas ────────────────────────────────────────────────────
        Schema::table('jsonld_schemas', function (Blueprint $table) {
            $table->string('locale', 10)->default('vi')->after('model_id');
            $table->index('locale');
        });

        // ── sitemap_indexes ───────────────────────────────────────────────────
        Schema::table('sitemap_indexes', function (Blueprint $table) {
            // Drop old single-value uniques — new names include locale prefix
            $table->dropUnique(['name']);
            $table->dropUnique(['filename']);

            $table->string('locale', 10)->nullable()->after('is_active');
            $table->string('model_type', 255)->nullable()->after('locale');

            // Re-add unique on the new wider columns (values already distinct)
            $table->unique('name');
            $table->unique('filename');
            $table->index('locale');
        });

        // ── sitemap_entries ───────────────────────────────────────────────────
        Schema::table('sitemap_entries', function (Blueprint $table) {
            $table->string('locale', 10)->default('vi')->after('sitemap_index_id');
            // hreflang xlinks: {'vi':'https://...','en':'https://...'}
            $table->jsonb('alternate_urls')->nullable()->after('url');
            $table->index(['sitemap_index_id', 'locale']);
        });

        // ── llms_documents ────────────────────────────────────────────────────
        Schema::table('llms_documents', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->dropUnique(['slug']);

            $table->string('locale', 10)->default('vi')->after('id');

            $table->unique(['name', 'locale']);
            $table->unique(['slug', 'locale']);
        });

        // ── llms_entries ──────────────────────────────────────────────────────
        Schema::table('llms_entries', function (Blueprint $table) {
            $table->string('locale', 10)->default('vi')->after('model_id');
            // Existing unique (llms_document_id, model_type, model_id) is
            // per-locale already because llms_document_id is locale-scoped.
            $table->index('locale');
        });

        // ── redirects ─────────────────────────────────────────────────────────
        Schema::table('redirects', function (Blueprint $table) {
            // from_path already contains locale prefix (/vi/...) — unique stays
            // locale column is informational for filtering/debugging
            $table->string('locale', 10)->nullable()->after('type');
            $table->index('locale');
        });
    }

    public function down(): void
    {
        Schema::table('redirects', function (Blueprint $table) {
            $table->dropIndex(['locale']);
            $table->dropColumn('locale');
        });

        Schema::table('llms_entries', function (Blueprint $table) {
            $table->dropIndex(['locale']);
            $table->dropColumn('locale');
        });

        Schema::table('llms_documents', function (Blueprint $table) {
            $table->dropUnique(['name', 'locale']);
            $table->dropUnique(['slug', 'locale']);
            $table->dropColumn('locale');
            $table->unique('name');
            $table->unique('slug');
        });

        Schema::table('sitemap_entries', function (Blueprint $table) {
            $table->dropIndex(['sitemap_index_id', 'locale']);
            $table->dropColumn(['locale', 'alternate_urls']);
        });

        Schema::table('sitemap_indexes', function (Blueprint $table) {
            $table->dropIndex(['locale']);
            $table->dropUnique(['name']);
            $table->dropUnique(['filename']);
            $table->dropColumn(['locale', 'model_type']);
            $table->unique('name');
            $table->unique('filename');
        });

        Schema::table('jsonld_schemas', function (Blueprint $table) {
            $table->dropIndex(['locale']);
            $table->dropColumn('locale');
        });

        Schema::table('geo_entity_profiles', function (Blueprint $table) {
            $table->dropUnique(['model_type', 'model_id', 'locale']);
            $table->dropColumn('locale');
            $table->unique(['model_type', 'model_id']);
        });

        Schema::table('seo_meta', function (Blueprint $table) {
            $table->dropUnique(['model_type', 'model_id', 'locale']);
            $table->dropColumn('locale');
            $table->unique(['model_type', 'model_id']);
        });
    }
};
