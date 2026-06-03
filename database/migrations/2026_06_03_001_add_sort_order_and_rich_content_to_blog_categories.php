<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_categories', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('is_active');
            $table->index('sort_order');
        });

        Schema::table('blog_category_translations', function (Blueprint $table) {
            $table->text('rich_content')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('blog_categories', function (Blueprint $table) {
            $table->dropIndex(['sort_order']);
            $table->dropColumn('sort_order');
        });

        Schema::table('blog_category_translations', function (Blueprint $table) {
            $table->dropColumn('rich_content');
        });
    }
};
