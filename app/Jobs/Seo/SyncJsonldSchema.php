<?php

namespace App\Jobs\Seo;

use App\Enums\JsonldSchemaType;
use App\Models\Seo\JsonldSchema;
use App\Models\Seo\JsonldTemplate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

class SyncJsonldSchema implements ShouldQueue
{
    use Queueable;

    /** Max attempts before the job is marked failed. */
    public int $tries = 3;

    /** Seconds to wait between retry attempts. */
    public int $backoff = 5;

    /**
     * Schema types applicable to each model (morph alias → schema_type[]).
     * BreadcrumbList is included for every public model.
     */
    private const MODEL_SCHEMA_TYPES = [
        'product'   => [JsonldSchemaType::Product,        JsonldSchemaType::BreadcrumbList],
        'blog_post' => [JsonldSchemaType::Article,         JsonldSchemaType::BreadcrumbList],
        'category'  => [JsonldSchemaType::CollectionPage,  JsonldSchemaType::BreadcrumbList],
    ];

    /**
     * Front-end URL prefix per model type.
     * Used to build canonical_url placeholder values.
     */
    private const URL_PREFIXES = [
        'product'   => '/products/',
        'blog_post' => '/blog/',
        'category'  => '/categories/',
    ];

    /**
     * Render order — lower = earlier in <head>.
     * Primary schema first, BreadcrumbList always last.
     */
    private const SORT_ORDER = [
        JsonldSchemaType::Product->value        => 10,
        JsonldSchemaType::Article->value        => 10,
        JsonldSchemaType::CollectionPage->value => 10,
        JsonldSchemaType::BreadcrumbList->value => 90,
        JsonldSchemaType::FaqPage->value        => 50,
    ];

    public function __construct(
        public readonly Model $model,
    ) {}

    public function handle(): void
    {
        $morphAlias  = $this->model->getMorphClass();
        $schemaTypes = self::MODEL_SCHEMA_TYPES[$morphAlias] ?? [];

        if (empty($schemaTypes)) {
            return;
        }

        $typeValues = array_map(fn (JsonldSchemaType $t) => $t->value, $schemaTypes);

        // Load only auto-generated templates for this model's applicable types.
        $templates = JsonldTemplate::whereIn('schema_type', $typeValues)
            ->where('is_auto_generated', true)
            ->get();

        foreach ($templates as $template) {
            // Respect manual overrides — never overwrite is_auto_generated=false rows.
            $existingManual = JsonldSchema::where('model_type', $morphAlias)
                ->where('model_id', $this->model->getKey())
                ->where('schema_type', $template->schema_type->value)
                ->where('is_auto_generated', false)
                ->exists();

            if ($existingManual) {
                continue;
            }

            $resolved = $this->resolvePlaceholders(
                $template->template ?? [],
                $this->model
            );

            JsonldSchema::updateOrCreate(
                [
                    'model_type'  => $morphAlias,
                    'model_id'    => $this->model->getKey(),
                    'schema_type' => $template->schema_type->value,
                ],
                [
                    'label'             => $template->label,
                    'payload'           => $resolved,
                    'is_active'         => true,
                    'is_auto_generated' => true,
                    'sort_order'        => self::SORT_ORDER[$template->schema_type->value] ?? 50,
                ]
            );
        }
    }

    // ── Placeholder resolution ────────────────────────────────────────────────

    /**
     * Walk the template array recursively and replace every {{prefix.field}}
     * token with the corresponding value from the model.
     *
     * Pattern: {{morph_alias.field_name}}
     * The prefix (morph alias) is intentionally ignored — only the field name
     * is used so the same resolver works for product, blog_post, category, etc.
     */
    private function resolvePlaceholders(array $template, Model $model): array
    {
        $valueMap = $this->buildValueMap($model);

        array_walk_recursive($template, function (mixed &$value) use ($valueMap): void {
            if (! is_string($value)) {
                return;
            }

            $value = preg_replace_callback(
                '/\{\{[^.}]+\.([^}]+)\}\}/',
                function (array $matches) use ($valueMap): string {
                    $field = $matches[1];

                    return isset($valueMap[$field])
                        ? (string) $valueMap[$field]
                        : '';
                },
                $value
            );
        });

        return $template;
    }

    /**
     * Build a flat key→value map covering both regular model attributes
     * and computed values that templates reference but aren't raw DB columns.
     */
    private function buildValueMap(Model $model): array
    {
        $morphAlias   = $model->getMorphClass();
        $baseUrl      = rtrim((string) config('app.url'), '/');
        $slug         = (string) ($model->getAttribute('slug') ?? '');
        $pathPrefix   = self::URL_PREFIXES[$morphAlias] ?? '/';
        $canonicalUrl = $baseUrl . $pathPrefix . $slug;

        // Start with all raw DB attributes (covers name, slug, sku, price, etc.)
        $map = $model->getAttributes();

        // ── Computed / derived values ─────────────────────────────────────────

        $map['canonical_url'] = $canonicalUrl;

        // Product: first image URL from images() relation
        $map['first_image_url'] = method_exists($model, 'images')
            ? ((string) ($model->images()->value('url') ?? ''))
            : '';

        // BlogPost: author name via eager-loadable author() relation
        $map['author_name'] = method_exists($model, 'author')
            ? ((string) ($model->author?->name ?? ''))
            : '';

        // Product: Schema.org availability string
        $stockQty             = (int) ($model->getAttribute('stock_quantity') ?? 0);
        $map['availability']  = $stockQty > 0
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock';

        // ── Normalise datetime values to ISO 8601 strings ─────────────────────
        foreach ($map as $key => $val) {
            if ($val instanceof \DateTimeInterface) {
                $map[$key] = $val->format(\DateTimeInterface::ATOM);
            }
        }

        return $map;
    }
}
