<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_videos', function (Blueprint $table) {
            $table->string('title', 255)->nullable()->after('thumbnail_path');
            $table->text('description')->nullable()->after('title');

            // ISO 8601 duration — e.g. "PT2M30S" (2 min 30 sec)
            // Required by Google VideoObject rich results
            $table->string('duration', 20)->nullable()->after('description');

            $table->unsignedSmallInteger('sort_order')->default(0)->after('duration');

            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('product_videos', function (Blueprint $table) {
            $table->dropIndex(['sort_order']);
            $table->dropColumn(['title', 'description', 'duration', 'sort_order']);
        });
    }
};
