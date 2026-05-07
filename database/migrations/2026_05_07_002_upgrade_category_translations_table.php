<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_translations', function (Blueprint $table) {
            $table->longText('rich_content')->nullable()->after('description');

            $table->string('og_title', 160)->nullable()->after('meta_description');
            $table->string('og_description', 320)->nullable()->after('og_title');

            $table->string('twitter_title', 160)->nullable()->after('og_description');
            $table->string('twitter_description', 320)->nullable()->after('twitter_title');
        });
    }

    public function down(): void
    {
        Schema::table('category_translations', function (Blueprint $table) {
            $table->dropColumn([
                'rich_content',
                'og_title',
                'og_description',
                'twitter_title',
                'twitter_description',
            ]);
        });
    }
};
