<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();

            // IMPORTANT: string(36) not morphs() default (unsignedBigInteger)
            // Handles both uuid PKs ("550e8400-...") and bigint PKs ("12") as string
            // CLAUDE.md: varchar(36) for ALL polymorphic model_id columns
            $table->string('tokenable_type');
            $table->string('tokenable_id', 36);

            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['tokenable_type', 'tokenable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
