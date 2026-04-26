<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_post_translations', function (Blueprint $table) {
            $table->bigIncrements('id');

            // FK → blog_posts.id (uuid PK) — dies with post
            $table->uuid('blog_post_id');
            $table->foreign('blog_post_id')
                ->references('id')
                ->on('blog_posts')
                ->cascadeOnDelete();

            $table->string('locale', 10)->comment('vi | en | ...');

            $table->string('title', 500);
            $table->string('slug', 600);

            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable(); // TinyMCE rich HTML

            $table->string('meta_title', 255)->nullable();
            $table->string('meta_description', 500)->nullable();

            $table->timestamps();

            $table->unique(['locale', 'slug']);
            $table->index(['blog_post_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_translations');
    }
};
