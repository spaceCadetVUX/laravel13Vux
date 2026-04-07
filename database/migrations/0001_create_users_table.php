<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            // PK — uuid, public-safe identifier (CLAUDE.md: uuid for user-facing tables)
            $table->uuid('id')->primary();

            $table->string('name');

            // Encrypted at rest — text type (encrypted values exceed varchar limits)
            $table->text('email')->unique();
            $table->text('phone')->nullable();

            // nullable: Google-only accounts have no password
            $table->string('password')->nullable();

            // PHP Enum: UserRole — admin | customer
            $table->enum('role', ['admin', 'customer'])->default('customer');

            // Socialite Google OAuth ID
            $table->string('google_id')->nullable()->unique();

            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken(); // string(100) nullable

            // Soft delete — never forceDelete() on users (CLAUDE.md)
            $table->softDeletes();
            $table->timestamps();

            // Additional indexes for common query patterns
            $table->index('role');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
