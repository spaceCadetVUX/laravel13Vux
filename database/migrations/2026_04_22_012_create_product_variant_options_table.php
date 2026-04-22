<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pivot: one variant row per (variant, option_value) pair.
        // A variant with Color=Red + Size=M has 2 rows in this table.
        Schema::create('product_variant_options', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('variant_id');
            $table->foreign('variant_id')
                ->references('id')
                ->on('product_variants')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('option_value_id');
            $table->foreign('option_value_id')
                ->references('id')
                ->on('product_option_values')
                ->cascadeOnDelete();

            $table->timestamps();

            // One option value per variant per option type
            $table->unique(['variant_id', 'option_value_id']);

            $table->index('variant_id');
            $table->index('option_value_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_options');
    }
};
