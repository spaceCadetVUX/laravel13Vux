<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_option_types', function (Blueprint $table) {
            $table->bigIncrements('id');

            // uuid — direct FK to products.id (native uuid type; varchar(36) is only for polymorphic morphs)
            $table->uuid('product_id');
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();

            $table->string('name', 100);             // "Color", "Size", "Storage"
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index('product_id');
            $table->index(['product_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_option_types');
    }
};
