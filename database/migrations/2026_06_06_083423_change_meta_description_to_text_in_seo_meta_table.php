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
        Schema::table('seo_meta', function (Blueprint $table) {
            $table->text('meta_description')->nullable()->change();
            $table->text('og_description')->nullable()->change();
            $table->text('twitter_description')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('seo_meta', function (Blueprint $table) {
            $table->string('meta_description', 320)->nullable()->change();
            $table->string('og_description', 320)->nullable()->change();
            $table->string('twitter_description', 320)->nullable()->change();
        });
    }
};
