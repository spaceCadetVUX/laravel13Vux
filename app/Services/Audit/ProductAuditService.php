<?php

namespace App\Services\Audit;

use App\Models\Product;

class ProductAuditService
{
    // ── Public entry points ───────────────────────────────────────────────────

    /**
     * Build the Markdown report string — no disk write.
     * Used by: Filament action (stream download to browser).
     */
    public function buildReport(Product $product): string
    {
        $product->loadMissing([
            'categories',
            'brand',
            'manufacturer',
            'images',
            'attributes',
            'variants',
            'seoMetas',
            'geoProfiles',
            'llmsEntries',
            'jsonldSchemas',
        ]);

        return $this->buildMarkdown($product, $this->runChecks($product));
    }

    /**
     * Build report AND save to audit/products/{slug}-{Ymd-Hi}.md
     * Used by: `php artisan audit:product`.
     * Returns the absolute file path.
     */
    public function generate(Product $product): string
    {
        $markdown = $this->buildReport($product);

        $dir = base_path('audit/products');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = $dir . DIRECTORY_SEPARATOR
            . $product->slug . '-' . now()->format('Ymd-Hi') . '.md';

        file_put_contents($filename, $markdown);

        return $filename;
    }

    // ── Check runners ─────────────────────────────────────────────────────────

    private function runChecks(Product $product): array
    {
        $seo     = $product->seoMeta();
        $geo     = $product->geoProfile();
        $llms    = $product->llmsEntries->first();
        $schemas = $product->jsonldSchemas;

        return [
            'basics'     => $this->checkBasics($product),
            'pricing'    => $this->checkPricing($product),
            'content'    => $this->checkContent($product),
            'images'     => $this->checkImages($product),
            'attributes' => $this->checkAttributes($product),
            'seo'        => $this->checkSeo($seo),
            'geo'        => $this->checkGeo($geo),
            'llms'       => $this->checkLlms($llms),
            'jsonld'     => $this->checkJsonld($schemas, (array) ($geo?->faq ?? [])),
        ];
    }

    private function checkBasics(Product $product): array
    {
        return [
            'label' => '📦 Product Basics',
            'items' => [
                $this->pass('Name filled',                   filled($product->name)),
                $this->pass('Slug filled',                   filled($product->slug)),
                $this->pass('SKU filled',                    filled($product->sku)),
                $this->pass('Active (is_active = true)',     $product->is_active),
                $this->pass('At least 1 category assigned',  $product->categories->isNotEmpty(),
                    detail: $product->categories->count() . ' category(s)'),
                $this->pass('Brand assigned',                $product->brand !== null,
                    detail: $product->brand?->name ?? '—'),
                $this->pass('Manufacturer assigned',         $product->manufacturer !== null,
                    detail: $product->manufacturer?->name ?? '—'),
            ],
        ];
    }

    private function checkPricing(Product $product): array
    {
        return [
            'label' => '💰 Pricing & Stock',
            'items' => [
                $this->pass('Price > 0',           ($product->price ?? 0) > 0,
                    detail: number_format((float) ($product->price ?? 0)) . ' ' . ($product->currency ?? 'VND')),
                $this->pass('Currency set',        filled($product->currency),
                    detail: $product->currency ?? '—'),
                $this->pass('Stock quantity ≥ 0',  ($product->stock_quantity ?? -1) >= 0,
                    detail: (string) ($product->stock_quantity ?? '—')),
                $this->pass('Variants defined',    $product->variants->isNotEmpty(),
                    detail: $product->variants->count() . ' variant(s)',
                    required: false),
            ],
        ];
    }

    private function checkContent(Product $product): array
    {
        return [
            'label' => '📝 Content',
            'items' => [
                $this->pass('Short description filled',  filled($product->short_description)),
                $this->pass('Full description filled',   filled($product->description)),
            ],
        ];
    }

    private function checkImages(Product $product): array
    {
        $images        = $product->images;
        $withAlt       = $images->filter(fn ($i) => filled($i->alt_text));
        $allHaveAlt    = $images->isNotEmpty() && $images->count() === $withAlt->count();

        return [
            'label' => '🖼️ Images',
            'items' => [
                $this->pass('At least 1 image uploaded',
                    $images->isNotEmpty(),
                    detail: $images->count() . ' image(s)'),
                $this->pass('All images have alt text',
                    $allHaveAlt,
                    detail: $withAlt->count() . '/' . $images->count() . ' have alt text'),
            ],
        ];
    }

