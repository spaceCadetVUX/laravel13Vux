<?php

namespace App\Services\Mcp;

use Illuminate\Support\Str;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductTranslation;
use App\Models\Seo\GeoEntityProfile;
use App\Models\Seo\SeoMeta;
use Illuminate\Support\Facades\DB;

class McpProductService
{
    // ── Context ───────────────────────────────────────────────────────────────

    public function context(string $slug): array
    {
        $product = $this->loadProduct($slug);

        return $this->buildContextResponse($product);
    }

    // ── Readiness ─────────────────────────────────────────────────────────────

    public function readiness(string $slug): array
    {
        $product = $this->loadProduct($slug);

        return $this->computeReadiness($product);
    }

    // ── Upsert ────────────────────────────────────────────────────────────────

    public function upsert(string $slug, array $data, int $tokenId, bool $dryRun): array
    {
        // Captured before potential rollback so dry_run can return the preview
        $preview     = null;
        $autoCreated = [];

        try {
            DB::transaction(function () use ($slug, $data, $tokenId, $dryRun, &$preview, &$autoCreated) {
                $overwrite = (bool) ($data['overwrite_existing'] ?? false);

                // 1a. Resolve brand via _stubs
                $brandId = $this->resolveBrand(
                    $data['brand_slug'] ?? null,
                    $data['_stubs']['brand'] ?? null,
                    $autoCreated,
                );

                // 1b. Resolve manufacturer via _stubs
                $manufacturerId = $this->resolveManufacturer(
                    $data['manufacturer_slug'] ?? null,
                    $data['_stubs']['manufacturer'] ?? null,
                    $autoCreated,
                );

                // 2. Resolve category via _stubs
                $categoryId = $this->resolveCategory(
                    $data['category_slug'] ?? null,
                    $data['_stubs']['category'] ?? null,
                    $autoCreated,
                );

                // 3. Find or create product (restore if soft-deleted)
                $product = Product::withTrashed()->where('slug', $slug)->first();

                if ($product && $product->trashed()) {
                    $product->restore();
                }

                if (! $product) {
                    // price is NOT NULL — default 0 for MCP drafts; admin sets real price later
                    $product = new Product([
                        'slug'     => $slug,
                        'is_active'=> false,
                        'price'    => 0,
                    ]);
                }

                // Base fields — slug/audit always written; all others respect overwrite_existing
                $isNew = !$product->exists;

                $product->slug          = $slug;
                $product->mcp_drafted_at = now();
                $product->mcp_token_id   = $tokenId;

                if (array_key_exists('name', $data) && ($isNew || $overwrite || empty($product->name))) {
                    $product->name = $data['name'];
                }
                if (array_key_exists('sku', $data) && ($isNew || $overwrite || empty($product->sku))) {
                    $product->sku = $data['sku'];
                }
                // price: 0 is the draft default — treat as "not set"
                if (array_key_exists('price', $data) && ($isNew || $overwrite || empty($product->price))) {
                    $product->price = $data['price'];
                }
                if (array_key_exists('sale_price', $data) && ($isNew || $overwrite || is_null($product->sale_price))) {
                    $product->sale_price = $data['sale_price'];
                }
                if (array_key_exists('currency', $data) && ($isNew || $overwrite || empty($product->currency))) {
                    $product->currency = $data['currency'];
                }
                if (array_key_exists('stock_quantity', $data) && ($isNew || $overwrite || is_null($product->stock_quantity))) {
                    $product->stock_quantity = $data['stock_quantity'];
                }
                if ($brandId !== null && ($isNew || $overwrite || is_null($product->brand_id))) {
                    $product->brand_id = $brandId;
                }
                if ($manufacturerId !== null && ($isNew || $overwrite || is_null($product->manufacturer_id))) {
                    $product->manufacturer_id = $manufacturerId;
                }

                // ── FAQ (legacy faq_items_vi/en) ──────────────────────────────
                foreach (['faq_items_vi', 'faq_items_en'] as $field) {
                    if (!array_key_exists($field, $data)) continue;
                    if ($overwrite || empty($product->$field)) {
                        $product->$field = $data[$field];
                    }
                }

                $product->save();

                // 4. Sync category (pivot)
                if ($categoryId) {
                    $product->categories()->syncWithoutDetaching([$categoryId]);
                }

                // 5. Translations
                if (! empty($data['translations'])) {
                    $this->writeTranslations($product, $data['translations'], $overwrite);
                }

                // 5b. Auto-promote root price/sale_price/currency → per-locale translations.
                // Skip any locale that was explicitly provided in translations — those already have correct per-locale values.
                if (array_key_exists('price', $data) && ! empty($data['price'])) {
                    $explicitLocales = array_keys($data['translations'] ?? []);

                    foreach (['vi', 'en'] as $locale) {
                        if (in_array($locale, $explicitLocales, true)) {
                            continue; // locale had explicit price — don't overwrite with root VND
                        }

                        $tr = $product->translations()->where('locale', $locale)->first();
                        if (! $tr) continue;

                        $dirty = false;
                        if ($overwrite || empty($tr->price)) {
                            $tr->price = $data['price'];
                            $dirty = true;
                        }
                        if (array_key_exists('sale_price', $data) && ($overwrite || is_null($tr->sale_price))) {
                            $tr->sale_price = $data['sale_price'];
                            $dirty = true;
                        }
                        if (array_key_exists('currency', $data) && ($overwrite || empty($tr->currency))) {
                            $tr->currency = $data['currency'];
                            $dirty = true;
                        }
                        if ($dirty) $tr->save();
                    }
                }

                // 6. SEO meta
                if (! empty($data['seo'])) {
                    $this->writeSeoMeta($product, $data['seo'], $overwrite);
                }

                // 7. Attributes
                if (isset($data['attributes'])) {
                    $this->writeAttributes($product, $data['attributes']);
                }

                // 8. Geo profiles (AI Context + Key Facts + FAQ)
                // geo[locale].faq takes priority; fallback to faq_items_vi/en
                $geoData = $data['geo'] ?? [];
                foreach (['vi' => 'faq_items_vi', 'en' => 'faq_items_en'] as $locale => $field) {
                    if (array_key_exists($field, $data) && !array_key_exists('faq', $geoData[$locale] ?? [])) {
                        $geoData[$locale]['faq'] = $data[$field];
                    }
                }
                if (!empty($geoData)) {
                    $this->writeGeoProfiles($product, $geoData, $overwrite);

                    // Sync geo[locale].faq → faq_items_vi/en so Filament FAQ tab stays in sync.
                    $faqSynced = false;
                    foreach (['vi' => 'faq_items_vi', 'en' => 'faq_items_en'] as $locale => $field) {
                        if (isset($geoData[$locale]['faq']) && ($overwrite || empty($product->$field))) {
                            $product->$field = $geoData[$locale]['faq'];
                            $faqSynced       = true;
                        }
                    }
                    if ($faqSynced) {
                        $product->save();
                    }
                }

                // Build the response BEFORE rolling back (dry_run needs this)
                $product->refresh()->load(['brand', 'manufacturer', 'categories.translations', 'translations', 'seoMetas', 'attributes', 'geoProfiles']);
                $preview = $this->buildContextResponse($product);

                if ($dryRun) {
                    // Force rollback — no changes written to DB
                    throw new \RuntimeException('__mcp_dry_run__');
                }
            });

        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== '__mcp_dry_run__') {
                throw $e;
            }
            // Dry run complete — transaction rolled back, preview captured
        }

