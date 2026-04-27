<?php

namespace App\Services\Seo;

use App\Enums\JsonldSchemaType;
use App\Models\Product;
use App\Models\Seo\JsonldSchema;
use App\Models\Seo\JsonldTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Services\Seo\BusinessJsonldService;

class JsonldService
{
    /**
     * Schema types applicable to each morph alias.
     * BreadcrumbList is included for every public model.
     * FAQPage for products is generated conditionally — only when geoProfile.faq has data.
     */
    private const MODEL_SCHEMA_TYPES = [
        'product'       => [JsonldSchemaType::Product,        JsonldSchemaType::BreadcrumbList],
        'blog_post'     => [JsonldSchemaType::Article,        JsonldSchemaType::BreadcrumbList],
        'category'      => [JsonldSchemaType::CollectionPage, JsonldSchemaType::BreadcrumbList],
        'blog_category' => [JsonldSchemaType::CollectionPage, JsonldSchemaType::BreadcrumbList],
    ];

    /**
     * Front-end URL prefix per morph alias.
     */
    private const URL_PREFIXES = [
        'product'       => '/products/',
        'blog_post'     => '/blog/',
        'category'      => '/categories/',
        'blog_category' => '/blog/category/',
    ];

    /**
     * Render order for <head> — lower = earlier.
     */
    private const SORT_ORDER = [
        JsonldSchemaType::Product->value        => 10,
        JsonldSchemaType::Article->value        => 10,
        JsonldSchemaType::CollectionPage->value => 10,
        JsonldSchemaType::FaqPage->value        => 50,
        JsonldSchemaType::VideoObject->value    => 60,
        JsonldSchemaType::BreadcrumbList->value => 90,
    ];

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Sync all applicable auto-generated JSON-LD schemas for a model.
     * Skips rows where is_auto_generated=false (manual admin overrides).
     *
     * Product-specific enrichment is applied after placeholder resolution:
     *   - brand + manufacturer (relationships)
     *   - aggregateRating (from approved reviews)
     *   - additionalProperty (from product_attributes)
     *   - image array (all product images)
     *
     * FAQPage and VideoObject schemas for products are handled separately
     * after the main loop since they depend on conditional data.
     */
    public function syncForModel(Model $model, string $locale = 'vi'): void
    {
        $morphAlias  = $model->getMorphClass();
        $schemaTypes = self::MODEL_SCHEMA_TYPES[$morphAlias] ?? [];

        if (empty($schemaTypes)) {
            return;
        }

        foreach ($schemaTypes as $schemaType) {
            $template = $this->getTemplateForType($schemaType);

            // No template seeded / template is not auto-generated → skip.
            if ($template === null || ! $template->is_auto_generated) {
                continue;
            }

            // Never overwrite a manually curated schema.
            $hasManualOverride = JsonldSchema::where('model_type', $morphAlias)
                ->where('model_id', $model->getKey())
                ->where('schema_type', $schemaType->value)
                ->where('is_auto_generated', false)
                ->exists();

            if ($hasManualOverride) {
                continue;
            }

            $resolved = $this->resolvePlaceholders($template->template ?? [], $model);

            // Model-specific enrichments applied after placeholder resolution.
            if ($morphAlias === 'product') {
                if ($schemaType === JsonldSchemaType::Product) {
                    $resolved = $this->enrichProductSchema($resolved, $model);
                }

                if ($schemaType === JsonldSchemaType::BreadcrumbList) {
                    $resolved = $this->buildProductBreadcrumb($model);
                }
            }

            if ($morphAlias === 'blog_post') {
                if ($schemaType === JsonldSchemaType::Article) {
                    $resolved = $this->enrichArticleSchema($resolved, $model);
                }

                if ($schemaType === JsonldSchemaType::BreadcrumbList) {
                    $resolved = $this->buildBlogPostBreadcrumb($model);
                }
            }

            if ($morphAlias === 'category') {
                if ($schemaType === JsonldSchemaType::BreadcrumbList) {
                    $resolved = $this->buildCategoryBreadcrumb($model);
                }
            }

            if ($morphAlias === 'blog_category') {
                if ($schemaType === JsonldSchemaType::BreadcrumbList) {
                    $resolved = $this->buildBlogCategoryBreadcrumb($model);
                }
            }

            JsonldSchema::updateOrCreate(
                [
                    'model_type'  => $morphAlias,
                    'model_id'    => $model->getKey(),
                    'schema_type' => $schemaType->value,
                    'locale'      => $locale,
                ],
                [
                    'label'             => $template->label,
                    'locale'            => $locale,
                    'payload'           => $resolved,
                    'is_active'         => true,
                    'is_auto_generated' => true,
                    'sort_order'        => self::SORT_ORDER[$schemaType->value] ?? 50,
                ]
            );
        }

        // ── Product-only conditional schemas ──────────────────────────────────
        if ($morphAlias === 'product') {
            $this->syncVideoObjectsForProduct($model, $locale);
        }

        // ── FAQPage — any model with geoProfile.faq data ──────────────────────
        if (in_array($morphAlias, ['product', 'blog_post'], true)) {
            $this->syncFaqPage($model, $locale);
        }
    }

