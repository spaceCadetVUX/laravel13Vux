<?php

namespace App\Services\Seo;

use App\Enums\JsonldSchemaType;
use App\Models\Product;
use App\Models\Seo\JsonldSchema;
use App\Models\Seo\JsonldTemplate;
use App\Support\LocaleUrl;
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
        'brand'         => [JsonldSchemaType::Brand,          JsonldSchemaType::BreadcrumbList],
        'manufacturer'  => [JsonldSchemaType::Manufacturer,   JsonldSchemaType::BreadcrumbList],
    ];

    // URL prefixes are now managed by config/localeurl.php + App\Support\LocaleUrl.

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

            $resolved = $this->resolvePlaceholders($template->template ?? [], $model, $locale);

            // Model-specific enrichments applied after placeholder resolution.
            if ($morphAlias === 'product') {
                if ($schemaType === JsonldSchemaType::Product) {
                    $resolved = $this->enrichProductSchema($resolved, $model, $locale);
                }

                if ($schemaType === JsonldSchemaType::BreadcrumbList) {
                    $resolved = $this->buildProductBreadcrumb($model, $locale);
                }
            }

            if ($morphAlias === 'blog_post') {
                if ($schemaType === JsonldSchemaType::Article) {
                    $resolved = $this->enrichArticleSchema($resolved, $model, $locale);
                }

                if ($schemaType === JsonldSchemaType::BreadcrumbList) {
                    $resolved = $this->buildBlogPostBreadcrumb($model, $locale);
                }
            }

            if ($morphAlias === 'category') {
                if ($schemaType === JsonldSchemaType::CollectionPage) {
                    $resolved = $this->enrichCategorySchema($resolved, $model, $locale);
                }

                if ($schemaType === JsonldSchemaType::BreadcrumbList) {
                    $resolved = $this->buildCategoryBreadcrumb($model, $locale);
                }
            }

            if ($morphAlias === 'blog_category') {
                if ($schemaType === JsonldSchemaType::CollectionPage) {
                    $resolved = $this->enrichBlogCategorySchema($resolved, $model, $locale);
                }

                if ($schemaType === JsonldSchemaType::BreadcrumbList) {
                    $resolved = $this->buildBlogCategoryBreadcrumb($model, $locale);
                }
            }

            if ($morphAlias === 'brand') {
                if ($schemaType === JsonldSchemaType::Brand) {
                    $resolved = $this->enrichBrandSchema($resolved, $model, $locale);
                }

                if ($schemaType === JsonldSchemaType::BreadcrumbList) {
                    $resolved = $this->buildBrandBreadcrumb($model, $locale);
                }
            }

            if ($morphAlias === 'manufacturer') {
                if ($schemaType === JsonldSchemaType::Manufacturer) {
                    $resolved = $this->enrichManufacturerSchema($resolved, $model, $locale);
                }

                if ($schemaType === JsonldSchemaType::BreadcrumbList) {
                    $resolved = $this->buildManufacturerBreadcrumb($model, $locale);
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
        if (in_array($morphAlias, ['product', 'blog_post', 'category', 'blog_category', 'brand', 'manufacturer'], true)) {
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
    public function resolvePlaceholders(array $template, Model $model, string $locale = 'vi'): array
    {
        $valueMap = $this->buildValueMap($model, $locale);

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
     * Return all active JSON-LD schemas for a model and locale, ordered for <head> output.
     * Used by the API to feed the Nuxt <JsonldRenderer> component.
     */
    public function getActiveSchemas(Model $model, ?string $locale = null): Collection
    {
        $locale ??= app()->getLocale();

        return JsonldSchema::where('model_type', $model->getMorphClass())
            ->where('model_id', $model->getKey())
            ->where('is_active', true)
            ->where('locale', $locale)
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

        $pageUrl = $listElements[count($listElements) - 1]['item'] ?? null;

        $schema = [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $listElements,
        ];

        if ($pageUrl) {
            $schema['@id'] = $pageUrl . '#breadcrumb';
        }

        return $schema;
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
            'url'         => LocaleUrl::for('product', $t?->slug ?? $product->slug, $locale),
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
    private function enrichProductSchema(array $payload, Model $model, string $locale = 'vi'): array
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

            $urls = $images
                ? $images->map(fn ($img): string => (string) ($img->url ?? ''))
                         ->filter()
                         ->values()
                         ->all()
                : [];

            if (! empty($urls)) {
                // Single image → string; multiple images → array (schema.org spec)
                $payload['image'] = count($urls) === 1 ? $urls[0] : $urls;
            } else {
                // Remove the "" left by template placeholder resolution — Google errors on empty image.
                unset($payload['image']);
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

        // ── category — all assigned categories as text array ─────────────────
        // Google uses this for product classification in Shopping and rich results.
        // Single category → plain string. Multiple → array of strings.
        if (method_exists($model, 'categories')) {
            $model->loadMissing('categories.translations');
            $cats = $model->getRelationValue('categories');
            if ($cats && $cats->isNotEmpty()) {
                $catNames = $cats
                    ->sortBy('sort_order')
                    ->map(fn ($c): string => (string) (
                        (method_exists($c, 'translation') ? $c->translation($locale)?->name : null)
                        ?? $c->name
                        ?? ''
                    ))
                    ->filter()
                    ->values()
                    ->all();

                if (! empty($catNames)) {
                    $payload['category'] = count($catNames) === 1 ? $catNames[0] : $catNames;
                }
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
                            'name'  => (string) (($locale !== 'vi' && filled($a->name_en)) ? $a->name_en : $a->name),
                            'value' => (string) (($locale !== 'vi' && filled($a->value_en)) ? $a->value_en : $a->value),
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
        $currency     = (string) ($payload['offers']['priceCurrency'] ?? config('seo.currency', 'VND'));
        $productUrl   = (string) ($payload['url'] ?? '');
        $seller       = app(BusinessJsonldService::class)->publisherBlock();
        $priceHidden  = $model->getAttribute('show_price') === false;

        $stockQty     = (int) ($model->getAttribute('stock_quantity') ?? 0);
        $availability = $stockQty > 0
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock';

        // ── Price hidden: return minimal Offer without price fields ───────────
        // Google requires structured data to match what's visible on the page.
        // Omitting price/priceCurrency prevents a "content mismatch" policy violation.
        if ($priceHidden) {
            return [
                '@type'        => 'Offer',
                'availability' => $availability,
                'url'          => $productUrl,
                'seller'       => $seller,
            ];
        }

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

                        $label = $variant->combination_label;
                        if (filled($label)) {
                            $offer['name'] = $label;
                        }

                        return $offer;
                    })->values()->all();

                    // ── Edge case: all variants same price ────────────────────
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
                            'seller'        => $seller,
                        ];
                    }

                    // ── Multiple prices → AggregateOffer ─────────────────────
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
                        'seller'        => $seller,
                    ];
                }
            }
        }

        // ── Fallback: simple product — single Offer ───────────────────────────
        $singleOffer = $payload['offers'] ?? [];
        if (isset($singleOffer['price'])) {
            $singleOffer['price'] = (float) $singleOffer['price'];
        }
        $singleOffer['seller'] = $seller;

        return $singleOffer;
    }

    /**
     * Build a BreadcrumbList payload for a product.
     * Structure: Home → Products → {Primary Category} → {Product}
     *
     * Primary category = lowest sort_order among assigned categories.
     * Falls back to Home → Products → Product if no categories are assigned.
     * Multiple categories: only primary used in breadcrumb; all categories
     * are injected into the Product schema body via the `category` field.
     */
    private function buildProductBreadcrumb(Model $model, string $locale = 'vi'): array
    {
        $baseUrl   = rtrim((string) (config('seo.app_url') ?: config('app.url')), '/');
        $shopUrl   = LocaleUrl::listUrl('product', $locale);
        $shopLabel = LocaleUrl::listLabel('product', $locale);

        $t    = method_exists($model, 'translation') ? $model->translation($locale) : null;
        $name = (string) ($t?->name ?? $model->getAttribute('name') ?? '');
        $slug = (string) ($t?->slug ?? $model->getAttribute('slug') ?? '');

        $items = [
            ['name' => 'Home',      'url' => $baseUrl],
            ['name' => $shopLabel,  'url' => $shopUrl],
        ];

        // Primary category: use primary_category_id if set, else first by sort_order.
        if (method_exists($model, 'categories')) {
            $model->loadMissing('categories.translations');
            $categories = $model->getRelationValue('categories');

            if ($categories && $categories->isNotEmpty()) {
                $primaryId = $model->getAttribute('primary_category_id');
                $primary   = $primaryId
                    ? $categories->firstWhere('id', $primaryId) ?? $categories->sortBy('sort_order')->first()
                    : $categories->sortBy('sort_order')->first();

                $catTr   = method_exists($primary, 'translation') ? $primary->translation($locale) : null;
                $catName = (string) ($catTr?->name ?? $primary->name ?? '');
                $catSlug = (string) ($catTr?->slug ?? $primary->slug ?? '');

                if (filled($catSlug)) {
                    $items[] = [
                        'name' => $catName,
                        'url'  => LocaleUrl::for('category', $catSlug, $locale),
                    ];
                }
            }
        }

        $items[] = ['name' => $name, 'url' => LocaleUrl::for('product', $slug, $locale)];

        return $this->buildBreadcrumbSchema($items);
    }

    /**
     * Build a BreadcrumbList payload for a blog post.
     * Structure: Home → Blog → [{Category} →] {Post}
     *
     * The category level is included only when a blogCategory is assigned.
     * Falls back to Home → Blog → Post.
     */
    private function buildBlogPostBreadcrumb(Model $model, string $locale = 'vi'): array
    {
        $baseUrl = rtrim((string) (config('seo.app_url') ?: config('app.url')), '/');
        $t       = method_exists($model, 'translation') ? $model->translation($locale) : null;
        $title   = (string) ($t?->title ?? $model->getAttribute('title') ?? '');
        $slug    = (string) ($t?->slug ?? $model->getAttribute('slug') ?? '');

        $items = [
            ['name' => 'Home', 'url' => $baseUrl],
            ['name' => 'Blog', 'url' => LocaleUrl::listUrl('blog_post', $locale)],
        ];

        if (method_exists($model, 'blogCategory')) {
            $model->loadMissing('blogCategory');
            $category = $model->getRelationValue('blogCategory');

            if ($category && filled($category->name)) {
                $catSlug = (string) ($category->translation($locale)?->slug ?? $category->slug ?? '');
                $catName = (string) ($category->translation($locale)?->name ?? $category->name);
                $items[] = [
                    'name' => $catName,
                    'url'  => LocaleUrl::for('blog_category', $catSlug, $locale),
                ];
            }
        }

        $postUrl = ($model instanceof \App\Models\BlogPost)
            ? LocaleUrl::forBlogPost($model, $locale)
            : LocaleUrl::for('blog_post', $slug, $locale);

        $items[] = ['name' => $title, 'url' => $postUrl];

        return $this->buildBreadcrumbSchema($items);
    }

    /**
     * Build a BreadcrumbList payload for a catalog category page.
     * Walks the full ancestor chain — supports unlimited nesting depth.
     *
     * Structure: Home → [Root →] [...] → [Parent →] {Category}
     *
     * Each ancestor is resolved with its locale-aware translation (name + slug).
     * A seen-ID guard prevents infinite loops from circular parent_id references.
     * Each loadMissing() call adds one DB query — acceptable for a background job
     * since category trees are typically 2–4 levels deep.
     */
    private function buildCategoryBreadcrumb(Model $model, string $locale = 'vi'): array
    {
        $baseUrl = rtrim((string) (config('seo.app_url') ?: config('app.url')), '/');

        // ── Walk up the ancestor chain ────────────────────────────────────────
        // Collect ancestors from nearest parent → root, then reverse to root → parent.
        $ancestors = [];
        $seenIds   = [$model->getKey()]; // guard against circular parent_id references
        $cursor    = $model;

        while (method_exists($cursor, 'parent')) {
            $cursor->loadMissing('parent');
            $parent = $cursor->getRelationValue('parent');

            if (! $parent || in_array($parent->getKey(), $seenIds, strict: true)) {
                break;
            }

            $seenIds[]   = $parent->getKey();
            $ancestors[] = $parent;
            $cursor      = $parent;
        }

        $ancestors = array_reverse($ancestors); // now root → nearest parent

        // ── Build item list ───────────────────────────────────────────────────
        $homeLabel      = $locale === 'vi' ? 'Trang chủ' : 'Home';
        $solutionsLabel = $locale === 'vi' ? 'Giải pháp'  : 'Solutions';
        $solutionsUrl   = LocaleUrl::listUrl('category', $locale);

        $items = [
            ['name' => $homeLabel,      'url' => $baseUrl],
            ['name' => $solutionsLabel, 'url' => $solutionsUrl],
        ];

        foreach ($ancestors as $ancestor) {
            $t    = method_exists($ancestor, 'translation') ? $ancestor->translation($locale) : null;
            $name = (string) ($t?->name ?? $ancestor->getAttribute('name') ?? '');
            $slug = (string) ($t?->slug ?? $ancestor->getAttribute('slug') ?? '');

            if (filled($name)) {
                $items[] = [
                    'name' => $name,
                    'url'  => LocaleUrl::for('category', $slug, $locale),
                ];
            }
        }

        // ── Current category ──────────────────────────────────────────────────
        $t    = method_exists($model, 'translation') ? $model->translation($locale) : null;
        $name = (string) ($t?->name ?? $model->getAttribute('name') ?? '');
        $slug = (string) ($t?->slug ?? $model->getAttribute('slug') ?? '');

        $items[] = ['name' => $name, 'url' => LocaleUrl::for('category', $slug, $locale)];

        return $this->buildBreadcrumbSchema($items);
    }

    /**
     * Enrich a resolved CollectionPage payload with dynamic category data.
     *
     * Added fields (not expressible as simple template placeholders):
     *   @id          → canonical URL (entity disambiguation)
     *   inLanguage   → locale signal for Google multilingual indexing
     *   image        → full URL built from categories.image_path
     *   publisher    → Organization block from BusinessProfile (E-E-A-T)
     *   numberOfItems → total active product count for this category
     *   mainEntity   → ItemList of top 20 active products (name, url, image, offers)
     *
     * Products are loaded with thumbnail + translations in two queries (no N+1).
     * All errors are caught silently so a broken products relation never crashes the job.
     */
    private function enrichCategorySchema(array $payload, Model $model, string $locale): array
    {
        $baseUrl = rtrim((string) (config('seo.app_url') ?: config('app.url')), '/');

        // @id — canonical entity identifier required by Google for entity disambiguation.
        if (isset($payload['url']) && ! isset($payload['@id'])) {
            $payload['@id'] = $payload['url'];
        }

        // inLanguage — tells Google which language edition this schema describes.
        $payload['inLanguage'] = $locale;

        // image — category thumbnail stored as a relative path in image_path column.
        $imagePath = (string) ($model->getAttribute('image_path') ?? '');
        if (filled($imagePath)) {
            $payload['image'] = $baseUrl . '/storage/' . ltrim($imagePath, '/');
        }

        // publisher — Organization block (same pattern as Article schemas).
        if (! isset($payload['publisher'])) {
            $payload['publisher'] = app(BusinessJsonldService::class)->publisherBlock();
        }

        // dateModified — freshness signal for Google.
        $updatedAt = $model->getAttribute('updated_at');
        if ($updatedAt instanceof \DateTimeInterface) {
            $payload['dateModified'] = $updatedAt->format(\DateTimeInterface::ATOM);
        }

        // breadcrumb — link CollectionPage to its BreadcrumbList for cross-schema association.
        if (isset($payload['@id'])) {
            $payload['breadcrumb'] = ['@type' => 'BreadcrumbList', '@id' => $payload['@id'] . '#breadcrumb'];
        }

        // additionalProperty — key_facts from geoProfile as PropertyValue array.
        $model->loadMissing('geoProfiles');
        $geoProfile = $model->geoProfiles->firstWhere('locale', $locale);
        $keyFacts   = (array) ($geoProfile?->key_facts ?? []);
        if (! empty($keyFacts)) {
            $props = [];
            foreach ($keyFacts as $kf) {
                if (! is_array($kf)) continue;
                $label = trim((string) ($kf['label'] ?? ''));
                $value = trim((string) ($kf['value'] ?? ''));
                if (filled($label) && filled($value)) {
                    $props[] = ['@type' => 'PropertyValue', 'name' => $label, 'value' => $value];
                }
            }
            if (! empty($props)) {
                $payload['additionalProperty'] = $props;
            }
        }

        // numberOfItems + mainEntity ItemList — products belonging to this category.
        if (method_exists($model, 'products')) {
            try {
                $productCount             = $model->products()->where('products.is_active', true)->count();
                $payload['numberOfItems'] = $productCount;

                if ($productCount > 0) {
                    $fallbackLocale = config('app.fallback_locale', 'vi');
                    $locales        = array_unique([$locale, $fallbackLocale]);

                    // Eager-load thumbnail (HasOne) and translations for the target locale
                    // in 2 queries total — no N+1 across the 20 product loop.
                    $topProducts = $model->products()
                        ->where('products.is_active', true)
                        ->with([
                            'thumbnail',
                            'translations' => fn ($q) => $q->whereIn('locale', $locales),
                        ])
                        ->orderBy('products.sort_order')
                        ->limit(20)
                        ->get();

                    if ($topProducts->isNotEmpty()) {
                        $listItems = $topProducts->map(function ($product, int $index) use ($locale): array {
                            $t           = method_exists($product, 'translation') ? $product->translation($locale) : null;
                            $productName = (string) ($t?->name ?? $product->getAttribute('name') ?? '');
                            $productSlug = (string) ($t?->slug ?? $product->getAttribute('slug') ?? '');

                            $item = [
                                '@type'    => 'ListItem',
                                'position' => $index + 1,
                                'name'     => $productName,
                                'url'      => LocaleUrl::for('product', $productSlug, $locale),
                            ];

                            // Thumbnail — relation is already eager-loaded, no extra query.
                            $thumb = $product->getRelationValue('thumbnail');
                            if ($thumb && filled($thumb->url)) {
                                $item['image'] = (string) $thumb->url;
                            }

                            // Price and availability — use locale-specific translation when available.
                            $price    = $t?->price ?? $product->getAttribute('price');
                            $currency = $t?->currency ?? $product->getAttribute('currency') ?? config('seo.currency', 'VND');
                            if (filled($price)) {
                                $item['offers'] = [
                                    '@type'         => 'Offer',
                                    'price'         => (float) $price,
                                    'priceCurrency' => $currency,
                                    'availability'  => ((int) $product->getAttribute('stock_quantity')) > 0
                                        ? 'https://schema.org/InStock'
                                        : 'https://schema.org/OutOfStock',
                                ];
                            }

                            return $item;
                        })->values()->all();

                        $payload['mainEntity'] = [
                            '@type'           => 'ItemList',
                            'name'            => $payload['name'] ?? '',
                            'numberOfItems'   => $productCount,
                            'itemListElement' => $listItems,
                        ];
                    }
                }
            } catch (\Throwable) {
                // Silently skip — products relationship may be unavailable in test/seeder context.
            }
        }

        return $payload;
    }

    /**
     * Enrich a CollectionPage payload for a blog category.
     * Adds @id, inLanguage, image, publisher, numberOfItems, and a
     * mainEntity ItemList of the top published posts in this category.
     */
    private function enrichBlogCategorySchema(array $payload, Model $model, string $locale): array
    {
        $baseUrl = rtrim((string) (config('seo.app_url') ?: config('app.url')), '/');

        if (isset($payload['url']) && ! isset($payload['@id'])) {
            $payload['@id'] = $payload['url'];
        }

        $payload['inLanguage'] = $locale;

        $imagePath = (string) ($model->getAttribute('image_path') ?? '');
        if (filled($imagePath)) {
            $payload['image'] = $baseUrl . '/storage/' . ltrim($imagePath, '/');
        }

        if (! isset($payload['publisher'])) {
            $payload['publisher'] = app(BusinessJsonldService::class)->publisherBlock();
        }

        if (method_exists($model, 'posts')) {
            try {
                $postCount             = $model->posts()->where('status', \App\Enums\BlogPostStatus::Published)->count();
                $payload['numberOfItems'] = $postCount;

                if ($postCount > 0) {
                    $fallbackLocale = config('app.fallback_locale', 'vi');
                    $locales        = array_unique([$locale, $fallbackLocale]);

                    $topPosts = $model->posts()
                        ->where('status', \App\Enums\BlogPostStatus::Published)
                        ->with(['translations' => fn ($q) => $q->whereIn('locale', $locales)])
                        ->orderBy('published_at', 'desc')
                        ->limit(20)
                        ->get();

                    if ($topPosts->isNotEmpty()) {
                        $listItems = $topPosts->map(function ($post, int $index) use ($baseUrl, $locale): array {
                            $t        = method_exists($post, 'translation') ? $post->translation($locale) : null;
                            $postName = (string) ($t?->title ?? $post->getAttribute('title') ?? '');
                            $postSlug = (string) ($t?->slug ?? $post->getAttribute('slug') ?? '');

                            $postUrl = ($post instanceof \App\Models\BlogPost && filled($postSlug))
                                ? LocaleUrl::forBlogPost($post, $locale)
                                : (filled($postSlug)
                                    ? LocaleUrl::for('blog_post', $postSlug, $locale)
                                    : rtrim((string) (config('seo.app_url') ?: config('app.url')), '/'));

                            return [
                                '@type'    => 'ListItem',
                                'position' => $index + 1,
                                'name'     => $postName,
                                'url'      => $postUrl,
                            ];
                        })->values()->all();

                        $payload['mainEntity'] = [
                            '@type'           => 'ItemList',
                            'name'            => $payload['name'] ?? '',
                            'numberOfItems'   => $postCount,
                            'itemListElement' => $listItems,
                        ];
                    }
                }
            } catch (\Throwable) {
                // Silently skip — posts relationship may be unavailable in test/seeder context.
            }
        }

        return $payload;
    }

    /**
     * Build a BreadcrumbList payload for a blog category page.
     * Structure: Home → Blog → [{Parent category} →] {Category}
     *
     * The parent level is included only when a parent_id is assigned.
     * Falls back to Home → Blog → Category.
     */
    private function buildBlogCategoryBreadcrumb(Model $model, string $locale = 'vi'): array
    {
        $baseUrl = rtrim((string) (config('seo.app_url') ?: config('app.url')), '/');
        $t       = method_exists($model, 'translation') ? $model->translation($locale) : null;
        $name    = (string) ($t?->name ?? $model->getAttribute('name') ?? '');
        $slug    = (string) ($t?->slug ?? $model->getAttribute('slug') ?? '');

        $items = [
            ['name' => 'Home', 'url' => $baseUrl],
            ['name' => 'Blog', 'url' => LocaleUrl::listUrl('blog_post', $locale)],
        ];

        if (method_exists($model, 'parent')) {
            $model->loadMissing('parent');
            $parent = $model->getRelationValue('parent');

            if ($parent && filled($parent->name)) {
                $parentSlug = (string) ($parent->translation($locale)?->slug ?? $parent->slug ?? '');
                $parentName = (string) ($parent->translation($locale)?->name ?? $parent->name);
                $items[] = [
                    'name' => $parentName,
                    'url'  => LocaleUrl::for('blog_category', $parentSlug, $locale),
                ];
            }
        }

        $items[] = ['name' => $name, 'url' => LocaleUrl::for('blog_category', $slug, $locale)];

        return $this->buildBreadcrumbSchema($items);
    }

    /**
     * Generate a FAQPage schema for any model that has geoProfile.faq data.
     * Supports products, blog posts, and categories. Skips if: no FAQ data,
     * manual override exists, or geoProfile is missing.
     */
    private function syncFaqPage(Model $model, string $locale = 'vi'): void
    {
        $morphAlias = $model->getMorphClass();
        $model->loadMissing('geoProfiles');
        $geoProfile = $model->geoProfile($locale);
        $faq        = (array) ($geoProfile?->faq ?? []);

        // Products store FAQ on the root model (faq_items_vi/en), not in geoProfiles.faq.
        // Fall back when geoProfile has no faq data.
        if (empty($faq) && $morphAlias === 'product') {
            $faqField = 'faq_items_' . $locale;
            $faq      = (array) ($model->getAttribute($faqField) ?? []);
        }

        if (empty($faq)) {
            // Source data is now empty — remove stale auto-generated FAQPage if present.
            JsonldSchema::where('model_type', $morphAlias)
                ->where('model_id', $model->getKey())
                ->where('schema_type', JsonldSchemaType::FaqPage->value)
                ->where('locale', $locale)
                ->where('is_auto_generated', true)
                ->delete();
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
                    '@context'        => 'https://schema.org',
                    '@type'           => 'FAQPage',
                    '@id'             => LocaleUrl::for($morphAlias, (string) ($model->getAttribute('slug') ?? ''), $locale) . '#faq',
                    'mainEntityOfPage'=> ['@type' => 'WebPage', '@id' => LocaleUrl::for($morphAlias, (string) ($model->getAttribute('slug') ?? ''), $locale)],
                    'mainEntity'      => $mainEntity,
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
    private function enrichArticleSchema(array $payload, Model $model, string $locale = 'vi'): array
    {
        $baseUrl = rtrim((string) (config('seo.app_url') ?: config('app.url')), '/');

        // ── @id — canonical entity identifier ────────────────────────────────
        if (isset($payload['url']) && ! isset($payload['@id'])) {
            $payload['@id'] = $payload['url'];
        }

        // ── inLanguage — required for multilingual indexing ───────────────────
        if (! isset($payload['inLanguage'])) {
            $payload['inLanguage'] = $locale;
        }

        // ── mainEntityOfPage — ties the Article to its canonical WebPage ─────
        // Google uses this to associate the structured data block with the page URL.
        if (isset($payload['url']) && ! isset($payload['mainEntityOfPage'])) {
            $payload['mainEntityOfPage'] = [
                '@type' => 'WebPage',
                '@id'   => $payload['url'],
            ];
        }

        // ── articleSection — blog category name ───────────────────────────────
        if (! isset($payload['articleSection']) && method_exists($model, 'blogCategory')) {
            $model->loadMissing('blogCategory');
            $category     = $model->blogCategory;
            $categoryName = $category?->translation($locale)?->name ?? $category?->name;
            if (filled($categoryName)) {
                $payload['articleSection'] = $categoryName;
            }
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

                if (filled($author->slug)) {
                    $person['@id'] = $baseUrl . '/authors/' . $author->slug . '#person';
                }

                if (filled($author->title)) {
                    $person['jobTitle'] = $author->title;
                }

                $sameAs = $author->same_as;

                if (filled($author->slug)) {
                    $person['url'] = $baseUrl . '/authors/' . $author->slug;
                } elseif (! empty($sameAs)) {
                    // Fallback: use first social/web profile URL so Google can anchor the author identity
                    $person['url'] = $sameAs[0];
                }

                if ($avatarUrl = $author->avatar_url) {
                    $person['image'] = $avatarUrl;
                }

                if (filled($author->bio)) {
                    $person['description'] = $author->bio;
                }

                if (! empty($sameAs)) {
                    $person['sameAs'] = count($sameAs) === 1 ? $sameAs[0] : $sameAs;
                }

                $expertise = array_values(array_filter((array) ($author->expertise ?? [])));
                if (! empty($expertise)) {
                    $person['knowsAbout'] = $expertise;
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

        // ── image → ImageObject ───────────────────────────────────────────────
        // Google requires ImageObject with url+width+height for Article rich results.
        // Plain URL string disqualifies the schema from rich snippets.
        if (isset($payload['image']) && is_string($payload['image']) && filled($payload['image'])) {
            $imageObj = ['@type' => 'ImageObject', 'url' => $payload['image']];

            $relativePath = $model->getAttribute('featured_image');
            if (filled($relativePath)) {
                $fullPath = storage_path('app/public/' . ltrim($relativePath, '/'));
                if (file_exists($fullPath)) {
                    [$w, $h] = @getimagesize($fullPath) ?: [null, null];
                    if ($w && $h) {
                        $imageObj['width']  = $w;
                        $imageObj['height'] = $h;
                    }
                }
            }

            $payload['image'] = $imageObj;
        }

        // ── wordCount — computed from locale body, stripped of HTML ──────────
        if (! isset($payload['wordCount']) && method_exists($model, 'translation')) {
            $body = $model->translation($locale)?->body ?? '';
            if (filled($body)) {
                $text = trim(strip_tags($body));
                $payload['wordCount'] = count(
                    preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY)
                );
            }
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
                'embedUrl'     => LocaleUrl::for('product', $slug, $locale) . '#video-' . $video->id,
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
     * Enrich a resolved Brand schema payload with logo, sameAs, @id, inLanguage.
     */
    private function enrichBrandSchema(array $payload, Model $model, string $locale): array
    {
        $baseUrl = rtrim((string) (config('seo.app_url') ?: config('app.url')), '/');

        if (isset($payload['url']) && ! isset($payload['@id'])) {
            $payload['@id'] = $payload['url'];
        }

        $payload['inLanguage'] = $locale;

        $logo = (string) ($model->getAttribute('logo') ?? '');
        if (filled($logo)) {
            $payload['logo'] = $baseUrl . '/storage/' . ltrim($logo, '/');
        }

        $website = (string) ($model->getAttribute('website') ?? '');
        if (filled($website)) {
            $payload['sameAs'] = $website;
        }

        // key_facts → additionalProperty (PropertyValue) for Knowledge Graph enrichment.
        $model->loadMissing('geoProfiles');
        $geoProfile = $model->geoProfiles->firstWhere('locale', $locale);
        $keyFacts   = (array) ($geoProfile?->key_facts ?? []);

        if (! empty($keyFacts)) {
            $props = [];
            foreach ($keyFacts as $kf) {
                if (! is_array($kf)) continue;
                $label = trim((string) ($kf['label'] ?? ''));
                $value = trim((string) ($kf['value'] ?? ''));
                if (filled($label) && filled($value)) {
                    $props[] = ['@type' => 'PropertyValue', 'name' => $label, 'value' => $value];
                }
            }
            if (! empty($props)) {
                $payload['additionalProperty'] = $props;
            }
        }

        return $payload;
    }

    /**
     * Enrich a resolved Manufacturer schema payload with logo, sameAs, country, @id, inLanguage.
     */
    private function enrichManufacturerSchema(array $payload, Model $model, string $locale): array
    {
        $baseUrl = rtrim((string) (config('seo.app_url') ?: config('app.url')), '/');
        $slug    = (string) ($model->getAttribute('slug') ?? '');

        // @id = canonical page URL on our site (entity disambiguation for Google).
        // url in the template is the external manufacturer website — keep them separate.
        $payload['@id'] = LocaleUrl::for('manufacturer', $slug, $locale);

        $payload['inLanguage'] = $locale;

        $logo = (string) ($model->getAttribute('logo') ?? '');
        if (filled($logo)) {
            $payload['logo'] = $baseUrl . '/storage/' . ltrim($logo, '/');
        }

        $website = (string) ($model->getAttribute('website') ?? '');
        if (filled($website)) {
            $payload['sameAs'] = $website;
        }

        $country = (string) ($model->getAttribute('country') ?? '');
        if (filled($country)) {
            $payload['address'] = ['@type' => 'PostalAddress', 'addressCountry' => $country];
        }

        // key_facts → additionalProperty (PropertyValue) for Knowledge Graph enrichment.
        $model->loadMissing('geoProfiles');
        $geoProfile = $model->geoProfiles->firstWhere('locale', $locale);
        $keyFacts   = (array) ($geoProfile?->key_facts ?? []);

        if (! empty($keyFacts)) {
            $props = [];
            foreach ($keyFacts as $kf) {
                if (! is_array($kf)) continue;
                $label = trim((string) ($kf['label'] ?? ''));
                $value = trim((string) ($kf['value'] ?? ''));
                if (filled($label) && filled($value)) {
                    $props[] = ['@type' => 'PropertyValue', 'name' => $label, 'value' => $value];
                }
            }
            if (! empty($props)) {
                $payload['additionalProperty'] = $props;
            }
        }

        return $payload;
    }

    /**
     * Build a BreadcrumbList payload for a manufacturer page.
     * Structure: Home → Manufacturers → {Manufacturer name}
     */
    private function buildManufacturerBreadcrumb(Model $model, string $locale = 'vi'): array
    {
        $name = (string) ($model->getAttribute('name') ?? '');
        $slug = (string) ($model->getAttribute('slug') ?? '');

        return $this->buildBreadcrumbSchema([
            ['name' => 'Home',                                            'url' => rtrim((string) (config('seo.app_url') ?: config('app.url')), '/')],
            ['name' => LocaleUrl::listLabel('manufacturer', $locale),    'url' => LocaleUrl::listUrl('manufacturer', $locale)],
            ['name' => $name,                                             'url' => LocaleUrl::for('manufacturer', $slug, $locale)],
        ]);
    }

    /**
     * Build a BreadcrumbList payload for a brand page.
     * Structure: Home → Brands → {Brand name}
     */
    private function buildBrandBreadcrumb(Model $model, string $locale = 'vi'): array
    {
        $baseUrl = rtrim((string) (config('seo.app_url') ?: config('app.url')), '/');
        $name    = (string) ($model->getAttribute('name') ?? '');
        $slug    = (string) ($model->getAttribute('slug') ?? '');

        return $this->buildBreadcrumbSchema([
            ['name' => 'Home',                                       'url' => $baseUrl],
            ['name' => LocaleUrl::listLabel('brand', $locale),       'url' => LocaleUrl::listUrl('brand', $locale)],
            ['name' => $name,                                        'url' => LocaleUrl::for('brand', $slug, $locale)],
        ]);
    }

    /**
     * Build a flat field→value map covering DB attributes and computed values
     * that templates reference but that don't exist as raw DB columns.
     */
    /**
     * Build the canonical URL for a model using LocaleUrl — the single source of truth
     * for all public URL paths (config/localeurl.php).
     */
    private function canonicalRouteFor(string $morphAlias, string $slug, string $locale): string
    {
        return LocaleUrl::for($morphAlias, $slug, $locale);
    }

    private function buildValueMap(Model $model, string $locale = 'vi'): array
    {
        $morphAlias   = $model->getMorphClass();
        $baseUrl      = rtrim((string) (config('seo.app_url') ?: config('app.url')), '/');
        $slug         = (string) ($model->getAttribute('slug') ?? '');
        $canonicalUrl = $this->canonicalRouteFor($morphAlias, $slug, $locale);

        // Seed with all raw DB attributes (name, slug, sku, price, etc.)
        $map = $model->getAttributes();

        // Re-cast datetime fields so the ISO 8601 normalizer loop below can
        // convert them correctly — getAttributes() returns raw DB strings.
        // Also covers created_at/updated_at which Laravel handles outside getCasts().
        $dateFields = array_keys(array_filter(
            $model->getCasts(),
            fn (string $c) => str_contains($c, 'datetime') || str_contains($c, 'date') || $c === 'timestamp'
        ));
        if ($model->usesTimestamps()) {
            $dateFields[] = $model->getCreatedAtColumn();
            $dateFields[] = $model->getUpdatedAtColumn();
        }
        foreach ($dateFields as $field) {
            if (array_key_exists($field, $map) && filled($map[$field])) {
                $map[$field] = $model->getAttribute($field);
            }
        }

        // ── Locale-specific price / currency overrides (products only) ────────
        // translation(locale) returns the row for the requested locale, falling
        // back to vi. This ensures EN JSON-LD uses USD pricing, VI uses VND.
        if ($morphAlias === 'product' && method_exists($model, 'translation')) {
            $t = $model->translation($locale);
            if ($t) {
                if (filled($t->price))             { $map['price']             = (float) $t->price; }
                if (filled($t->sale_price))        { $map['sale_price']        = (float) $t->sale_price; }
                if (filled($t->currency))          { $map['currency']          = $t->currency; }
                if (filled($t->name))              { $map['name']              = $t->name; }
                if (filled($t->short_description)) { $map['short_description'] = strip_tags((string) $t->short_description); }
                if (filled($t->description))       { $map['description']       = strip_tags((string) $t->description); }
                if (filled($t->slug))              {
                    $map['slug']  = $t->slug;
                    $canonicalUrl = $this->canonicalRouteFor($morphAlias, $t->slug, $locale);
                }
            }
        }

        // ── Locale-specific field overrides for categories and blog categories ──
        if (in_array($morphAlias, ['category', 'blog_category'], true) && method_exists($model, 'translation')) {
            $t = $model->translation($locale);
            if ($t) {
                if (filled($t->name))        { $map['name']        = $t->name; }
                if (filled($t->description)) { $map['description'] = $t->description; }
                if (filled($t->slug))        {
                    $map['slug']  = $t->slug;
                    $canonicalUrl = $this->canonicalRouteFor($morphAlias, $t->slug, $locale);
                }
            }
        }

        // ── Locale-specific field overrides for blog posts ────────────────────
        if ($morphAlias === 'blog_post' && method_exists($model, 'translation')) {
            $t = $model->translation($locale);
            if ($t) {
                if (filled($t->title)) { $map['title'] = $t->title; }
                if (filled($t->slug))  {
                    $map['slug']  = $t->slug;
                    $canonicalUrl = $this->canonicalRouteFor($morphAlias, $t->slug, $locale);
                }
                if (filled($t->excerpt)) { $map['excerpt'] = $t->excerpt; }
            }
        }

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

        // Product: currency — locale-specific (overridden above for products), fallback to VND
        $map['price_currency'] = (string) ($map['currency'] ?? $model->getAttribute('currency') ?: config('seo.currency', 'VND'));

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
