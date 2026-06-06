<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_filter_values', function (Blueprint $table) {
            $table->uuid('product_id');
            $table->unsignedBigInteger('filter_value_id');

            $table->primary(['product_id', 'filter_value_id']);

            $table->foreign('product_id')
                  ->references('id')
                  ->on('products')
                  ->onDelete('cascade');

            $table->foreign('filter_value_id')
                  ->references('id')
                  ->on('filter_values')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_filter_values');
    }
};
