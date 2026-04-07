<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('parent_id')->nullable();

            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('parent_id');
            $table->index('is_active');
        });

        // FK AFTER create (self reference)
        Schema::table('blog_categories', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('blog_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_categories');
    }
};