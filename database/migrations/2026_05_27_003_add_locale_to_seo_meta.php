<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('seo_meta', 'locale')) {
            return;
        }

        Schema::table('seo_meta', function (Blueprint $table) {
            $table->string('locale', 10)->default('vi')->after('model_id');
        });

        DB::table('seo_meta')->update(['locale' => 'vi']);

        Schema::table('seo_meta', function (Blueprint $table) {
            $table->dropUnique(['model_type', 'model_id']);
            $table->unique(['model_type', 'model_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::table('seo_meta', function (Blueprint $table) {
            $table->dropUnique(['model_type', 'model_id', 'locale']);
            $table->unique(['model_type', 'model_id']);
            $table->dropColumn('locale');
        });
    }
};
