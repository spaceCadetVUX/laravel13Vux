<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Standalone — không có parent model, không cần FK
        // page_key là internal identifier: 'about', 'contact', 'faq'
        Schema::create('page_translations', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Internal identifier — e.g. 'about', 'contact', 'faq', 'terms'
            $table->string('page_key', 100)->comment('about | contact | faq | terms');

            $table->string('locale', 10)->comment('vi | en | ...');

            $table->string('title', 255);
            $table->string('slug', 255)->comment('URL segment: about | lien-he | gioi-thieu');

            $table->longText('body')->nullable(); // TinyMCE rich HTML

            $table->string('meta_title', 255)->nullable();
            $table->string('meta_description', 500)->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Slug unique per locale
            $table->unique(['locale', 'slug']);
            // One page per locale
            $table->unique(['locale', 'page_key']);

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_translations');
    }
};
