<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_option_values', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('option_type_id');
            $table->foreign('option_type_id')
                ->references('id')
                ->on('product_option_types')
                ->cascadeOnDelete();

            $table->string('value', 255);            // "Red", "M", "256GB"
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index('option_type_id');
            $table->index(['option_type_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_option_values');
    }
};
