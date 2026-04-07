<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sitemap_indexes', function (Blueprint $table) {
            $table->bigIncrements('id');

            // e.g. 'products', 'blog', 'categories'
            $table->string('name', 100)->unique();

            // e.g. 'sitemap-products.xml'
            $table->string('filename', 100)->unique();

            // Absolute URL to this child sitemap
            $table->string('url', 500);

            $table->integer('entry_count')->default(0);
            $table->timestamp('last_generated_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sitemap_indexes');
    }
};
