<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();

            $table->uuid('product_id');

            $table->string('path', 500);
            $table->string('alt_text', 255)->nullable();
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->index('product_id');
            $table->index('sort_order');

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};