    /**
     * Fetch a JsonldTemplate by schema type.
     * Result is cached in Redis for 60 minutes to avoid repeated DB hits
     * on high-traffic observer dispatches.
     * Falls back to a direct DB query if Redis is unavailable.
     */
    public function getTemplateForType(JsonldSchemaType $type): ?JsonldTemplate
    {
        $cacheKey = "jsonld_template:{$type->value}";

        try {
            /** @var ?JsonldTemplate */
            return Cache::store('redis')->remember(
                $cacheKey,
                now()->addMinutes(60),
                fn (): ?JsonldTemplate => JsonldTemplate::where('schema_type', $type->value)->first()
            );
        } catch (\Throwable) {
            // Redis unavailable (e.g. local dev without Redis extension) — hit DB directly.
            return JsonldTemplate::where('schema_type', $type->value)->first();
        }
    }

    /**
     * Walk a template array recursively and replace {{prefix.field}} tokens
     * with real values derived from the model.
     *
     * Pattern: {{morph_alias.field_name}}
     * The prefix is intentionally ignored so the same resolver works for every
     * model type. Only the field name after the dot matters.
     */
    public function resolvePlaceholders(array $template, Model $model): array
    {
        $valueMap = $this->buildValueMap($model);

        array_walk_recursive($template, function (mixed &$value) use ($valueMap): void {
            if (! is_string($value)) {
                return;
            }

            $value = preg_replace_callback(
                '/\{\{[^.}]+\.([^}]+)\}\}/',
                function (array $matches) use ($valueMap): string {
                    return isset($valueMap[$matches[1]])
                        ? (string) $valueMap[$matches[1]]
                        : '';
                },
                $value
            );
        });

        return $template;
    }

    /**
     * Return all active JSON-LD schemas for a model, ordered for <head> output.
     * Used by the API to feed the Nuxt <JsonldRenderer> component.
     */
    public function getActiveSchemas(Model $model): Collection
    {
        return JsonldSchema::where('model_type', $model->getMorphClass())
            ->where('model_id', $model->getKey())
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Build a Schema.org BreadcrumbList payload from a list of items.
     *
     * @param  array<int, array{name: string, url: string}>  $items
     *         Ordered from root → current page.
     */
    public function buildBreadcrumbSchema(array $items): array
    {
        $listElements = [];

        foreach ($items as $position => $item) {
            $listElements[] = [
                '@type'    => 'ListItem',
                'position' => $position + 1,
                'name'     => $item['name'] ?? '',
                'item'     => $item['url']  ?? '',
            ];
        }

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $listElements,
        ];
    }

