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

        $locales = [
            'vi' => 'App\\Models\\Category',
            'en' => 'App\\Models\\Category',
        ];

        $types = [
            'products'           => ['model_type' => 'App\\Models\\Product'],
            'product-categories' => ['model_type' => 'App\\Models\\Category'],
            'blog'               => ['model_type' => 'App\\Models\\BlogPost'],
            'blog-categories'    => ['model_type' => 'App\\Models\\BlogCategory'],
        ];

        $indexes = [];
        foreach (['vi', 'en'] as $locale) {
            foreach ($types as $typeSlug => $meta) {
                $name     = "{$locale}-{$typeSlug}";
                $filename = "sitemap-{$locale}-{$typeSlug}.xml";
                $indexes[] = [
                    'name'              => $name,
                    'filename'          => $filename,
                    'url'               => "{$appUrl}/{$filename}",
                    'entry_count'       => 0,
                    'last_generated_at' => null,
                    'is_active'         => true,
                    'locale'            => $locale,
                    'model_type'        => $meta['model_type'],
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }
        }

        foreach ($indexes as $index) {
            DB::table('sitemap_indexes')->updateOrInsert(
                ['name' => $index['name']],
                $index
            );
        }

        $this->command->info('Sitemap indexes seeded: 8 child sitemaps (vi + en × 4 types)');
    }
}
