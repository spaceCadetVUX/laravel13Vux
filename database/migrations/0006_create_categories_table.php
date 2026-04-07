<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            // PK — bigint auto-increment (not user-facing, CLAUDE.md)
            $table->bigIncrements('id');

            // Self-referencing FK — added after table creation to avoid forward reference
            // nullable: top-level categories have no parent
            $table->unsignedBigInteger('parent_id')->nullable();

            $table->string('name');
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->string('image_path', 500)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            // Soft delete — never forceDelete() on categories (CLAUDE.md)
            $table->softDeletes();
            $table->timestamps();

            // Indexes for common query patterns
            $table->index('parent_id');
            $table->index('is_active');
            $table->index('sort_order');
        });

        // Self-referencing FK added AFTER table creation
        // nullOnDelete: child categories survive parent deletion (become top-level)
        Schema::table('categories', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });

        Schema::dropIfExists('categories');
    }
};
