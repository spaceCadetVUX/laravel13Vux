<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('brand_id')->nullable()->after('sku');
            $table->unsignedBigInteger('manufacturer_id')->nullable()->after('brand_id');

            $table->foreign('brand_id')
                ->references('id')
                ->on('brands')
                ->nullOnDelete();

            $table->foreign('manufacturer_id')
                ->references('id')
                ->on('manufacturers')
                ->nullOnDelete();

            $table->index('brand_id');
            $table->index('manufacturer_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->dropForeign(['manufacturer_id']);
            $table->dropIndex(['brand_id']);
            $table->dropIndex(['manufacturer_id']);
            $table->dropColumn(['brand_id', 'manufacturer_id']);
        });
    }
};
