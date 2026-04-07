<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── permissions ───────────────────────────────────────────────────────
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');        // e.g. 'products.create'
            $table->string('guard_name'); // e.g. 'web' | 'api'
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        // ── roles ─────────────────────────────────────────────────────────────
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');        // e.g. 'admin' | 'customer'
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        // ── model_has_permissions ─────────────────────────────────────────────
        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');

            $table->string('model_type');

            // IMPORTANT: string(36) not unsignedBigInteger (Spatie default)
            // Handles uuid PKs ("550e8400-...") and bigint PKs ("12") as string
            // CLAUDE.md: varchar(36) for ALL polymorphic model_id columns
            $table->string('model_id', 36);

            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->onDelete('cascade');

            $table->primary(['permission_id', 'model_type', 'model_id']);
            $table->index(['model_type', 'model_id']);
        });

        // ── model_has_roles ───────────────────────────────────────────────────
        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');

            $table->string('model_type');

            // IMPORTANT: string(36) not unsignedBigInteger (Spatie default)
            $table->string('model_id', 36);

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('cascade');

            $table->primary(['role_id', 'model_type', 'model_id']);
            $table->index(['model_type', 'model_id']);
        });

        // ── role_has_permissions ──────────────────────────────────────────────
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');

            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->onDelete('cascade');

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('cascade');

            $table->primary(['permission_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
