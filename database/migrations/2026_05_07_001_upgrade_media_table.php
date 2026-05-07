<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            // Original filename as uploaded by user — shown in Media Library
            $table->string('original_name', 255)->nullable()->after('collection');

            // SHA-256 of file content — used for deduplication
            // Same content = same hash = reuse existing file on disk
            $table->string('hash', 64)->nullable()->unique()->after('original_name');

            // Optional human-friendly display name, editable in Media Library
            $table->string('title', 255)->nullable()->after('hash');

            // Relative path to resized thumbnail (400px) — null for non-image files
            $table->string('thumb_path', 500)->nullable()->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropUnique(['hash']);
            $table->dropColumn(['original_name', 'hash', 'title', 'thumb_path']);
        });
    }
};
