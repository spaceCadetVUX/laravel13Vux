<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_profiles', function (Blueprint $table) {
            $table->id();

            // Identity
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('tagline', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('logo_path', 500)->nullable();

            // Contact
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('address_line', 500)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Business
            $table->string('vat_number', 100)->nullable();
            $table->string('currency', 10)->default('VND');
            $table->smallInteger('founded_year')->nullable();
            $table->jsonb('business_hours')->nullable();
            $table->jsonb('social_links')->nullable();
            $table->jsonb('extra')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_profiles');
    }
};
