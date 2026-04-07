<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jsonld_templates', function (Blueprint $table) {
            $table->bigIncrements('id');

            // One template per schema type — e.g. 'Product', 'Article', 'BreadcrumbList'
            $table->string('schema_type', 100)->unique();

            // Display name shown in Filament admin panel
            $table->string('label', 100);

            // Base JSON-LD with {{placeholders}} — jsonb for fast querying
            $table->jsonb('template');

            // Available placeholder keys and their model source field
            $table->jsonb('placeholders')->nullable();

            // true = Observer auto-fills on model save
            // false = Admin manually edited, Observer never overwrites
            $table->boolean('is_auto_generated')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jsonld_templates');
    }
};
