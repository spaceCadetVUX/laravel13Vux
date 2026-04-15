<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Add optional price override to product_images ─────────────────
        // null = use product's listed price, value = override price for this image
        Schema::table('product_images', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->nullable()->after('sort_order');
        });

        // ── 2. Pivot: product_image ↔ category ───────────────────────────────
        // Tags each image with the subset of the product's own categories
        Schema::create('category_product_image', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('product_image_id');

            $table->primary(['category_id', 'product_image_id']);

            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->cascadeOnDelete();

            $table->foreign('product_image_id')
                ->references('id')
                ->on('product_images')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_product_image');

        Schema::table('product_images', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
