<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LlmsDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $templates = [
            [
                'name'        => 'root',
                'slug'        => 'root',
                'scope'       => 'index',
                'model_type'  => null,
                'vi' => ['title' => 'Tổng quan website', 'description' => 'Tổng hợp tất cả nội dung website — dành cho AI agent đọc tiếng Việt.'],
                'en' => ['title' => 'Site Index',         'description' => 'Top-level llms.txt — one-liner index of all content sections for AI consumption.'],
            ],
            [
                'name'        => 'products',
                'slug'        => 'products',
                'scope'       => 'full',
                'model_type'  => 'App\\Models\\Product',
                'vi' => ['title' => 'Sản phẩm',    'description' => 'Danh sách sản phẩm đầy đủ với thông tin chi tiết dành cho AI agent.'],
                'en' => ['title' => 'Products',     'description' => 'Full product catalogue with facts and FAQ for LLM consumption.'],
            ],
            [
                'name'        => 'blog',
                'slug'        => 'blog',
                'scope'       => 'full',
                'model_type'  => 'App\\Models\\BlogPost',
                'vi' => ['title' => 'Bài viết', 'description' => 'Tổng hợp bài blog với trích đoạn và dữ liệu có cấu trúc dành cho AI.'],
                'en' => ['title' => 'Blog',     'description' => 'Blog posts with excerpts and structured facts for LLM consumption.'],
            ],
            [
                'name'        => 'categories',
                'slug'        => 'categories',
                'scope'       => 'full',
                'model_type'  => 'App\\Models\\Category',
                'vi' => ['title' => 'Danh mục sản phẩm', 'description' => 'Danh mục sản phẩm với mô tả dành cho AI agent.'],
                'en' => ['title' => 'Categories',         'description' => 'Product categories with descriptions for LLM consumption.'],
            ],
            [
                'name'        => 'blog_categories',
                'slug'        => 'blog-categories',
                'scope'       => 'full',
                'model_type'  => 'App\\Models\\BlogCategory',
                'vi' => ['title' => 'Danh mục blog', 'description' => 'Danh mục bài viết với mô tả dành cho AI agent.'],
                'en' => ['title' => 'Blog Categories', 'description' => 'Blog categories with descriptions for LLM consumption.'],
            ],
            [
                'name'        => 'business',
                'slug'        => 'business',
                'scope'       => 'full',
                'model_type'  => null,
                'vi' => ['title' => 'Hồ sơ doanh nghiệp', 'description' => 'Thông tin doanh nghiệp, giờ mở cửa, địa chỉ và các thông tin quan trọng dành cho AI.'],
                'en' => ['title' => 'Business Profile',    'description' => 'Who we are, contact details, hours, and key business facts for AI consumption.'],
            ],
        ];

        foreach ($templates as $tpl) {
            foreach (['vi', 'en'] as $locale) {
                $name = "{$tpl['name']}-{$locale}";
                $slug = "{$tpl['slug']}-{$locale}";

                DB::table('llms_documents')->updateOrInsert(
                    ['name' => $name, 'locale' => $locale],
                    [
                        'name'              => $name,
                        'slug'              => $slug,
                        'title'             => $tpl[$locale]['title'],
                        'description'       => $tpl[$locale]['description'],
                        'scope'             => $tpl['scope'],
                        'model_type'        => $tpl['model_type'],
                        'locale'            => $locale,
                        'entry_count'       => 0,
                        'last_generated_at' => null,
                        'is_active'         => true,
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ]
                );
            }
        }

        $this->command->info('LLMs documents seeded: 12 documents (6 types × vi + en)');
    }
}
