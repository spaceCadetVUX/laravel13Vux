<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add email_hash column to users table.
 *
 * WHY: email is encrypted at rest with a random IV (non-deterministic).
 * Standard WHERE email = ? queries cannot find encrypted rows.
 * email_hash = hash('sha256', strtolower($email)) is deterministic and
 * safe to index — it reveals nothing about the email content.
 *
 * Auth lookup pattern:
 *   SELECT * FROM users WHERE email_hash = hash('sha256', input_email)
 *   Then verify decrypted email matches (defence-in-depth).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email_hash', 64)->nullable()->after('email');
            $table->index('email_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email_hash']);
            $table->dropColumn('email_hash');
        });
    }
};
