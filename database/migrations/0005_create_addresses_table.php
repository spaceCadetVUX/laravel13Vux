<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            // PK — uuid (user-facing table, CLAUDE.md)
            $table->uuid('id')->primary();

            // FK → users.id — address dies with user (CASCADE)
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            // PHP Enum: AddressLabel — home | office | other
            $table->enum('label', ['home', 'office', 'other'])->default('home');

            $table->string('full_name');

            // Encrypted at rest — text type (CLAUDE.md)
            $table->text('phone');
            $table->text('address_line');

            $table->string('city', 100);
            $table->string('district', 100);
            $table->string('ward', 100);

            $table->boolean('is_default')->default(false);

            $table->timestamps();

            // Indexes for common query patterns
            $table->index('user_id');
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
