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
        Schema::create('filter_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('filter_group_id');
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('filter_group_id')
                  ->references('id')
                  ->on('filter_groups')
                  ->onDelete('cascade');

            $table->index('filter_group_id');
            $table->index('sort_order');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('filter_values');
    }
};
