<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // MCP creates drafts without knowing the SKU yet.
            // SKU is filled in by admin or via a later MCP upsert.
            // Readiness check enforces SKU before activation.
            $table->string('sku', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sku', 100)->nullable(false)->change();
        });
    }
};
