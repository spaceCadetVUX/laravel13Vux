<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->uuid('order_id');
            $table->uuid('product_id')->nullable();

            $table->string('product_name', 255);
            $table->string('product_sku', 100);

            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);

            $table->timestamps();

            $table->index('order_id');
            $table->index('product_id');

            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->cascadeOnDelete();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};