    private function checkAttributes(Product $product): array
    {
        $attrs = $product->attributes;

        return [
            'label' => '🏷️ Attributes (JSON-LD additionalProperty)',
            'items' => [
                $this->pass('At least 1 attribute defined',
                    $attrs->isNotEmpty(),
                    detail: $attrs->count() . ' attribute(s)'),
            ],
        ];
    }

    private function checkSeo(?\App\Models\Seo\SeoMeta $seo): array
    {
        $titleLen = mb_strlen($seo?->meta_title ?? '');
        $descLen  = mb_strlen($seo?->meta_description ?? '');

        return [
            'label' => '🔍 SEO Meta',
            'items' => [
                $this->warn(
                    'meta_title filled (50–70 chars)',
                    filled($seo?->meta_title) && $titleLen >= 50 && $titleLen <= 70,
                    warn: filled($seo?->meta_title) && ($titleLen < 50 || $titleLen > 70),
                    detail: filled($seo?->meta_title) ? $titleLen . ' chars' : 'empty',
                ),
                $this->warn(
                    'meta_description filled (120–160 chars)',
                    filled($seo?->meta_description) && $descLen >= 120 && $descLen <= 160,
                    warn: filled($seo?->meta_description) && ($descLen < 120 || $descLen > 160),
                    detail: filled($seo?->meta_description) ? $descLen . ' chars' : 'empty',
                ),
                $this->pass('canonical_url set',     filled($seo?->canonical_url)),
                $this->pass('robots directive set',  filled($seo?->robots),
                    detail: $seo?->robots ?? '—'),
                $this->pass('og_title set',          filled($seo?->og_title)),
                $this->pass('og_description set',    filled($seo?->og_description)),
                $this->pass('og_image set',          filled($seo?->og_image),
                    detail: filled($seo?->og_image) ? '✓ has image' : 'empty — fallback to default'),
                $this->pass('og_type set',           filled($seo?->og_type),
                    detail: $seo?->og_type
                        ? (is_object($seo->og_type) ? $seo->og_type->value : (string) $seo->og_type)
                        : '—'),
                $this->pass('twitter_card set',      filled($seo?->twitter_card),
                    detail: $seo?->twitter_card ?? '—'),
            ],
        ];
    }

    private function checkGeo(?\App\Models\Seo\GeoEntityProfile $geo): array
    {
        $keyFacts = (array) ($geo?->key_facts ?? []);
        $faq      = (array) ($geo?->faq ?? []);

        return [
            'label' => '🤖 GEO / AI',
            'items' => [
                $this->pass('ai_summary filled',         filled($geo?->ai_summary)),
                $this->pass('use_cases filled',          filled($geo?->use_cases),   required: false),
                $this->pass('target_audience filled',    filled($geo?->target_audience), required: false),
                $this->pass('llm_context_hint filled',   filled($geo?->llm_context_hint), required: false),
                $this->pass('key_facts not empty',       ! empty($keyFacts),
                    detail: count($keyFacts) . ' fact(s)',    required: false),
                $this->pass('faq has ≥ 1 Q&A',          ! empty($faq),
                    detail: count($faq) . ' Q&A(s)',          required: false),
                // Only warn when faq actually exists — skip check (optional) when empty
                $this->warn('faq ≤ 10 items (Google limit)',
                    empty($faq) || count($faq) <= 10,
                    warn: ! empty($faq) && count($faq) > 10,
                    detail: count($faq) . ' items'),
            ],
        ];
    }

    private function checkLlms(?\App\Models\Seo\LlmsEntry $llms): array
    {
        $daysSinceSync = $llms?->updated_at?->diffInDays(now());

        return [
            'label' => '📄 LLMs Entry',
            'items' => [
                $this->pass('Entry exists',                    $llms !== null),
                $this->pass('Entry published (is_active)',     $llms?->is_active ?? false),
                $this->pass('Summary filled',                  filled($llms?->summary)),
                $this->warn('Synced within last 30 days',
                    $daysSinceSync !== null && $daysSinceSync <= 30,
                    warn: $daysSinceSync !== null && $daysSinceSync > 30,
                    detail: $llms?->updated_at ? $llms->updated_at->diffForHumans() : 'never synced'),
            ],
        ];
    }

