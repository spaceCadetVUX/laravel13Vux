<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_videos', function (Blueprint $table) {
            // PK — bigint auto-increment (child table, CLAUDE.md)
            $table->bigIncrements('id');

            // FK → products.id — cascadeOnDelete: videos die with product
            $table->uuid('product_id');
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();

            $table->string('path', 500);
            $table->string('thumbnail_path', 500)->nullable();

            $table->timestamps();

            // Index
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_videos');
    }
};
