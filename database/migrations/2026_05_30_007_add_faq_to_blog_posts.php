<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->jsonb('faq_items_vi')->nullable()->after('published_at');
            $table->jsonb('faq_items_en')->nullable()->after('faq_items_vi');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn(['faq_items_vi', 'faq_items_en']);
        });
    }
};
