<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_category_translations', function (Blueprint $table) {
            $table->bigIncrements('id');

            // FK → blog_categories.id (bigint PK) — dies with category
            $table->unsignedBigInteger('blog_category_id');
            $table->foreign('blog_category_id')
                ->references('id')
                ->on('blog_categories')
                ->cascadeOnDelete();

            $table->string('locale', 10)->comment('vi | en | ...');

            $table->string('name', 255);
            $table->string('slug', 300);

            $table->text('description')->nullable();

            $table->string('meta_title', 255)->nullable();
            $table->string('meta_description', 500)->nullable();

            $table->timestamps();

            $table->unique(['locale', 'slug']);
            $table->index(['blog_category_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_category_translations');
    }
};