    /**
     * Build a locale-aware Schema.org Product schema for Blade rendering.
     * Uses the translation record for name, slug, description, price, currency.
     */
    public function buildProductSchema(Product $product, string $locale): array
    {
        $t = $product->translation($locale);

        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => $t?->name ?? $product->name,
            'url'         => route('product.show', ['locale' => $locale, 'slug' => $t?->slug ?? $product->slug]),
            'description' => strip_tags($t?->short_description ?? ''),
            'sku'         => $product->sku,
            'offers'      => [
                '@type'         => 'Offer',
                'priceCurrency' => $t?->currency ?? config('app.default_currency'),
                'price'         => $t?->price ?? $product->price,
                'availability'  => $product->stock_quantity > 0
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
            ],
        ];
    }

    /**
     * Build a Schema.org BreadcrumbList from a simple items array.
     * Public counterpart for use in controllers/views.
     *
     * @param  array<int, array{name: string, url: string}>  $items
     */
    public function buildBreadcrumb(array $items): array
    {
        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => collect($items)->map(fn ($item, $i) => [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $item['name'],
                'item'     => $item['url'],
            ])->values()->all(),
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Enrich the resolved Product schema payload with relationship data
     * that cannot be expressed as simple {{placeholder}} template tokens.
     *
     * Added fields:
     *   brand            → { @type: Brand, name: ... }
     *   manufacturer     → { @type: Organization, name: ... }
     *   image            → array of all product image URLs (replaces single URL)
     *   aggregateRating  → { @type: AggregateRating, ... } from approved reviews
     *   additionalProperty → [ { @type: PropertyValue, ... } ] from product_attributes
     */
    private function enrichProductSchema(array $payload, Model $model): array
    {
        // ── Brand ─────────────────────────────────────────────────────────────
        if (method_exists($model, 'brand')) {
            $model->loadMissing('brand');
            $brand = $model->getRelationValue('brand');
            if ($brand && filled($brand->name)) {
                $brandSchema = ['@type' => 'Brand', 'name' => $brand->name];

                // Official brand website → helps Google disambiguate the entity
                if (filled($brand->website)) {
                    $brandSchema['url']    = $brand->website;
                    $brandSchema['sameAs'] = $brand->website;
                }

                // Brand logo → used by Google Knowledge Panel
                if (filled($brand->logo)) {
                    $baseUrl = rtrim((string) (config('seo.app_url') ?: config('app.url')), '/');
                    $brandSchema['logo'] = $baseUrl . '/storage/' . ltrim((string) $brand->logo, '/');
                }

                $payload['brand'] = $brandSchema;
            }
        }

        // ── Manufacturer ──────────────────────────────────────────────────────
        if (method_exists($model, 'manufacturer')) {
            $model->loadMissing('manufacturer');
            $mfr = $model->getRelationValue('manufacturer');
            if ($mfr && filled($mfr->name)) {
                $mfrSchema = ['@type' => 'Organization', 'name' => $mfr->name];

                // Official manufacturer website
                if (filled($mfr->website)) {
                    $mfrSchema['url']    = $mfr->website;
                    $mfrSchema['sameAs'] = $mfr->website;
                }

                // Country of origin → useful context for Google
                if (filled($mfr->country)) {
                    $mfrSchema['address'] = ['@type' => 'PostalAddress', 'addressCountry' => $mfr->country];
                }

                $payload['manufacturer'] = $mfrSchema;
            }
        }

        // ── Images array (all images, not just first) ─────────────────────────
        if (method_exists($model, 'images')) {
            $model->loadMissing('images');
            $images = $model->getRelationValue('images');
            if ($images && $images->isNotEmpty()) {
                $urls = $images
                    ->map(fn ($img): string => (string) ($img->url ?? ''))
                    ->filter()
                    ->values()
                    ->all();

                if (! empty($urls)) {
                    // Single image → string; multiple images → array (schema.org spec)
                    $payload['image'] = count($urls) === 1 ? $urls[0] : $urls;
                }
            }
        }

        // ── AggregateRating from approved reviews ─────────────────────────────
        // Only injected when there is at least 1 approved review.
        // Google requires reviewCount ≥ 1 to show star ratings in search results.
        if (method_exists($model, 'approvedReviews')) {
            $model->loadMissing('approvedReviews');
            $reviews = $model->getRelationValue('approvedReviews');

            if ($reviews && $reviews->count() > 0) {
                $payload['aggregateRating'] = [
                    '@type'       => 'AggregateRating',
                    'ratingValue' => round((float) $reviews->avg('rating'), 1),
                    'reviewCount' => $reviews->count(),
                    'bestRating'  => 5,
                    'worstRating' => 1,
                ];
            }
        }

        // ── additionalProperty from product_attributes ────────────────────────
        // Maps to Schema.org PropertyValue — helps Google understand product specs.
        // Uses getRelationValue() to avoid conflict with Eloquent's $attributes magic.
        if (method_exists($model, 'attributes')) {
            try {
                $model->loadMissing('attributes');
                $attrs = $model->getRelationValue('attributes');

                if ($attrs && $attrs->isNotEmpty()) {
                    $payload['additionalProperty'] = $attrs
                        ->map(fn ($a): array => [
                            '@type' => 'PropertyValue',
                            'name'  => (string) $a->name,
                            'value' => (string) $a->value,
                        ])
                        ->values()
                        ->all();
                }
            } catch (\Throwable) {
                // Silently skip — not all models have an attributes relationship.
            }
        }

        // ── @id — canonical entity identifier ────────────────────────────────
        // Google uses @id for entity disambiguation across pages.
        if (isset($payload['url']) && ! isset($payload['@id'])) {
            $payload['@id'] = $payload['url'];
        }

        // ── Offers — single Offer vs AggregateOffer + variant array ──────────
        // Google supports both. When variants exist, AggregateOffer with lowPrice/
        // highPrice + individual Offer per active variant is more informative and
        // allows Google to show price ranges in Shopping and rich results.
        $payload['offers'] = $this->buildOffersPayload($model, $payload);

        return $payload;
    }

    /**
     * Build the offers payload for a Product schema.
     *
     * Logic:
     *   - No active variants → single Offer from product.price (simple product).
     *   - Has active variants → AggregateOffer (lowPrice/highPrice) wrapping
     *     an array of individual Offer objects, one per active variant.
     *
     * Google spec:
     *   https://schema.org/AggregateOffer
     *   https://developers.google.com/search/docs/appearance/structured-data/product
     */
    private function buildOffersPayload(Model $model, array $payload): array
    {
        $currency   = (string) ($payload['offers']['priceCurrency'] ?? config('seo.currency', 'VND'));
        $productUrl = (string) ($payload['url'] ?? '');

        // ── Try to load active variants ───────────────────────────────────────
        if (method_exists($model, 'activeVariants')) {
            $model->loadMissing('activeVariants.optionValues.optionType');
            $variants = $model->getRelationValue('activeVariants');

            if ($variants && $variants->isNotEmpty()) {

                // Effective selling price per variant (sale_price takes precedence).
                $prices = $variants
                    ->map(fn ($v): float => (float) ($v->sale_price ?? $v->price))
                    ->filter(fn (float $p): bool => $p > 0);

                if ($prices->isNotEmpty()) {
                    $lowPrice  = $prices->min();
                    $highPrice = $prices->max();

                    $offerList = $variants->map(function ($variant) use ($currency, $productUrl): array {
                        $offer = [
                            '@type'         => 'Offer',
                            'sku'           => $variant->sku,
                            'price'         => (float) ($variant->sale_price ?? $variant->price),
                            'priceCurrency' => $currency,
                            'availability'  => ((int) $variant->stock_quantity) > 0
                                ? 'https://schema.org/InStock'
                                : 'https://schema.org/OutOfStock',
                            'url'           => $productUrl,
                        ];

                        // Combination label e.g. "Red / M" — requires loaded relation.
                        $label = $variant->combination_label;
                        if (filled($label)) {
                            $offer['name'] = $label;
                        }

                        return $offer;
                    })->values()->all();

                    // ── Edge case: all variants same price ────────────────────
                    // AggregateOffer with lowPrice = highPrice looks wrong in
                    // search results ("500.000 ₫ – 500.000 ₫"). Use single Offer.
                    if ($lowPrice === $highPrice) {
                        $anyInStock = $variants->contains(
                            fn ($v): bool => ((int) $v->stock_quantity) > 0
                        );

                        return [
                            '@type'         => 'Offer',
                            'price'         => $lowPrice,
                            'priceCurrency' => $currency,
                            'availability'  => $anyInStock
                                ? 'https://schema.org/InStock'
                                : 'https://schema.org/OutOfStock',
                            'offerCount'    => $variants->count(),
                            'url'           => $productUrl,
                        ];
                    }

                    // ── Multiple prices → AggregateOffer ─────────────────────
                    // Top-level availability = InStock if ANY variant has stock.
                    $anyInStock = $variants->contains(
                        fn ($v): bool => ((int) $v->stock_quantity) > 0
                    );

                    return [
                        '@type'         => 'AggregateOffer',
                        'lowPrice'      => $lowPrice,
                        'highPrice'     => $highPrice,
                        'offerCount'    => $variants->count(),
                        'priceCurrency' => $currency,
                        'availability'  => $anyInStock
                            ? 'https://schema.org/InStock'
                            : 'https://schema.org/OutOfStock',
                        'offers'        => $offerList,
                    ];
                }
            }
        }

        // ── Fallback: simple product — single Offer ───────────────────────────
        // Cast price to float (template substitution always produces strings).
        $singleOffer = $payload['offers'] ?? [];
        if (isset($singleOffer['price'])) {
            $singleOffer['price'] = (float) $singleOffer['price'];
        }

        return $singleOffer;
    }

    /**
     * Build a BreadcrumbList payload for a product from its first category.
     * Structure: Home → {Category} → {Product}
     *
     * Falls back to Home → Product if no categories are assigned.
     * Breadcrumbs are built at save time as a best-effort approximation;
     * the frontend may override with a more accurate render-time breadcrumb.
     */
    private function buildProductBreadcrumb(Model $model): array
    {
        $baseUrl = rtrim((string) (config('seo.app_url') ?: config('app.url')), '/');
        $name    = (string) ($model->getAttribute('name') ?? '');
        $slug    = (string) ($model->getAttribute('slug') ?? '');

        $items = [
            ['name' => 'Home', 'url' => $baseUrl],
        ];

        // Use the first category as the middle breadcrumb level.
        if (method_exists($model, 'categories')) {
            $model->loadMissing('categories');
            $categories = $model->getRelationValue('categories');

            if ($categories && $categories->isNotEmpty()) {
                $cat     = $categories->first();
                $items[] = [
                    'name' => (string) ($cat->name ?? ''),
                    'url'  => $baseUrl . '/categories/' . ($cat->slug ?? ''),
                ];
            }
        }

        $items[] = ['name' => $name, 'url' => $baseUrl . '/products/' . $slug];

        return $this->buildBreadcrumbSchema($items);
    }

    /**
     * Build a BreadcrumbList payload for a blog post.
     * Structure: Home → Blog → [{Category} →] {Post}
     *
     * The category level is included only when a blogCategory is assigned.
     * Falls back to Home → Blog → Post.
     */
    private function buildBlogPostBreadcrumb(Model $model): array
    {
        $baseUrl = rtrim((string) (config('seo.app_url') ?: config('app.url')), '/');
        $title   = (string) ($model->getAttribute('title') ?? '');
        $slug    = (string) ($model->getAttribute('slug') ?? '');

        $items = [
            ['name' => 'Home', 'url' => $baseUrl],
            ['name' => 'Blog', 'url' => $baseUrl . '/blog'],
        ];

        // Include category as a middle level when assigned.
        if (method_exists($model, 'blogCategory')) {
            $model->loadMissing('blogCategory');
            $category = $model->getRelationValue('blogCategory');

            if ($category && filled($category->name)) {
                $items[] = [
                    'name' => (string) $category->name,
                    'url'  => $baseUrl . '/blog/category/' . ($category->slug ?? ''),
                ];
            }
        }

        $items[] = ['name' => $title, 'url' => $baseUrl . '/blog/' . $slug];

        return $this->buildBreadcrumbSchema($items);
    }

    /**
     * Build a BreadcrumbList payload for a catalog category page.
     * Structure: Home → [{Parent} →] {Category}
     *
     * The parent level is included only when parent_id is set.
     * Falls back to Home → Category for top-level categories.
     * Prevents duplicate breadcrumb structure across tree levels.
     */
    private function buildCategoryBreadcrumb(Model $model): array
    {
        $baseUrl = rtrim((string) (config('seo.app_url') ?: config('app.url')), '/');
        $name    = (string) ($model->getAttribute('name') ?? '');
        $slug    = (string) ($model->getAttribute('slug') ?? '');

        $items = [
            ['name' => 'Home', 'url' => $baseUrl],
        ];

        if (method_exists($model, 'parent')) {
            $model->loadMissing('parent');
            $parent = $model->getRelationValue('parent');

            if ($parent && filled($parent->name)) {
                $items[] = [
                    'name' => (string) $parent->name,
                    'url'  => $baseUrl . '/categories/' . ($parent->slug ?? ''),
                ];
            }
        }

        $items[] = ['name' => $name, 'url' => $baseUrl . '/categories/' . $slug];

        return $this->buildBreadcrumbSchema($items);
    }

    /**
     * Build a BreadcrumbList payload for a blog category page.
     * Structure: Home → Blog → [{Parent category} →] {Category}
     *
     * The parent level is included only when a parent_id is assigned.
     * Falls back to Home → Blog → Category.
     */
    private function buildBlogCategoryBreadcrumb(Model $model): array
    {
        $baseUrl = rtrim((string) (config('seo.app_url') ?: config('app.url')), '/');
        $name    = (string) ($model->getAttribute('name') ?? '');
        $slug    = (string) ($model->getAttribute('slug') ?? '');

        $items = [
            ['name' => 'Home', 'url' => $baseUrl],
            ['name' => 'Blog', 'url' => $baseUrl . '/blog'],
        ];

        // Include parent category as a middle level when assigned.
        if (method_exists($model, 'parent')) {
            $model->loadMissing('parent');
            $parent = $model->getRelationValue('parent');

            if ($parent && filled($parent->name)) {
                $items[] = [
                    'name' => (string) $parent->name,
                    'url'  => $baseUrl . '/blog/category/' . ($parent->slug ?? ''),
                ];
            }
        }

        $items[] = ['name' => $name, 'url' => $baseUrl . '/blog/category/' . $slug];

        return $this->buildBreadcrumbSchema($items);
    }

    /**
     * Generate a FAQPage schema for any model that has geoProfile.faq data.
     * Supports products and blog posts. Skips if: no FAQ data, manual override
     * exists, or geoProfile is missing.
     */
    private function syncFaqPage(Model $model, string $locale = 'vi'): void
    {
        $morphAlias = $model->getMorphClass();
        $model->loadMissing('geoProfiles');
        $geoProfile = $model->geoProfile();
        $faq        = (array) ($geoProfile?->faq ?? []);

        if (empty($faq)) {
            return;
        }

        // Never overwrite a manually curated FAQ schema.
        $hasManualOverride = JsonldSchema::where('model_type', $morphAlias)
            ->where('model_id', $model->getKey())
            ->where('schema_type', JsonldSchemaType::FaqPage->value)
            ->where('is_auto_generated', false)
            ->exists();

        if ($hasManualOverride) {
            return;
        }

        $mainEntity = collect($faq)
            ->map(fn (array $item): array => [
                '@type' => 'Question',
                'name'  => trim((string) ($item['question'] ?? '')),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => trim((string) ($item['answer'] ?? '')),
                ],
            ])
            ->filter(fn (array $q): bool => filled($q['name']))
            ->values()
            ->all();

        if (empty($mainEntity)) {
            return;
        }

        JsonldSchema::updateOrCreate(
            [
                'model_type'  => $morphAlias,
                'model_id'    => $model->getKey(),
                'schema_type' => JsonldSchemaType::FaqPage->value,
                'locale'      => $locale,
            ],
            [
                'label'             => 'FAQ Schema',
                'locale'            => $locale,
                'payload'           => [
                    '@context'   => 'https://schema.org',
                    '@type'      => 'FAQPage',
                    'mainEntity' => $mainEntity,
                ],
                'is_active'         => true,
                'is_auto_generated' => true,
                'sort_order'        => self::SORT_ORDER[JsonldSchemaType::FaqPage->value] ?? 50,
            ]
        );
    }

    /**
     * Enrich the resolved Article schema payload with author Person data.
     *
     * Replaces the flat author.name placeholder with a full Person object:
     *   - name, jobTitle, url (author page)
     *   - image (avatar)
     *   - sameAs array (website, LinkedIn, Twitter, Facebook)
     *
     * Falls back to the simple { @type: Person, name: "..." } when no author
     * profile is assigned.
     */
    private function enrichArticleSchema(array $payload, Model $model): array
    {
        $baseUrl = rtrim((string) (config('seo.app_url') ?: config('app.url')), '/');

        // ── @id — canonical entity identifier ────────────────────────────────
        if (isset($payload['url']) && ! isset($payload['@id'])) {
            $payload['@id'] = $payload['url'];
        }

        // ── mainEntityOfPage — ties the Article to its canonical WebPage ─────
        // Google uses this to associate the structured data block with the page URL.
        if (isset($payload['url']) && ! isset($payload['mainEntityOfPage'])) {
            $payload['mainEntityOfPage'] = [
                '@type' => 'WebPage',
                '@id'   => $payload['url'],
            ];
        }

        // ── Author — full Person schema ───────────────────────────────────────
        if (method_exists($model, 'author')) {
            $model->loadMissing('author');
            $author = $model->getRelationValue('author');

            if ($author) {
                $person = [
                    '@type' => 'Person',
                    'name'  => (string) $author->name,
                ];

                if (filled($author->title)) {
                    $person['jobTitle'] = $author->title;
                }

                if (filled($author->slug)) {
                    $person['url'] = $baseUrl . '/authors/' . $author->slug;
                }

                if ($avatarUrl = $author->avatar_url) {
                    $person['image'] = $avatarUrl;
                }

                if (filled($author->bio)) {
                    $person['description'] = $author->bio;
                }

                $sameAs = $author->same_as;
                if (! empty($sameAs)) {
                    $person['sameAs'] = count($sameAs) === 1 ? $sameAs[0] : $sameAs;
                }

                $payload['author'] = $person;
            }
        }

        // ── Publisher — Organization block from BusinessProfile ──────────────
        // Required by Google for Article rich results; uses live business data
        // (name, logo) instead of hardcoded config values.
        if (! isset($payload['publisher'])) {
            $payload['publisher'] = app(BusinessJsonldService::class)->publisherBlock();
        }

        return $payload;
    }

    /**
     * Generate a VideoObject schema for each active product video IF it has
     * title + description (minimum required by Google for VideoObject rich results).
     * Skips videos that are missing required SEO fields.
     */
    private function syncVideoObjectsForProduct(Model $model, string $locale = 'vi'): void
    {
        if (! method_exists($model, 'videos')) {
            return;
        }

        $morphAlias = $model->getMorphClass();
        $baseUrl    = rtrim((string) (config('seo.app_url') ?: config('app.url')), '/');
        $slug       = (string) ($model->getAttribute('slug') ?? '');

        $model->loadMissing('videos');
        $videos = $model->getRelationValue('videos');

        if (! $videos || $videos->isEmpty()) {
            return;
        }

        foreach ($videos as $video) {
            // Google requires name + description for VideoObject rich results.
            if (empty($video->title) || empty($video->description)) {
                continue;
            }

            $schemaKey = JsonldSchemaType::VideoObject->value;

            // Never overwrite manually curated video schemas.
            $hasManualOverride = JsonldSchema::where('model_type', $morphAlias)
                ->where('model_id', $model->getKey())
                ->where('schema_type', $schemaKey)
                ->where('label', 'Video: ' . $video->title)
                ->where('is_auto_generated', false)
                ->exists();

            if ($hasManualOverride) {
                continue;
            }

            $payload = [
                '@context'     => 'https://schema.org',
                '@type'        => 'VideoObject',
                'name'         => $video->title,
                'description'  => $video->description,
                'contentUrl'   => $baseUrl . '/storage/' . ltrim((string) ($video->path ?? ''), '/'),
                'embedUrl'     => $baseUrl . '/products/' . $slug . '#video-' . $video->id,
                'thumbnailUrl' => $video->thumbnail_path
                    ? ($baseUrl . '/storage/' . ltrim((string) $video->thumbnail_path, '/'))
                    : '',
                'uploadDate'   => $video->created_at?->toIso8601String() ?? '',
            ];

            // ISO 8601 duration (e.g. "PT2M30S") — optional but recommended.
            if (filled($video->duration)) {
                $payload['duration'] = $video->duration;
            }

            JsonldSchema::updateOrCreate(
                [
                    'model_type'  => $morphAlias,
                    'model_id'    => $model->getKey(),
                    'schema_type' => $schemaKey,
                    'locale'      => $locale,
                    'label'       => 'Video: ' . $video->title,
                ],
                [
                    'locale'            => $locale,
                    'payload'           => $payload,
                    'is_active'         => true,
                    'is_auto_generated' => true,
                    'sort_order'        => self::SORT_ORDER[$schemaKey] ?? 60,
                ]
            );
        }
    }

    /**
     * Build a flat field→value map covering DB attributes and computed values
     * that templates reference but that don't exist as raw DB columns.
     */
    private function buildValueMap(Model $model): array
    {
        $morphAlias   = $model->getMorphClass();
        $baseUrl      = rtrim((string) (config('seo.app_url') ?: config('app.url')), '/');
        $slug         = (string) ($model->getAttribute('slug') ?? '');
        $pathPrefix   = self::URL_PREFIXES[$morphAlias] ?? '/';
        $canonicalUrl = $baseUrl . $pathPrefix . $slug;

        // Seed with all raw DB attributes (name, slug, sku, price, etc.)
        $map = $model->getAttributes();

        // ── Computed values ───────────────────────────────────────────────────

        $map['canonical_url'] = $canonicalUrl;

        // Product: first product image URL
        // ->value('url') fails because 'url' is a computed Attribute, not a DB column.
        // Must fetch the model instance first, then access the accessor.
        $map['first_image_url'] = method_exists($model, 'images')
            ? ((string) ($model->images()->first()?->url ?? ''))
            : '';

        // BlogPost: author display name via author() → Author model
        // enrichArticleSchema() replaces this with a full Person object at sync time.
        $map['author_name'] = method_exists($model, 'author')
            ? ((string) ($model->author?->name ?? ''))
            : '';

        // BlogPost: full URL for featured image.
        // The raw DB column stores a relative storage path (e.g. "blog/2024/01/x.jpg").
        // Google requires an absolute URL in Article image — never a bare path.
        if ($morphAlias === 'blog_post') {
            $featuredImage          = (string) ($model->getAttribute('featured_image') ?? '');
            $map['featured_image_url'] = filled($featuredImage)
                ? ($baseUrl . '/storage/' . ltrim($featuredImage, '/'))
                : '';
        }

        // Product: Schema.org availability string
        $stockQty            = (int) ($model->getAttribute('stock_quantity') ?? 0);
        $map['availability'] = $stockQty > 0
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock';

        // Product: currency — per-product field, fallback to VND
        $map['price_currency'] = (string) ($model->getAttribute('currency') ?: config('seo.currency', 'VND'));

        // Product: brand and manufacturer names (used as simple placeholders in template)
        if (method_exists($model, 'brand')) {
            $map['brand_name'] = (string) ($model->brand?->name ?? '');
        }
        if (method_exists($model, 'manufacturer')) {
            $map['manufacturer_name'] = (string) ($model->manufacturer?->name ?? '');
        }

        // ── Normalise datetime values → ISO 8601 strings ─────────────────────
        foreach ($map as $key => $val) {
            if ($val instanceof \DateTimeInterface) {
                $map[$key] = $val->format(\DateTimeInterface::ATOM);
            }
        }

        return $map;
    }
}
