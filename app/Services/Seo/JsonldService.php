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
     */
    private const MODEL_SCHEMA_TYPES = [
        'product'   => [JsonldSchemaType::Product,        JsonldSchemaType::BreadcrumbList],
        'blog_post' => [JsonldSchemaType::Article,         JsonldSchemaType::BreadcrumbList],
        'category'  => [JsonldSchemaType::CollectionPage,  JsonldSchemaType::BreadcrumbList],
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
        JsonldSchemaType::BreadcrumbList->value => 90,
    ];

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Sync all applicable auto-generated JSON-LD schemas for a model.
     * Skips rows where is_auto_generated=false (manual admin overrides).
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

        // ── Normalise datetime values → ISO 8601 strings ─────────────────────
        foreach ($map as $key => $val) {
            if ($val instanceof \DateTimeInterface) {
                $map[$key] = $val->format(\DateTimeInterface::ATOM);
            }
        }

        return $map;
    }
}
