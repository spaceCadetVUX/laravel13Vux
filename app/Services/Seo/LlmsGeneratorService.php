<?php

namespace App\Services\Seo;

use App\Enums\LlmsScope;
use App\Models\Seo\GeoEntityProfile;
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
     * Loads the GEO profile and flattens key_facts + faq to plain text.
     * Updates the parent document entry_count after upsert.
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
        $summary = (string) ($geoProfile?->ai_summary ?? '');

        // ── key_facts jsonb → indented plain text ─────────────────────────────
        // Stored as {"Label": "Value", ...} by the Filament KeyValue component.
        $keyFacts     = (array) ($geoProfile?->key_facts ?? []);
        $keyFactsText = collect($keyFacts)
            ->map(fn (string $value, string $key): string => "  - {$key}: {$value}")
            ->implode("\n");

        // ── faq jsonb → indented Q&A plain text ──────────────────────────────
        // Stored as [{"question": "...", "answer": "..."}, ...] by Repeater.
        $faq     = (array) ($geoProfile?->faq ?? []);
        $faqText = collect($faq)
            ->map(function (array $item): string {
                $q = trim((string) ($item['question'] ?? ''));
                $a = trim((string) ($item['answer'] ?? ''));
                return "  Q: {$q}\n  A: {$a}";
            })
            ->implode("\n\n");

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
                'is_active'      => (bool) ($model->getAttribute('is_active') ?? true),
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
     * Build a full Markdown block for a single entry (scope=full).
     *
     * ## {title}
     * URL: {url}
     * Summary: {summary}
     * Key Facts:
     * {key_facts_text}
     * FAQ:
     * {faq_text}
     */
    public function buildEntryBlock(LlmsEntry $entry): string
    {
        $lines = [];

        $lines[] = '## ' . $entry->title;
        $lines[] = 'URL: ' . $entry->url;

        if (filled($entry->summary)) {
            $lines[] = 'Summary: ' . $entry->summary;
        }

        if (filled($entry->key_facts_text)) {
            $lines[] = 'Key Facts:';
            $lines[] = $entry->key_facts_text;
        }

        if (filled($entry->faq_text)) {
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
}
