<?php

namespace App\Services\Seo;

use App\Enums\LlmsScope;
use App\Models\BusinessProfile;
use App\Models\Seo\LlmsDocument;
use App\Models\Seo\LlmsEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class LlmsGeneratorService
{
    /**
     * URL prefix per morph alias — used when building entry URLs.
     */
    private const URL_PREFIXES = [
        'product'   => '/products/',
        'blog_post' => '/blog/',
        'category'  => '/categories/',
    ];

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Regenerate all active LLMs documents.
     * Called by `php artisan llms:generate`.
     */
    public function generateAll(): void
    {
        LlmsDocument::where('is_active', true)->each(
            fn (LlmsDocument $document) => $this->generateDocument($document)
        );
    }

    /**
     * Build and write the .txt file for a single LlmsDocument.
     *
     * scope = index → one-liner per entry (table of contents)
     * scope = full  → full block per entry (title + url + summary + facts + faq)
     *
     * Output: storage/app/public/llms/{slug}.txt
     */
    public function generateDocument(LlmsDocument $document): void
    {
        // Business document has no llms_entries — generated directly from BusinessProfile.
        if ($document->slug === 'business') {
            $this->generateBusinessDocument($document);
            return;
        }

        $entries = LlmsEntry::where('llms_document_id', $document->id)
            ->where('is_active', true)
            ->orderBy('title')
            ->get();

        $lines = [];

        // ── File header ───────────────────────────────────────────────────────
        $lines[] = '# ' . ($document->title ?? $document->slug);

        if (filled($document->description)) {
            $lines[] = '';
            $lines[] = $document->description;
        }

        $lines[] = '';

        // ── Entry blocks ──────────────────────────────────────────────────────
        if ($entries->isEmpty()) {
            $lines[] = '_No entries yet._';
        } elseif ($document->scope === LlmsScope::Index) {
            foreach ($entries as $entry) {
                $lines[] = $this->buildIndexLine($entry);
            }
        } else {
            // LlmsScope::Full
            $blocks = $entries->map(fn (LlmsEntry $entry) => $this->buildEntryBlock($entry));
            $lines[] = implode("\n\n---\n\n", $blocks->toArray());
        }

        $lines[] = '';

        $content = implode("\n", $lines);

        Storage::disk('public')->makeDirectory('llms');
        Storage::disk('public')->put('llms/' . $document->slug . '.txt', $content);

        $document->update([
            'entry_count'       => $entries->count(),
            'last_generated_at' => now(),
        ]);
    }

    /**
     * Upsert a single llms_entries row for a model.
     *
     * Sources (in priority order):
     *   summary        = geoProfile.ai_summary → fallback: model.short_description
     *                    + appended: use_cases, target_audience, llm_context_hint
     *   key_facts_text = brand + manufacturer (if present)
     *                    + Technical Specs (product_attributes if present)
     *                    + geoProfile.key_facts
     *   faq_text       = geoProfile.faq
     *
     * No migration needed — extra data is embedded as labelled sections
     * in the existing text columns.
     *
     * @param  LlmsDocument|null  $document  Pass explicitly to avoid a repeated DB lookup.
     */
    public function upsertEntry(Model $model, ?LlmsDocument $document = null): void
    {
        $morphAlias = $model->getMorphClass();

        $document ??= LlmsDocument::where('model_type', get_class($model))
            ->where('scope', LlmsScope::Full)
            ->first();

        if ($document === null) {
            return;
        }

        // ── GEO profile ───────────────────────────────────────────────────────
        $geoProfile = method_exists($model, 'geoProfile')
            ? $model->geoProfile
            : null;

        // ── Core fields ───────────────────────────────────────────────────────
        $title   = (string) ($model->getAttribute('title') ?? $model->getAttribute('name') ?? '');
        $slug    = (string) ($model->getAttribute('slug') ?? '');
        $baseUrl = rtrim((string) config('app.url'), '/');
        $url     = $baseUrl . (self::URL_PREFIXES[$morphAlias] ?? '/') . $slug;

        // ── Summary block ─────────────────────────────────────────────────────
        // Priority: ai_summary → short_description (products) / excerpt (blog posts) → empty
        // Appended: use_cases, target_audience, llm_context_hint (if filled)
        $summaryParts = [];

        $aiSummary        = trim((string) ($geoProfile?->ai_summary ?? ''));
        $shortDescription = trim((string) ($model->getAttribute('short_description') ?? ''));
        $excerpt          = trim((string) ($model->getAttribute('excerpt') ?? ''));

        // Blog posts use `excerpt`, not `short_description` — fall through both.
        $baseSummary = filled($aiSummary) ? $aiSummary
            : (filled($shortDescription) ? $shortDescription : $excerpt);

        $summaryParts[] = $baseSummary;

        if (filled($geoProfile?->use_cases)) {
            $summaryParts[] = 'Use Cases: ' . trim($geoProfile->use_cases);
        }

        if (filled($geoProfile?->target_audience)) {
            $summaryParts[] = 'Target Audience: ' . trim($geoProfile->target_audience);
        }

        if (filled($geoProfile?->llm_context_hint)) {
            $summaryParts[] = 'Additional Context: ' . trim($geoProfile->llm_context_hint);
        }

        $summary = implode("\n\n", array_filter($summaryParts));

        // ── Key facts block ───────────────────────────────────────────────────
        // Sections: [Brand / Manufacturer] + [Technical Specs] + [Key Facts]
        $keyFactsSections = [];

        // Brand & Manufacturer (product-specific — silently skip if not present)
        $brandLines = [];
        if (method_exists($model, 'brand')) {
            $model->loadMissing('brand');
            $brandName = $model->getRelationValue('brand')?->name;
            if (filled($brandName)) {
                $brandLines[] = "  - Brand: {$brandName}";
            }
        }
        if (method_exists($model, 'manufacturer')) {
            $model->loadMissing('manufacturer');
            $mfrName = $model->getRelationValue('manufacturer')?->name;
            if (filled($mfrName)) {
                $brandLines[] = "  - Manufacturer: {$mfrName}";
            }
        }
        if (! empty($brandLines)) {
            $keyFactsSections[] = implode("\n", $brandLines);
        }

        // Technical Specs from product_attributes (product-specific)
        // Uses getRelationValue() to avoid conflict with Eloquent's magic $attributes property.
        if (method_exists($model, 'attributes')) {
            try {
                $model->loadMissing('attributes');
                $attrs = $model->getRelationValue('attributes');

                if ($attrs && $attrs->isNotEmpty()) {
                    $specLines = $attrs->map(
                        fn ($attr): string => "  - {$attr->name}: {$attr->value}"
                    )->all();

                    $keyFactsSections[] = "Technical Specs:\n" . implode("\n", $specLines);
                }
            } catch (\Throwable) {
                // Silently skip — not all models have an attributes relationship.
            }
        }

        // Blog post: author, category, tags (contextual info for AI consumers)
        if ($morphAlias === 'blog_post') {
            $blogContextLines = [];

            // Author
            if (method_exists($model, 'author')) {
                $model->loadMissing('author');
                $author = $model->getRelationValue('author');
                if ($author) {
                    $authorName  = trim((string) ($author->name ?? ''));
                    $authorTitle = trim((string) ($author->title ?? ''));
                    $authorLine  = "  - Author: {$authorName}";
                    if (filled($authorTitle)) {
                        $authorLine .= " ({$authorTitle})";
                    }
                    if (filled($authorName)) {
                        $blogContextLines[] = $authorLine;
                    }
                }
            }

            // Category
            if (method_exists($model, 'blogCategory')) {
                $model->loadMissing('blogCategory');
                $category     = $model->getRelationValue('blogCategory');
                $categoryName = trim((string) ($category?->name ?? ''));
                if (filled($categoryName)) {
                    $blogContextLines[] = "  - Category: {$categoryName}";
                }
            }

            // Tags
            if (method_exists($model, 'blogTags')) {
                $model->loadMissing('blogTags');
                $tags     = $model->getRelationValue('blogTags');
                $tagNames = $tags?->pluck('name')->filter()->implode(', ');
                if (filled($tagNames)) {
                    $blogContextLines[] = "  - Tags: {$tagNames}";
                }
            }

            if (! empty($blogContextLines)) {
                $keyFactsSections[] = implode("\n", $blogContextLines);
            }
        }

        // GEO key_facts jsonb → indented plain text
        // Stored as {"Label": "Value", ...} by the Filament KeyValue component.
        $keyFacts = (array) ($geoProfile?->key_facts ?? []);
        if (! empty($keyFacts)) {
            $factLines = collect($keyFacts)
                ->map(fn (string $v, string $k): string => "  - {$k}: {$v}")
                ->values()
                ->all();

            $keyFactsSections[] = "Key Facts:\n" . implode("\n", $factLines);
        }

        $keyFactsText = implode("\n\n", $keyFactsSections);

        // ── FAQ jsonb → indented Q&A plain text ──────────────────────────────
        // Stored as [{"question": "...", "answer": "..."}, ...] by Repeater.
        $faq     = (array) ($geoProfile?->faq ?? []);
        $faqText = collect($faq)
            ->map(function (array $item): string {
                $q = trim((string) ($item['question'] ?? ''));
                $a = trim((string) ($item['answer'] ?? ''));
                return "  Q: {$q}\n  A: {$a}";
            })
            ->implode("\n\n");

        // ── Upsert ────────────────────────────────────────────────────────────
        LlmsEntry::updateOrCreate(
            [
                'llms_document_id' => $document->id,
                'model_type'       => $morphAlias,
                'model_id'         => $model->getKey(),
            ],
            [
                'title'          => $title,
                'url'            => $url,
                'summary'        => $summary,
                'key_facts_text' => $keyFactsText,
                'faq_text'       => $faqText,
                'is_active'      => $this->resolveIsActive($model, $morphAlias),
            ]
        );

        $document->update([
            'entry_count'       => LlmsEntry::where('llms_document_id', $document->id)
                ->where('is_active', true)
                ->count(),
            'last_generated_at' => now(),
        ]);
    }

    /**
     * Generate business.txt from BusinessProfile — no llms_entries involved.
     * Called when slug === 'business'.
     */
    private function generateBusinessDocument(LlmsDocument $document): void
    {
        $profile = BusinessProfile::instance();
        $lines   = [];

        $lines[] = '# ' . $profile->name;

        $intro = $profile->description ?? $profile->tagline ?? '';
        if (filled($intro)) {
            $lines[] = '';
            $lines[] = $intro;
        }

        $lines[] = '';

        // Contact
        $contactLines = [];
        if (filled($profile->email)) {
            $contactLines[] = '- Email: ' . $profile->email;
        }
        if (filled($profile->phone)) {
            $contactLines[] = '- Phone: ' . $profile->phone;
        }

        $addressParts = array_filter([
            $profile->address_line,
            $profile->city,
            $profile->state,
            $profile->country,
        ]);
        if (! empty($addressParts)) {
            $contactLines[] = '- Address: ' . implode(', ', $addressParts);
        }

        if (! empty($contactLines)) {
            $lines[] = '## Contact';
            array_push($lines, ...$contactLines);
            $lines[] = '';
        }

        // Business Hours
        if (! empty($profile->business_hours)) {
            $lines[] = '## Business Hours';
            foreach ((array) $profile->business_hours as $day => $hours) {
                $lines[] = "- {$day}: {$hours}";
            }
            $lines[] = '';
        }

        // Social Links
        if (! empty($profile->social_links)) {
            $lines[] = '## Online';
            foreach ((array) $profile->social_links as $platform => $url) {
                $lines[] = "- {$platform}: {$url}";
            }
            $lines[] = '';
        }

        // Business Details
        $detailLines = [];
        if (filled($profile->founded_year)) {
            $detailLines[] = '- Founded: ' . $profile->founded_year;
        }
        if (filled($profile->currency)) {
            $detailLines[] = '- Currency: ' . $profile->currency;
        }
        if (filled($profile->vat_number)) {
            $detailLines[] = '- VAT Number: ' . $profile->vat_number;
        }
        foreach ((array) ($profile->extra ?? []) as $key => $value) {
            $detailLines[] = "- {$key}: {$value}";
        }

        if (! empty($detailLines)) {
            $lines[] = '## Business Details';
            array_push($lines, ...$detailLines);
            $lines[] = '';
        }

        $content = implode("\n", $lines);

        Storage::disk('public')->makeDirectory('llms');
        Storage::disk('public')->put('llms/' . $document->slug . '.txt', $content);

        $document->update([
            'entry_count'       => 1,
            'last_generated_at' => now(),
        ]);
    }

    /**
     * Build a full Markdown block for a single entry (scope=full).
     *
     * ## {title}
     * URL: {url}
     *
     * {summary}          ← may include use_cases / target_audience / llm_context_hint
     *
     * {key_facts_text}   ← may include Brand, Technical Specs, Key Facts sections
     *
     * FAQ:
     * {faq_text}
     */
    public function buildEntryBlock(LlmsEntry $entry): string
    {
        $lines = [];

        $lines[] = '## ' . $entry->title;
        $lines[] = 'URL: ' . $entry->url;

        if (filled($entry->summary)) {
            $lines[] = '';
            $lines[] = $entry->summary;
        }

        if (filled($entry->key_facts_text)) {
            $lines[] = '';
            $lines[] = $entry->key_facts_text;
        }

        if (filled($entry->faq_text)) {
            $lines[] = '';
            $lines[] = 'FAQ:';
            $lines[] = $entry->faq_text;
        }

        return implode("\n", $lines);
    }

    /**
     * Build a single index line for a table-of-contents entry (scope=index).
     *
     * - [{title}]({url}): {summary}
     */
    public function buildIndexLine(LlmsEntry $entry): string
    {
        $line = '- [' . $entry->title . '](' . $entry->url . ')';

        if (filled($entry->summary)) {
            $line .= ': ' . $entry->summary;
        }

        return $line;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Determine whether the LlmsEntry should be active.
     *
     * - blog_post  → active only when status === 'published'
     *               (blog_posts has no is_active column — uses a status enum)
     * - all others → reads is_active column, defaults true when absent
     */
    private function resolveIsActive(Model $model, string $morphAlias): bool
    {
        if ($morphAlias === 'blog_post') {
            $status = $model->getAttribute('status');

            // Support both backed enum and raw string value.
            if ($status instanceof \BackedEnum) {
                $status = $status->value;
            }

            return $status === 'published';
        }

        return (bool) ($model->getAttribute('is_active') ?? true);
    }
}
