<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JsonldTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $templates = [

            // ── a) Product ────────────────────────────────────────────────────
            [
                'schema_type'      => 'Product',
                'label'            => 'Product Schema',
                'is_auto_generated' => true,
                'template'         => json_encode([
                    '@context'    => 'https://schema.org',
                    '@type'       => 'Product',
                    'name'        => '{{product.name}}',
                    'description' => '{{product.short_description}}',
                    'sku'         => '{{product.sku}}',
                    'image'       => '{{product.first_image_url}}',
                    'url'         => '{{product.canonical_url}}',
                    'offers'      => [
                        '@type'           => 'Offer',
                        'price'           => '{{product.price}}',
                        'priceCurrency'   => '{{product.price_currency}}',
                        'availability'    => '{{product.availability}}',
                        'url'             => '{{product.canonical_url}}',
                    ],
                ]),
                'placeholders'     => json_encode([
                    '{{product.name}}'               => 'name',
                    '{{product.slug}}'               => 'slug',
                    '{{product.short_description}}'  => 'short_description',
                    '{{product.sku}}'                => 'sku',
                    '{{product.price}}'              => 'price',
                    '{{product.price_currency}}'     => 'price_currency',
                    '{{product.first_image_url}}'    => 'first_image_url',
                    '{{product.canonical_url}}'      => 'canonical_url',
                    '{{product.availability}}'       => 'availability',
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ── b) Article (BlogPost) ─────────────────────────────────────────
            [
                'schema_type'      => 'Article',
                'label'            => 'Article Schema',
                'is_auto_generated' => true,
                'template'         => json_encode([
                    '@context'      => 'https://schema.org',
                    '@type'         => 'Article',
                    'headline'      => '{{blog_post.title}}',
                    'description'   => '{{blog_post.excerpt}}',
                    'image'         => '{{blog_post.featured_image}}',
                    'datePublished' => '{{blog_post.published_at}}',
                    'author'        => [
                        '@type' => 'Person',
                        'name'  => '{{blog_post.author_name}}',
                    ],
                    'url'           => '{{blog_post.canonical_url}}',
                ]),
                'placeholders'     => json_encode([
                    '{{blog_post.title}}'          => 'title',
                    '{{blog_post.slug}}'           => 'slug',
                    '{{blog_post.excerpt}}'        => 'excerpt',
                    '{{blog_post.featured_image}}' => 'featured_image_url',
                    '{{blog_post.published_at}}'   => 'published_at',
                    '{{blog_post.author_name}}'    => 'author.name',
                    '{{blog_post.canonical_url}}'  => 'canonical_url',
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ── c) CollectionPage (Category) ──────────────────────────────────
            [
                'schema_type'      => 'CollectionPage',
                'label'            => 'Collection Page Schema (Category)',
                'is_auto_generated' => true,
                'template'         => json_encode([
                    '@context' => 'https://schema.org',
                    '@type'    => 'CollectionPage',
                    'name'     => '{{category.name}}',
                    'url'      => '{{category.canonical_url}}',
                ]),
                'placeholders'     => json_encode([
                    '{{category.name}}'          => 'name',
                    '{{category.slug}}'          => 'slug',
                    '{{category.canonical_url}}' => 'canonical_url',
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ── d) BreadcrumbList (shared) ────────────────────────────────────
            [
                'schema_type'      => 'BreadcrumbList',
                'label'            => 'Breadcrumb List Schema',
                'is_auto_generated' => true,
                'template'         => json_encode([
                    '@context'        => 'https://schema.org',
                    '@type'           => 'BreadcrumbList',
                    'itemListElement' => [],   // populated by JsonldService at render time
                ]),
                'placeholders'     => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ── e) FAQPage (shared) ───────────────────────────────────────────
            [
                'schema_type'      => 'FAQPage',
                'label'            => 'FAQ Page Schema',
                'is_auto_generated' => true,
                'template'         => json_encode([
                    '@context'   => 'https://schema.org',
                    '@type'      => 'FAQPage',
                    'mainEntity' => [],   // populated from geo_entity_profiles.faq_items
                ]),
                'placeholders'     => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ── f) WebSite (static, site-wide) ───────────────────────────────
            [
                'schema_type'      => 'WebSite',
                'label'            => 'WebSite Schema',
                'is_auto_generated' => false,   // manually managed — no model observer
                'template'         => json_encode([
                    '@context'        => 'https://schema.org',
                    '@type'           => 'WebSite',
                    'name'            => '{{site.name}}',
                    'url'             => '{{site.url}}',
                    'potentialAction' => [
                        '@type'       => 'SearchAction',
                        'target'      => '{{site.url}}/search?q={search_term_string}',
                        'query-input' => 'required name=search_term_string',
                    ],
                ]),
                'placeholders'     => json_encode([
                    '{{site.name}}' => 'config:app.name',
                    '{{site.url}}'  => 'config:app.url',
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ── g) Organization (static, site-wide) ──────────────────────────
            [
                'schema_type'      => 'Organization',
                'label'            => 'Organization Schema',
                'is_auto_generated' => false,   // manually managed — no model observer
                'template'         => json_encode([
                    '@context' => 'https://schema.org',
                    '@type'    => 'Organization',
                    'name'     => '{{site.name}}',
                    'url'      => '{{site.url}}',
                    'logo'     => '{{site.logo_url}}',
                ]),
                'placeholders'     => json_encode([
                    '{{site.name}}'     => 'config:app.name',
                    '{{site.url}}'      => 'config:app.url',
                    '{{site.logo_url}}' => 'config:seo.logo_url',
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],

        ];

        foreach ($templates as $template) {
            DB::table('jsonld_templates')->updateOrInsert(
                ['schema_type' => $template['schema_type']],
                $template
            );
        }

        $this->command->info('JSON-LD templates seeded: ' . count($templates) . ' templates');
    }
}
