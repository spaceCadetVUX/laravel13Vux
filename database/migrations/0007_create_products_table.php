<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            // PK — uuid, public-safe identifier (CLAUDE.md: uuid for user-facing tables)
            $table->uuid('id')->primary();

            // FK → categories.id — nullOnDelete: product survives category deletion
            $table->unsignedBigInteger('category_id')->nullable();
            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->nullOnDelete();

            $table->string('name');
            $table->string('slug', 255)->unique();
            $table->string('sku', 100)->unique();

            $table->text('short_description')->nullable();   // plain text
            $table->longText('description')->nullable();     // TinyMCE rich HTML

            // Monetary values — decimal(12,2) for PostgreSQL (CLAUDE.md)
            $table->decimal('price', 12, 2);
            $table->decimal('sale_price', 12, 2)->nullable(); // null = not on sale

            $table->integer('stock_quantity')->default(0);
            $table->boolean('is_active')->default(true);

            // Soft delete — never forceDelete() on products (CLAUDE.md)
            $table->softDeletes();
            $table->timestamps();

            // Indexes for common query patterns
            $table->index('category_id');
            $table->index('is_active');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
