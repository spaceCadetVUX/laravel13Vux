<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->uuid('product_id');
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();

            // FK → product_images.id (nullable — variant may not have a specific image)
            $table->unsignedBigInteger('image_id')->nullable();
            $table->foreign('image_id')
                ->references('id')
                ->on('product_images')
                ->nullOnDelete();

            $table->string('sku', 100)->unique();
            $table->decimal('price', 12, 2);
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->unsignedInteger('stock_quantity')->default(0);

            // Single option dimension — e.g. option_name="Color", option_value="Red"
            // For multi-dimension variants (Color + Size) a separate variant_options
            // pivot table would be needed — extend later if required.
            $table->string('option_name', 100);
            $table->string('option_value', 100);

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index('product_id');
            $table->index('image_id');
            $table->index(['product_id', 'is_active']);
            $table->index(['product_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
