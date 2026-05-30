<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'products',
            'categories',
            'blog_posts',
            'blog_categories',
            'brands',
            'manufacturers',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->timestamp('mcp_drafted_at')->nullable()->after('updated_at');
                $table->foreignId('mcp_token_id')
                    ->nullable()
                    ->after('mcp_drafted_at')
                    ->constrained('personal_access_tokens')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'products',
            'categories',
            'blog_posts',
            'blog_categories',
            'brands',
            'manufacturers',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropForeign(['mcp_token_id']);
                $table->dropColumn(['mcp_drafted_at', 'mcp_token_id']);
            });
        }
    }
};
