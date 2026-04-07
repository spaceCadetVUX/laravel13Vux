<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->bigIncrements('id');

            // e.g. 'default', 'product', 'order'
            $table->string('log_name', 255)->nullable();

            $table->text('description');

            // CRITICAL: string(36) — CLAUDE.md polymorphic model_id rule
            $table->string('subject_type', 255)->nullable();
            $table->string('subject_id', 36)->nullable();

            // Who caused the action — usually App\Models\User
            $table->string('causer_type', 255)->nullable();
            $table->string('causer_id', 36)->nullable();

            // Spatie activitylog stores old/new values here
            $table->jsonb('properties')->nullable();

            $table->timestamps();

            // Polymorphic lookup indexes
            $table->index(['subject_type', 'subject_id']);
            $table->index(['causer_type', 'causer_id']);
            $table->index('log_name');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
