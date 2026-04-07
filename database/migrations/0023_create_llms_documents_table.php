<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llms_documents', function (Blueprint $table) {
            $table->bigIncrements('id');

            // e.g. 'root', 'products', 'blog' — internal identifier
            $table->string('name', 100)->unique();

            // Route key: 'products' → /llms-products.txt
            $table->string('slug', 100)->unique();

            $table->string('title', 255);
            $table->text('description')->nullable();

            // 'index' = one-liner per entry | 'full' = with facts + FAQ blocks
            $table->string('scope', 50)->default('full');

            // null = site-wide document | set = scoped to one model type
            $table->string('model_type', 255)->nullable();

            $table->integer('entry_count')->default(0);
            $table->timestamp('last_generated_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llms_documents');
    }
};
