<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_translations', function (Blueprint $table) {
            $table->bigIncrements('id');

            // FK → products.id (uuid PK) — dies with product
            $table->uuid('product_id');
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();

            $table->string('locale', 10)->comment('vi | en | ...');

            $table->string('name', 500);
            $table->string('slug', 600);

            $table->text('short_description')->nullable();
            $table->longText('description')->nullable(); // TinyMCE rich HTML

            // Admin nhập per locale — null = fallback về products.price
            $table->decimal('price', 12, 2)->nullable();
            // 'VND', 'USD' — null = fallback về config('app.default_currency')
            $table->string('currency', 10)->nullable();

            $table->string('meta_title', 255)->nullable();
            $table->string('meta_description', 500)->nullable();

            $table->timestamps();

            // Slug unique per locale — /vi/ao-thun và /en/t-shirt đều hợp lệ
            $table->unique(['locale', 'slug']);

            // Fast lookup: product_id + locale → 1 row
            $table->index(['product_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_translations');
    }
};
