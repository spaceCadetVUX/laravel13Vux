<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            // Remove flat option columns — replaced by product_variant_options pivot
            if (Schema::hasColumn('product_variants', 'option_name')) {
                $table->dropColumn('option_name');
            }

            if (Schema::hasColumn('product_variants', 'option_value')) {
                $table->dropColumn('option_value');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('option_name', 100)->default('')->after('sale_price');
            $table->string('option_value', 100)->default('')->after('option_name');
        });
    }
};
