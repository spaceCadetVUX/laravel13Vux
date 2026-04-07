<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SitemapIndexSeeder extends Seeder
{
    public function run(): void
    {
        $appUrl = rtrim(config('app.url'), '/');
        $now    = now();

        $indexes = [
            [
                'name'             => 'products',
                'filename'         => 'sitemap-products.xml',
                'url'              => "{$appUrl}/sitemap-products.xml",
                'entry_count'      => 0,
                'last_generated_at' => null,
                'is_active'        => true,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            [
                'name'             => 'blog',
                'filename'         => 'sitemap-blog.xml',
                'url'              => "{$appUrl}/sitemap-blog.xml",
                'entry_count'      => 0,
                'last_generated_at' => null,
                'is_active'        => true,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            [
                'name'             => 'categories',
                'filename'         => 'sitemap-categories.xml',
                'url'              => "{$appUrl}/sitemap-categories.xml",
                'entry_count'      => 0,
                'last_generated_at' => null,
                'is_active'        => true,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
        ];

        foreach ($indexes as $index) {
            DB::table('sitemap_indexes')->updateOrInsert(
                ['name' => $index['name']],
                $index
            );
        }

        $this->command->info('Sitemap indexes seeded: products, blog, categories');
    }
}
