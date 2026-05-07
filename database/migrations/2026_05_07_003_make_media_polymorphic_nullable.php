<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The media library stores standalone files that are not tied to a
     * specific model. model_type / model_id are only populated when a file
     * is explicitly attached to an Eloquent record; standalone uploads must
     * be allowed to leave them null.
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('model_type', 255)->nullable()->change();
            $table->string('model_id', 36)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('model_type', 255)->nullable(false)->change();
            $table->string('model_id', 36)->nullable(false)->change();
        });
    }
};