    private function checkJsonld(\Illuminate\Database\Eloquent\Collection $schemas, array $faq): array
    {
        $items         = [];
        $expectedTypes = ['Product', 'BreadcrumbList'];

        if (! empty($faq)) {
            $expectedTypes[] = 'FAQPage';
        }

        foreach ($expectedTypes as $type) {
            $schema = $schemas->first(fn ($s) =>
                (is_object($s->schema_type) ? $s->schema_type->value : (string) $s->schema_type) === $type
            );

            $exists  = $schema !== null;
            $active  = $schema?->is_active ?? false;
            $hasData = ! empty($schema?->payload);

            $items[] = $this->pass(
                "{$type}: exists & active",
                $exists && $active,
                detail: $exists
                    ? ($active ? 'active' : '⚠️ inactive — will not appear in <head>')
                    : 'missing — run: php artisan jsonld:sync product ' . '--all',
            );

            if ($exists) {
                $items[] = $this->pass(
                    "{$type}: payload not empty",
                    $hasData,
                    detail: $hasData
                        ? count((array) $schema->payload) . ' top-level keys'
                        : '⚠️ empty payload',
                );

                $items[] = $this->pass(
                    "{$type}: auto-generated flag",
                    $schema->is_auto_generated,
                    detail: $schema->is_auto_generated ? '⚡ auto (safe)' : '✎ manual (observer will not overwrite)',
                    required: false,
                );
            }
        }

        // Any extra schemas not in the expected list
        $extras = $schemas->filter(fn ($s) =>
            ! in_array(
                is_object($s->schema_type) ? $s->schema_type->value : (string) $s->schema_type,
                $expectedTypes
            )
        );

        foreach ($extras as $schema) {
            $type    = is_object($schema->schema_type) ? $schema->schema_type->value : (string) $schema->schema_type;
            $hasData = ! empty($schema->payload);
            $items[] = $this->pass(
                "{$type}: active & has payload",
                $schema->is_active && $hasData,
                detail: ($schema->is_active ? 'active' : 'inactive') . ' · ' . ($hasData ? count((array) $schema->payload) . ' keys' : 'empty payload'),
                required: false,
            );
        }

        return ['label' => '🧩 JSON-LD Schemas', 'items' => $items];
    }

    // ── Check factories ───────────────────────────────────────────────────────

    /** Hard fail — counts against score */
    private function pass(string $label, bool $result, string $detail = '', bool $required = true): array
    {
        return [
            'label'    => $label,
            'state'    => $result ? 'pass' : ($required ? 'fail' : 'optional'),
            'detail'   => $detail,
        ];
    }

    /** Soft warning — yellow in output, still counts */
    private function warn(string $label, bool $result, bool $warn = false, string $detail = ''): array
    {
        $state = $result ? 'pass' : ($warn ? 'warn' : 'fail');

        return [
            'label'  => $label,
            'state'  => $state,
            'detail' => $detail,
        ];
    }

    // ── Markdown builder ──────────────────────────────────────────────────────

