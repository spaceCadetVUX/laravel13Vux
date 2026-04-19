<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->bigIncrements('id');

            // FK → products.id (uuid)
            $table->uuid('product_id');
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();

            // FK → users.id (uuid, nullable — guest reviews allowed)
            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            // Review content
            $table->string('author', 100);
            $table->string('title', 255)->nullable();
            $table->tinyInteger('rating')->unsigned(); // 1–5
            $table->text('content');

            // Moderation
            $table->boolean('is_approved')->default(false);

            $table->timestamps();

            // Indexes
            $table->index('product_id');
            $table->index('user_id');
            $table->index(['product_id', 'is_approved']); // common query: approved reviews per product
            $table->index('rating');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