        $response = ['data' => $preview];

        if (! empty($autoCreated)) {
            $response['auto_created'] = $autoCreated;
        }

        return $response;
    }

    // ── Activate ──────────────────────────────────────────────────────────────

    public function activate(string $slug): array
    {
        $product  = $this->loadProduct($slug);
        $readiness = $this->computeReadiness($product);

        if (! $readiness['ready']) {
            abort(422, implode('; ', $readiness['blocking_issues']));
        }

        $product->update([
            'is_active'      => true,
            'mcp_drafted_at' => null,
            'mcp_token_id'   => null,
        ]);

        $product->refresh()->load(['brand', 'manufacturer', 'categories.translations', 'translations', 'seoMetas', 'attributes', 'geoProfiles']);

        return ['data' => $this->buildContextResponse($product)];
    }

    // ── Private: stub resolvers ───────────────────────────────────────────────

    private function resolveManufacturer(?string $slug, ?array $stub, array &$autoCreated): ?int
    {
        if (blank($slug)) {
            return null;
        }

        $existing = Manufacturer::where('slug', $slug)->first();
        if ($existing) {
            return $existing->id;
        }

        if (empty($stub)) {
            abort(422, "Manufacturer '{$slug}' not found and no _stubs.manufacturer provided.");
        }

        $manufacturer = Manufacturer::create([
            'slug'      => $stub['slug'] ?? $slug,
            'name'      => $stub['name'],
            'country'   => $stub['country'] ?? null,
            'website'   => $stub['website'] ?? null,
            'is_active' => false,
        ]);

        $autoCreated[] = [
            'type'     => 'manufacturer',
            'slug'     => $manufacturer->slug,
            'name'     => $manufacturer->name,
            'is_active'=> false,
            'fill_url' => "PUT /api/v1/mcp/manufacturers/{$manufacturer->slug}",
        ];

        return $manufacturer->id;
    }

    private function resolveBrand(?string $slug, ?array $stub, array &$autoCreated): ?int
    {
        if (blank($slug)) {
            return null;
        }

        $existing = Brand::where('slug', $slug)->first();
        if ($existing) {
            return $existing->id;
        }

        if (empty($stub)) {
            abort(422, "Brand '{$slug}' not found and no _stubs.brand provided.");
        }

        $brand = Brand::create([
            'slug'      => $stub['slug'] ?? $slug,
            'name'      => $stub['name'],
            'website'   => $stub['website'] ?? null,
            'is_active' => false,
        ]);

        $autoCreated[] = [
            'type'     => 'brand',
            'slug'     => $brand->slug,
            'name'     => $brand->name,
            'is_active'=> false,
            'fill_url' => "PUT /api/v1/mcp/brands/{$brand->slug}",
        ];

        return $brand->id;
    }

    private function resolveCategory(?string $slug, ?array $stub, array &$autoCreated): ?int
    {
        if (blank($slug)) {
            return null;
        }

        $existing = Category::where('slug', $slug)->first();
        if ($existing) {
            return $existing->id;
        }

        if (empty($stub)) {
            abort(422, "Category '{$slug}' not found and no _stubs.category provided.");
        }

        $translations = $stub['translations'] ?? [];
        $viName = $translations['vi']['name'] ?? $slug;
        $enName = $translations['en']['name'] ?? $slug;

        $category = Category::create([
            'slug'      => $stub['slug'] ?? $slug,
            'name'      => $enName,
            'is_active' => false,
        ]);

        foreach (['vi' => $viName, 'en' => $enName] as $locale => $name) {
            $t = $translations[$locale] ?? [];
            CategoryTranslation::create([
                'category_id' => $category->id,
                'locale'      => $locale,
                'name'        => $t['name'] ?? $name,
                'slug'        => $t['slug'] ?? $stub['slug'] ?? $slug,
            ]);
        }

        $autoCreated[] = [
            'type'     => 'category',
            'slug'     => $category->slug,
            'is_active'=> false,
            'fill_url' => "PUT /api/v1/mcp/categories/{$category->slug}",
        ];

        return $category->id;
    }

    // ── Private: write helpers ────────────────────────────────────────────────

    private function writeTranslations(Product $product, array $translations, bool $overwrite): void
    {
        foreach ($translations as $locale => $data) {
            if (! in_array($locale, ['vi', 'en'], true)) {
                continue;
            }

            $translation = ProductTranslation::firstOrNew([
                'product_id' => $product->id,
                'locale'     => $locale,
            ]);

            if ($translation->exists && $translation->is_mcp_protected) {
                continue; // translation is human-written — never overwrite
            }

            $writeable = ['name', 'description', 'short_description', 'price', 'sale_price', 'currency'];

            foreach ($writeable as $field) {
                if (! isset($data[$field])) {
                    continue;
                }

                if (! $overwrite && $translation->exists && filled($translation->{$field})) {
                    continue;
                }

                $translation->{$field} = $data[$field];
            }

            // Auto-generate slug from name — never let AI set slug directly
            if (filled($data['name'] ?? null) && ($overwrite || ! filled($translation->slug))) {
                $translation->slug = Str::slug($data['name']);
            }

            $translation->save();
        }
    }

    private function writeSeoMeta(Product $product, array $seo, bool $overwrite): void
    {
        foreach ($seo as $locale => $data) {
            if (! in_array($locale, ['vi', 'en'], true)) {
                continue;
            }

            $seoMeta = SeoMeta::firstOrNew([
                'model_type' => 'product',
                'model_id'   => $product->id,
                'locale'     => $locale,
            ]);

            if ($seoMeta->exists && $seoMeta->is_mcp_protected) {
                continue;
            }

            $writeable = ['meta_title', 'meta_description', 'meta_keywords', 'canonical_url', 'og_title', 'og_description', 'og_image', 'robots'];

            foreach ($writeable as $field) {
                if (! isset($data[$field])) {
                    continue;
                }

                if (! $overwrite && $seoMeta->exists && filled($seoMeta->{$field})) {
                    continue;
                }

                $seoMeta->{$field} = $data[$field];
            }

            // Default robots
            if (blank($seoMeta->robots)) {
                $seoMeta->robots = 'index, follow';
            }

            $seoMeta->model_type = 'product';
            $seoMeta->model_id   = $product->id;
            $seoMeta->locale     = $locale;
            $seoMeta->save();
        }
    }

    private function writeAttributes(Product $product, array $attributes): void
    {
        $product->attributes()->delete();

        foreach ($attributes as $i => $attr) {
            ProductAttribute::create([
                'product_id' => $product->id,
                'name'       => $attr['name'],
                'name_en'    => $attr['name_en'] ?? null,
                'value'      => $attr['value'],
                'value_en'   => $attr['value_en'] ?? null,
                'unit'       => $attr['unit'] ?? null,
                'sort_order' => $i,
            ]);
        }
    }

    private function writeGeoProfiles(Product $product, array $geo, bool $overwrite): void
    {
        $writeable = ['ai_summary', 'use_cases', 'target_audience', 'llm_context_hint', 'key_facts'];

        $normalize = fn (array $items): array => collect($items)
            ->filter(fn (array $item): bool => filled($item['question'] ?? null))
            ->map(fn (array $item): array => [
                'question' => trim($item['question']),
                'answer'   => trim($item['answer'] ?? ''),
            ])
            ->values()
            ->toArray();

        foreach ($geo as $locale => $data) {
            if (! in_array($locale, ['vi', 'en'], true)) {
                continue;
            }

            $profile = GeoEntityProfile::firstOrNew([
                'model_type' => 'product',
                'model_id'   => $product->id,
                'locale'     => $locale,
            ]);

            foreach ($writeable as $field) {
                if (! isset($data[$field])) {
                    continue;
                }

                if (! $overwrite && $profile->exists && filled($profile->{$field})) {
                    continue;
                }

                $profile->{$field} = $data[$field];
            }

            if (array_key_exists('faq', $data)) {
                $normalized = $normalize((array) $data['faq']);
                if ($overwrite || empty($profile->faq)) {
                    $profile->faq = $normalized;
                }
            }

            $profile->model_type = 'product';
            $profile->model_id   = $product->id;
            $profile->locale     = $locale;
            $profile->save();
        }
    }

    // ── Private: readiness ────────────────────────────────────────────────────

    private function computeReadiness(Product $product): array
    {
        $checks   = [];
        $blocking = [];
        $warnings = [];
        $score    = 0;

        // Per-locale checks
        foreach (['vi', 'en'] as $locale) {
            $translation = $product->translations->firstWhere('locale', $locale);
            $seoMeta     = $product->seoMetas->firstWhere('locale', $locale);

            $descLen      = mb_strlen($translation?->description ?? '');
            $shortDescLen = mb_strlen($translation?->short_description ?? '');
            $metaTitle    = $seoMeta?->meta_title ?? '';  // meta_title lives in seo_meta, not translation
            $metaDesc     = $seoMeta?->meta_description ?? '';
            $metaTitleLen = mb_strlen($metaTitle);
            $geoProfile   = $product->geoProfiles->firstWhere('locale', $locale);
            $faqItems     = $geoProfile?->faq ?? ($locale === 'vi' ? ($product->faq_items_vi ?? []) : ($product->faq_items_en ?? []));
            $faqCount     = count((array) $faqItems);

            $hasDesc      = $descLen > 0;
            $hasShortDesc = $shortDescLen > 0;
            $hasMeta      = mb_strlen($metaTitle) > 0;
            $hasMetaDesc  = mb_strlen($metaDesc) > 0;

            $checks[$locale] = [
                'has_description'        => ['pass' => $hasDesc,      'value' => $descLen],
                'description_min_length' => ['pass' => $descLen >= 100, 'min' => 100, 'value' => $descLen],
                'has_short_description'  => ['pass' => $hasShortDesc],
                'has_meta_title'         => ['pass' => $hasMeta],
                'meta_title_length'      => ['pass' => $metaTitleLen <= 70, 'value' => $metaTitleLen, 'max' => 70],
                'has_meta_description'   => ['pass' => $hasMetaDesc],
                'has_faq'                => ['pass' => $faqCount >= 1, 'count' => $faqCount],
            ];

            // Blocking
            if (! $hasDesc)      { $blocking[] = "{$locale}.description missing"; }
            if (! $hasMeta)      { $blocking[] = "{$locale}.meta_title missing"; }
            if (! $hasMetaDesc)  { $blocking[] = "{$locale}.meta_description missing"; }

            // Warnings
            if ($hasDesc && $descLen < 100) {
                $warnings[] = "{$locale}.description quá ngắn ({$descLen}/100 ký tự)";
            }
            if ($hasMeta && $metaTitleLen > 70) {
                $warnings[] = "{$locale}.meta_title quá dài ({$metaTitleLen}/70 ký tự)";
            }
            if ($faqCount === 0) {
                $warnings[] = "{$locale}.faq chưa có — nên thêm ít nhất 3 câu hỏi";
            }

            // Score (15 + 15 + 5 + 5 + 10 + 10 per locale = 60 total)
            if ($hasDesc)      { $score += 15; }
            if ($hasShortDesc) { $score += 5; }
            if ($hasMeta)      { $score += 10; }
            if ($hasMetaDesc)  { $score += 10; }
        }

        // General checks
        $categories     = $product->categories;
        $hasCategory    = $categories->isNotEmpty();
        $hasManufacturer= filled($product->manufacturer_id);
        $hasSku         = filled($product->sku);
        $allCatActive   = $hasCategory && $categories->every(fn ($c) => $c->is_active);
        $inactiveCats   = $categories->where('is_active', false)->pluck('slug')->join(', ');

        $checks['general'] = [
            'has_sku'            => ['pass' => $hasSku],
            'has_category'       => ['pass' => $hasCategory],
            'has_manufacturer'   => ['pass' => $hasManufacturer],
            'category_is_active' => ['pass' => $allCatActive],
        ];

        $hasPrice = $product->price !== null && $product->price > 0;
        $checks['general']['has_price'] = ['pass' => $hasPrice, 'value' => (float) $product->price];

        if (! $hasSku)         { $blocking[] = 'general.has_sku — SKU chưa được set'; }
        if (! $hasPrice)       { $blocking[] = 'general.has_price — price = 0 hoặc chưa được set'; }
        if (! $hasCategory)    { $blocking[] = 'general.has_category — product chưa có danh mục'; }
        if (! $hasManufacturer){ $blocking[] = 'general.has_manufacturer — product chưa có nhà sản xuất'; }
        if ($hasCategory && ! $allCatActive) {
            $blocking[] = "general.category_is_active — category '{$inactiveCats}' chưa active";
        }

        // General scoring: sku=5, price=5, category=10, manufacturer=5, cat_active=5 → max 30
        // Per-locale: desc=15, short_desc=5, meta_title=10, meta_desc=10 → 40×2=80
        // Total max = 110 → converted to 0–100% for consistency with other entities
        if ($hasSku)         { $score += 5; }
        if ($hasPrice)       { $score += 5; }
        if ($hasCategory)    { $score += 10; }
        if ($hasManufacturer){ $score += 5; }
        if ($allCatActive)   { $score += 5; }

        $scorePercent = (int) round(($score / 110) * 100);

        return [
            'slug'            => $this->productSlug($product),
            'score'           => $scorePercent,
            'ready'           => empty($blocking),
            'checks'          => $checks,
            'blocking_issues' => $blocking,
            'warnings'        => $warnings,
        ];
    }

    // ── Private: response builder ─────────────────────────────────────────────

    private function buildContextResponse(Product $product): array
    {
        $translationsOut = [];
        foreach ($product->translations as $t) {
            $translationsOut[$t->locale] = [
                'name'              => $t->name,
                'slug'              => $t->slug,
                'description'       => $t->description,
                'short_description' => $t->short_description,
                'price'             => $t->price !== null ? (string) $t->price : null,
                'sale_price'        => $t->sale_price !== null ? (string) $t->sale_price : null,
                'currency'          => $t->currency,
                'is_mcp_protected'  => (bool) $t->is_mcp_protected,
            ];
        }

        $geoOut = [];
        foreach ($product->geoProfiles as $g) {
            $geoOut[$g->locale] = [
                'ai_summary'       => $g->ai_summary,
                'use_cases'        => $g->use_cases,
                'target_audience'  => $g->target_audience,
                'llm_context_hint' => $g->llm_context_hint,
                'key_facts'        => $g->key_facts ?? [],
                'faq'              => $g->faq ?? [],
            ];
        }

        $seoOut = [];
        foreach ($product->seoMetas as $s) {
            $seoOut[$s->locale] = [
                'meta_title'       => $s->meta_title,
                'meta_description' => $s->meta_description,
                'meta_keywords'    => $s->meta_keywords,
                'canonical_url'    => $s->canonical_url,
                'og_title'         => $s->og_title,
                'og_description'   => $s->og_description,
                'og_image'         => $s->og_image,
                'robots'           => $s->robots,
                'is_mcp_protected' => (bool) $s->is_mcp_protected,
            ];
        }

        $firstCategory = $product->categories->first();

        $categoriesOut = $product->categories->map(function ($cat) {
            $catTranslations = [];
            foreach ($cat->translations as $ct) {
                $catTranslations[$ct->locale] = ['name' => $ct->name, 'slug' => $ct->slug];
            }
            return [
                'slug'         => $cat->slug,
                'is_active'    => (bool) $cat->is_active,
                'translations' => $catTranslations,
            ];
        })->values()->all();

        // Related: other active products in same categories (limit 5)
        $relatedProducts = [];
        if ($firstCategory) {
            $related = Product::active()
                ->whereHas('categories', fn ($q) => $q->where('categories.id', $firstCategory->id))
                ->where('id', '!=', $product->id)
                ->with('translations')
                ->limit(5)
                ->get();

            foreach ($related as $r) {
                $relatedProducts[] = [
                    'slug' => $r->slug,
                    'name' => $r->translations->firstWhere('locale', 'vi')?->name
                              ?? $r->translations->firstWhere('locale', 'en')?->name
                              ?? $r->slug,
                ];
            }
        }

        return [
            'slug'           => $this->productSlug($product),
            'name'           => $product->name,
            'sku'            => $product->sku,
            'price'          => $product->price !== null ? (float) $product->price : null,
            'sale_price'     => $product->sale_price !== null ? (float) $product->sale_price : null,
            'currency'       => $product->currency,
            'stock_quantity' => $product->stock_quantity,
            'is_active'      => (bool) $product->is_active,
            'mcp_drafted_at' => $product->mcp_drafted_at?->toIso8601String(),
            'brand'          => $product->brand
                ? ['slug' => $product->brand->slug, 'name' => $product->brand->name]
                : null,
            'manufacturer'   => $product->manufacturer
                ? ['slug' => $product->manufacturer->slug, 'name' => $product->manufacturer->name]
                : null,
            'categories'      => $categoriesOut,
            'attributes'      => $product->attributes->map(fn ($a) => [
                'name'     => $a->name,
                'name_en'  => $a->name_en,
                'value'    => $a->value,
                'value_en' => $a->value_en,
                'unit'     => $a->unit,
            ])->all(),
            'translations'    => $translationsOut,
            'geo'             => $geoOut,
            'seo'             => $seoOut,
            'faq_items_vi'    => $product->faq_items_vi ?? [],
            'faq_items_en'    => $product->faq_items_en ?? [],
            'related_products'=> $relatedProducts,
        ];
    }

    // ── Private: helpers ──────────────────────────────────────────────────────

    private function loadProduct(string $slug): Product
    {
        $product = Product::where('slug', $slug)
            ->with(['brand', 'manufacturer', 'categories.translations', 'translations', 'seoMetas', 'attributes', 'geoProfiles'])
            ->first();

        if (! $product) {
            abort(404, "Product '{$slug}' not found.");
        }

        return $product;
    }

    private function productSlug(Product $product): string
    {
        return $product->slug;
    }
}
