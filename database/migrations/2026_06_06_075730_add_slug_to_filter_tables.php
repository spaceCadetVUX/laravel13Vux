<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('filter_groups', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name_en');
        });

        Schema::table('filter_values', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name_en');
            $table->unique(['filter_group_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('filter_values', function (Blueprint $table) {
            $table->dropUnique(['filter_group_id', 'slug']);
            $table->dropColumn('slug');
        });

        Schema::table('filter_groups', function (Blueprint $table) {
            $table->dropUnique(['filter_groups_slug_unique']);
            $table->dropColumn('slug');
        });
    }
};
