<?php

namespace App\Services\Seo;

use App\Enums\JsonldSchemaType;
use App\Models\Seo\JsonldSchema;
use App\Models\Seo\JsonldTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class JsonldService
{
    /**
     * Schema types applicable to each morph alias.
     * BreadcrumbList is included for every public model.
     * FAQPage for products is generated conditionally — only when geoProfile.faq has data.
     */
    private const MODEL_SCHEMA_TYPES = [
        'product'   => [JsonldSchemaType::Product, JsonldSchemaType::BreadcrumbList],
        'blog_post' => [JsonldSchemaType::Article,  JsonldSchemaType::BreadcrumbList],
        'category'  => [JsonldSchemaType::CollectionPage, JsonldSchemaType::BreadcrumbList],
    ];

    /**
     * Front-end URL prefix per morph alias.
     */
    private const URL_PREFIXES = [
        'product'   => '/products/',
        'blog_post' => '/blog/',
        'category'  => '/categories/',
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
    public function syncForModel(Model $model): void
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

            // Product-specific enrichments applied after placeholder resolution.
            if ($morphAlias === 'product') {
                if ($schemaType === JsonldSchemaType::Product) {
                    $resolved = $this->enrichProductSchema($resolved, $model);
                }

                if ($schemaType === JsonldSchemaType::BreadcrumbList) {
                    $resolved = $this->buildProductBreadcrumb($model);
                }
            }

            JsonldSchema::updateOrCreate(
                [
                    'model_type'  => $morphAlias,
                    'model_id'    => $model->getKey(),
                    'schema_type' => $schemaType->value,
                ],
                [
                    'label'             => $template->label,
                    'payload'           => $resolved,
                    'is_active'         => true,
                    'is_auto_generated' => true,
                    'sort_order'        => self::SORT_ORDER[$schemaType->value] ?? 50,
                ]
            );
        }

        // ── Product-only conditional schemas ──────────────────────────────────
        if ($morphAlias === 'product') {
            $this->syncFaqPageForProduct($model);
            $this->syncVideoObjectsForProduct($model);
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

        // ── Cast price fields to float (Schema.org requires number, not string) ─
        // Template placeholder substitution always produces strings — fix here.
        if (isset($payload['offers']['price'])) {
            $payload['offers']['price'] = (float) $payload['offers']['price'];
        }

        return $payload;
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
     * Generate a FAQPage schema for a product IF geoProfile.faq has content.
     * Skips if: no FAQ data, manual override exists, or geoProfile is missing.
     */
    private function syncFaqPageForProduct(Model $model): void
    {
        $morphAlias = $model->getMorphClass();
        $geoProfile = method_exists($model, 'geoProfile') ? $model->geoProfile : null;
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
            ],
            [
                'label'             => 'FAQ Schema',
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
     * Generate a VideoObject schema for each active product video IF it has
     * title + description (minimum required by Google for VideoObject rich results).
     * Skips videos that are missing required SEO fields.
     */
    private function syncVideoObjectsForProduct(Model $model): void
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
                    'label'       => 'Video: ' . $video->title,
                ],
                [
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

        // BlogPost: author display name via author() relation
        $map['author_name'] = method_exists($model, 'author')
            ? ((string) ($model->author?->name ?? ''))
            : '';

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
