<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LlmsDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $documents = [
            [
                'name'             => 'root',
                'slug'             => 'root',
                'title'            => 'Site Index',
                'description'      => 'Top-level llms.txt — one-liner index of all content sections.',
                'scope'            => 'index',
                'model_type'       => null,
                'entry_count'      => 0,
                'last_generated_at' => null,
                'is_active'        => true,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            [
                'name'             => 'products',
                'slug'             => 'products',
                'title'            => 'Products',
                'description'      => 'Full product catalogue with facts and FAQ for LLM consumption.',
                'scope'            => 'full',
                'model_type'       => 'App\\Models\\Product',
                'entry_count'      => 0,
                'last_generated_at' => null,
                'is_active'        => true,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            [
                'name'             => 'blog',
                'slug'             => 'blog',
                'title'            => 'Blog',
                'description'      => 'Blog posts with excerpts and structured facts for LLM consumption.',
                'scope'            => 'full',
                'model_type'       => 'App\\Models\\BlogPost',
                'entry_count'      => 0,
                'last_generated_at' => null,
                'is_active'        => true,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            [
                'name'             => 'categories',
                'slug'             => 'categories',
                'title'            => 'Categories',
                'description'      => 'Product categories with descriptions for LLM consumption.',
                'scope'            => 'full',
                'model_type'       => 'App\\Models\\Category',
                'entry_count'      => 0,
                'last_generated_at' => null,
                'is_active'        => true,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
        ];

        $documents[] = [
            'name'              => 'business',
            'slug'              => 'business',
            'title'             => 'Business Profile',
            'description'       => 'Who we are, contact details, hours, and key business facts for AI consumption.',
            'scope'             => 'full',
            'model_type'        => null,
            'entry_count'       => 0,
            'last_generated_at' => null,
            'is_active'         => true,
            'created_at'        => $now,
            'updated_at'        => $now,
        ];

        foreach ($documents as $document) {
            DB::table('llms_documents')->updateOrInsert(
                ['name' => $document['name']],
                $document
            );
        }

        $this->command->info('LLMs documents seeded: root, products, blog, categories, business');
    }
}
