<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Create pivot table ─────────────────────────────────────────────
        Schema::create('category_product', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id');
            $table->uuid('product_id');

            $table->primary(['category_id', 'product_id']);

            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->cascadeOnDelete();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();
        });

        // ── 2. Migrate existing category_id data to pivot ─────────────────────
        DB::table('products')
            ->whereNotNull('category_id')
            ->select('id', 'category_id')
            ->orderBy('id')
            ->each(function ($product) {
                DB::table('category_product')->insertOrIgnore([
                    'category_id' => $product->category_id,
                    'product_id'  => $product->id,
                ]);
            });

        // ── 3. Drop category_id from products ────────────────────────────────
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropIndex(['category_id']);
            $table->dropColumn('category_id');
        });
    }

    public function down(): void
    {
        // Restore category_id (takes first pivot category as the value)
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('id');
            $table->index('category_id');
            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->nullOnDelete();
        });

        DB::table('category_product')
            ->select('product_id', DB::raw('MIN(category_id) as category_id'))
            ->groupBy('product_id')
            ->orderBy('product_id')
            ->each(function ($row) {
                DB::table('products')
                    ->where('id', $row->product_id)
                    ->update(['category_id' => $row->category_id]);
            });

        Schema::dropIfExists('category_product');
    }
};
