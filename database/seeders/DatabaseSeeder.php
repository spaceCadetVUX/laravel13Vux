<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Run in strict dependency order.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,           // 1. roles must exist before user assignment
            AdminUserSeeder::class,      // 2. admin user + Spatie role assignment
            JsonldTemplateSeeder::class, // 3. base JSON-LD templates
            SitemapIndexSeeder::class,   // 4. sitemap child index registry
            LlmsDocumentSeeder::class,   // 5. llms.txt document registry
        ]);
    }
}
