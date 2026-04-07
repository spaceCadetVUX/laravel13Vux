<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('blog_comments', function (Blueprint $table) {
            $table->id();

            $table->uuid('blog_post_id');
            $table->uuid('user_id')->nullable();

            $table->text('body');

            $table->boolean('is_approved')->default(false);

            $table->timestamps();

            $table->index('blog_post_id');
            $table->index('user_id');
            $table->index('is_approved');

            $table->foreign('blog_post_id')
                ->references('id')
                ->on('blog_posts')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_comments');
    }
};