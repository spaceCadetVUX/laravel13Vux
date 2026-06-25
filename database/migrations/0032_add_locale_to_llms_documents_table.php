<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('llms_documents', function (Blueprint $table) {
            $table->string('locale', 10)->nullable()->default('vi')->after('model_type');
        });
    }

    public function down(): void
    {
        Schema::table('llms_documents', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
