<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'product_translations',
            'category_translations',
            'blog_post_translations',
            'blog_category_translations',
            'seo_meta',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->boolean('is_mcp_protected')->default(false)->after('locale');
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'product_translations',
            'category_translations',
            'blog_post_translations',
            'blog_category_translations',
            'seo_meta',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn('is_mcp_protected');
            });
        }
    }
};
