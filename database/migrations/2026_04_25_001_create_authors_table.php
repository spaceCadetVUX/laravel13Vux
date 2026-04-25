<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authors', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Optional link to an admin User account.
            // null → guest author (external contributor without an account).
            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            // ── Identity ──────────────────────────────────────────────────────
            $table->string('name', 255);
            $table->string('slug', 255)->unique();

            // Job title shown on the author page — helps Google E-E-A-T signals.
            // e.g. "Senior Editor", "KNX Systems Engineer"
            $table->string('title', 255)->nullable();

            // Short bio displayed on author page and in Article JSON-LD.
            $table->text('bio')->nullable();

            // Profile photo stored in public disk.
            $table->string('avatar', 500)->nullable();

            // ── Social / web presence — used in JSON-LD Person.sameAs ─────────
            $table->string('website',  500)->nullable();
            $table->string('twitter',  500)->nullable();
            $table->string('linkedin', 500)->nullable();
            $table->string('facebook', 500)->nullable();

            // Topic areas — jsonb for fast querying and future filtering.
            // e.g. ["Smart Home", "KNX", "DALI", "IoT"]
            $table->jsonb('expertise')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('user_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authors');
    }
};