    private function buildMarkdown(Product $product, array $sections): string
    {
        [$totalReq, $passedReq, $issues] = $this->scoreData($sections);

        $score = $totalReq > 0 ? (int) round($passedReq / $totalReq * 100) : 0;
        $grade = match (true) {
            $score >= 90 => '✅ Excellent',
            $score >= 70 => '🟡 Good',
            $score >= 50 => '🟠 Needs Improvement',
            default      => '🔴 Poor',
        };

        $seo     = $product->seoMeta();
        $geo     = $product->geoProfile();
        $llms    = $product->llmsEntries->first();
        $schemas = $product->jsonldSchemas;

        $lines = [];

        // ── Title ─────────────────────────────────────────────────────────────
        $lines[] = '# 📋 Product Audit Report';
        $lines[] = '';
        $lines[] = '> **' . e($product->name) . '**';
        $lines[] = '> Generated: ' . now()->format('d/m/Y H:i:s') . ' (UTC+7)';
        $lines[] = '';

        // ── Product info table ────────────────────────────────────────────────
        $lines[] = '| Field | Value |';
        $lines[] = '|---|---|';
        $lines[] = '| ID | `' . $product->id . '` |';
        $lines[] = '| SKU | `' . ($product->sku ?? '—') . '` |';
        $lines[] = '| Slug | `' . ($product->slug ?? '—') . '` |';
        $lines[] = '| Status | ' . ($product->is_active ? '🟢 Active' : '🔴 Inactive') . ' |';
        $lines[] = '| Price | ' . number_format((float) ($product->price ?? 0)) . ' ' . ($product->currency ?? 'VND') . ' |';
        $lines[] = '| Sale Price | ' . ($product->sale_price ? number_format((float) $product->sale_price) : '—') . ' |';
        $lines[] = '| Stock | ' . ($product->stock_quantity ?? '—') . ' |';
        $lines[] = '| Brand | ' . ($product->brand?->name ?? '—') . ' |';
        $lines[] = '| Manufacturer | ' . ($product->manufacturer?->name ?? '—') . ' |';
        $lines[] = '| Categories | ' . ($product->categories->pluck('name')->join(', ') ?: '—') . ' |';
        $lines[] = '| Images | ' . $product->images->count() . ' |';
        $lines[] = '| Attributes | ' . $product->attributes->count() . ' |';
        $lines[] = '| Variants | ' . $product->variants->count() . ' |';
        $lines[] = '';

        // ── Overall score ─────────────────────────────────────────────────────
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## Overall Score: ' . $score . '/100 — ' . $grade;
        $lines[] = '';
        $lines[] = $this->scoreBar($score);
        $lines[] = '';
        $lines[] = '| | Count |';
        $lines[] = '|---|---|';
        $lines[] = '| ✅ Passed | ' . $passedReq . ' |';
        $lines[] = '| ❌ Failed | ' . ($totalReq - $passedReq) . ' |';
        $lines[] = '| Total required checks | ' . $totalReq . ' |';
        $lines[] = '';

        // ── Issues list ───────────────────────────────────────────────────────
        if (! empty($issues)) {
            $lines[] = '---';
            $lines[] = '';
            $lines[] = '## ⚠️ Issues to Fix (' . count($issues) . ')';
            $lines[] = '';
            foreach ($issues as $issue) {
                $lines[] = $issue;
            }
            $lines[] = '';
        }

        // ── Detailed checks per section ───────────────────────────────────────
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## Detailed Checks';
        $lines[] = '';

        foreach ($sections as $section) {
            $items    = $section['items'];
            $reqItems = array_filter($items, fn ($i) => $i['state'] !== 'optional');
            $passed   = array_filter($reqItems, fn ($i) => $i['state'] === 'pass');
            $pCount   = count($passed);
            $rCount   = count($reqItems);

            $icon = match (true) {
                $pCount === $rCount            => '✅',
                $pCount >= (int) ($rCount * .5) => '🟡',
                default                         => '🔴',
            };

            $lines[] = '### ' . $icon . ' ' . $section['label'] . ' (' . $pCount . '/' . $rCount . ')';
            $lines[] = '';

            foreach ($items as $item) {
                $marker = match ($item['state']) {
                    'pass'     => '- [x]',
                    'warn'     => '- [~]',
                    'optional' => '- [·]',
                    default    => '- [ ]',
                };
                $detail = filled($item['detail']) ? '  `' . $item['detail'] . '`' : '';
                $flag   = $item['state'] === 'optional' ? ' _(optional)_' : '';
                $lines[] = $marker . ' ' . $item['label'] . $detail . $flag;
            }
            $lines[] = '';
        }

        // ── SEO detail ────────────────────────────────────────────────────────
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## 🔍 SEO Meta — Full Values';
        $lines[] = '';
        $lines[] = '| Field | Value |';
        $lines[] = '|---|---|';
        $lines[] = '| meta_title | ' . $this->td($seo?->meta_title) . ' (' . mb_strlen($seo?->meta_title ?? '') . ' chars) |';
        $lines[] = '| meta_description | ' . $this->td($seo?->meta_description) . ' (' . mb_strlen($seo?->meta_description ?? '') . ' chars) |';
        $lines[] = '| canonical_url | ' . $this->td($seo?->canonical_url) . ' |';
        $lines[] = '| robots | `' . ($seo?->robots ?? '—') . '` |';
        $lines[] = '| og_title | ' . $this->td($seo?->og_title) . ' |';
        $lines[] = '| og_description | ' . $this->td($seo?->og_description) . ' |';
        $lines[] = '| og_image | ' . $this->td($seo?->og_image) . ' |';
        $lines[] = '| og_type | `' . ($seo?->og_type
            ? (is_object($seo->og_type) ? $seo->og_type->value : (string) $seo->og_type)
            : '—') . '` |';
        $lines[] = '| twitter_card | `' . ($seo?->twitter_card ?? '—') . '` |';
        $lines[] = '| twitter_title | ' . $this->td($seo?->twitter_title) . ' |';
        $lines[] = '';

        // ── GEO/AI detail ─────────────────────────────────────────────────────
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## 🤖 GEO / AI — Full Values';
        $lines[] = '';

        if (! $geo) {
            $lines[] = '_No GEO profile found — profile is created on first save._';
        } else {
            if (filled($geo->ai_summary)) {
                $lines[] = '**AI Summary:**';
                $lines[] = '';
                $lines[] = '> ' . str_replace("\n", "\n> ", trim($geo->ai_summary));
                $lines[] = '';
            } else {
                $lines[] = '**AI Summary:** _empty_';
                $lines[] = '';
            }

            $lines[] = '**Use Cases:** ' . ($geo->use_cases ? trim($geo->use_cases) : '_empty_');
            $lines[] = '';
            $lines[] = '**Target Audience:** ' . ($geo->target_audience ?? '_empty_');
            $lines[] = '';

            if (filled($geo->llm_context_hint)) {
                $lines[] = '**LLM Context Hint:**';
                $lines[] = '> ' . trim($geo->llm_context_hint);
                $lines[] = '';
            }

            $keyFacts = (array) ($geo->key_facts ?? []);
            if (! empty($keyFacts)) {
                $lines[] = '**Key Facts (' . count($keyFacts) . '):**';
                $lines[] = '';
                foreach ($keyFacts as $k => $v) {
                    $lines[] = '- **' . $k . ':** ' . $v;
                }
                $lines[] = '';
            } else {
                $lines[] = '**Key Facts:** _none_';
                $lines[] = '';
            }

            $faq = (array) ($geo->faq ?? []);
            if (! empty($faq)) {
                $lines[] = '**FAQ (' . count($faq) . ' items):**';
                $lines[] = '';
                foreach ($faq as $i => $item) {
                    $q = $item['question'] ?? '';
                    $a = $item['answer'] ?? '';
                    $lines[] = ($i + 1) . '. **Q:** ' . $q;
                    $lines[] = '   **A:** ' . $a;
                    $lines[] = '';
                }
            } else {
                $lines[] = '**FAQ:** _none_';
                $lines[] = '';
            }
        }

        // ── LLMs detail ───────────────────────────────────────────────────────
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## 📄 LLMs Entry';
        $lines[] = '';

        if (! $llms) {
            $lines[] = '_No LLMs entry found. Save the product to trigger the observer, or run:_';
            $lines[] = '```bash';
            $lines[] = 'php artisan llms:generate';
            $lines[] = '```';
        } else {
            $lines[] = '| Field | Value |';
            $lines[] = '|---|---|';
            $lines[] = '| Title | ' . $this->td($llms->title) . ' |';
            $lines[] = '| URL | ' . ($llms->url ?? '—') . ' |';
            $lines[] = '| Published | ' . ($llms->is_active ? '✅ Yes' : '❌ No') . ' |';
            $lines[] = '| Last synced | ' . ($llms->updated_at?->format('d/m/Y H:i') ?? '—') . ' |';
            $lines[] = '';

            if (filled($llms->summary)) {
                $preview = mb_substr($llms->summary, 0, 400);
                if (mb_strlen($llms->summary) > 400) {
                    $preview .= "\n…(truncated)";
                }
                $lines[] = '**Summary preview:**';
                $lines[] = '```';
                $lines[] = $preview;
                $lines[] = '```';
                $lines[] = '';
            }

            if (filled($llms->key_facts_text)) {
                $lines[] = '**Key Facts (assembled):**';
                $lines[] = '```';
                $lines[] = mb_substr($llms->key_facts_text, 0, 600);
                $lines[] = '```';
                $lines[] = '';
            }
        }

        // ── JSON-LD detail ────────────────────────────────────────────────────
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## 🧩 JSON-LD Schemas';
        $lines[] = '';

        if ($schemas->isEmpty()) {
            $lines[] = '_No schemas found. Run:_';
            $lines[] = '```bash';
            $lines[] = 'php artisan jsonld:sync product --all';
            $lines[] = '```';
        } else {
            // ── Summary table ─────────────────────────────────────────────────
            $lines[] = '| Schema | Label | Active | Mode | Payload Keys | Updated |';
            $lines[] = '|---|---|---|---|---|---|';
            foreach ($schemas->sortBy('sort_order') as $schema) {
                $type    = is_object($schema->schema_type) ? $schema->schema_type->value : (string) $schema->schema_type;
                $label   = $schema->label ?? '—';
                $active  = $schema->is_active ? '✅' : '❌';
                $mode    = $schema->is_auto_generated ? '⚡ Auto' : '✎ Manual';
                $keys    = ! empty($schema->payload) ? count((array) $schema->payload) . ' keys' : '⚠️ empty';
                $updated = $schema->updated_at?->format('d/m/Y H:i') ?? '—';
                $lines[] = "| {$type} | {$label} | {$active} | {$mode} | {$keys} | {$updated} |";
            }
            $lines[] = '';

            // ── Full payload per schema ───────────────────────────────────────
            foreach ($schemas->sortBy('sort_order') as $schema) {
                $type = is_object($schema->schema_type) ? $schema->schema_type->value : (string) $schema->schema_type;
                $mode = $schema->is_auto_generated ? '⚡ Auto' : '✎ Manual';
                $flag = $schema->is_active ? '✅ active' : '❌ inactive';

                $lines[] = '#### ' . $type . ' — ' . $mode . ' · ' . $flag;
                $lines[] = '';

                if (empty($schema->payload)) {
                    $lines[] = '_Empty payload — run `php artisan jsonld:sync product ' . $product->id . '`_';
                } else {
                    $json = json_encode(
                        $schema->payload,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    );
                    $lines[] = '```json';
                    $lines[] = $json;
                    $lines[] = '```';
                }
                $lines[] = '';
            }
        }

        // ── Attributes detail ─────────────────────────────────────────────────
        if ($product->attributes->isNotEmpty()) {
            $lines[] = '---';
            $lines[] = '';
            $lines[] = '## 🏷️ Attributes';
            $lines[] = '';
            $lines[] = '| # | Name | Value |';
            $lines[] = '|---|---|---|';
            foreach ($product->attributes as $i => $attr) {
                $lines[] = '| ' . ($i + 1) . ' | ' . $this->td($attr->name) . ' | ' . $this->td($attr->value) . ' |';
            }
            $lines[] = '';
        }

        // ── Regeneration hints ────────────────────────────────────────────────
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## 🔧 Quick Fix Commands';
        $lines[] = '';
        $lines[] = '```bash';
        $lines[] = '# Re-sync JSON-LD schemas for this product';
        $lines[] = 'php artisan jsonld:sync product ' . $product->id;
        $lines[] = '';
        $lines[] = '# Re-sync LLMs entry';
        $lines[] = 'php artisan llms:generate';
        $lines[] = '';
        $lines[] = '# Re-generate this audit';
        $lines[] = 'php artisan audit:product ' . $product->slug;
        $lines[] = '```';
        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '_Audit generated by `php artisan audit:product` — Backbone Admin_';

        return implode("\n", $lines);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Collect score data and build issues list */
    private function scoreData(array $sections): array
    {
        $total  = 0;
        $passed = 0;
        $issues = [];

        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {
                if ($item['state'] === 'optional') {
                    continue; // optional items don't count toward score
                }

                $total++;

                if ($item['state'] === 'pass') {
                    $passed++;
                } else {
                    $icon     = $item['state'] === 'warn' ? '⚠️' : '❌';
                    $detail   = filled($item['detail']) ? ' — `' . $item['detail'] . '`' : '';
                    $issues[] = $icon . ' **[' . $section['label'] . ']** ' . $item['label'] . $detail;
                }
            }
        }

        return [$total, $passed, $issues];
    }

    /** ASCII progress bar for score */
    private function scoreBar(int $score): string
    {
        $filled = (int) round($score / 5);
        $empty  = 20 - $filled;

        return '`[' . str_repeat('█', $filled) . str_repeat('░', $empty) . '] ' . $score . '%`';
    }

    /** Safe value for markdown table cell — escapes pipes, truncates long strings */
    private function td(?string $value, int $max = 80): string
    {
        if ($value === null || $value === '') {
            return '_empty_';
        }

        $value = str_replace(['|', "\n", "\r"], ['\|', ' ', ''], $value);

        if (mb_strlen($value) > $max) {
            $value = mb_substr($value, 0, $max) . '…';
        }

        return $value;
    }
}
