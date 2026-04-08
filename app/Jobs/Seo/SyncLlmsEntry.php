<?php

namespace App\Jobs\Seo;

use App\Enums\LlmsScope;
use App\Models\Seo\LlmsDocument;
use App\Models\Seo\LlmsEntry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

class SyncLlmsEntry implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * URL prefix per morph alias — mirrors SyncSitemapEntry for consistency.
     */
    private const URL_PREFIXES = [
        'product'   => '/products/',
        'blog_post' => '/blog/',
        'category'  => '/categories/',
    ];

    public function __construct(
        public readonly Model $model,
    ) {}

    public function handle(): void
    {
        $morphAlias = $this->model->getMorphClass();

        // LlmsDocument.model_type stores the full class name (from seeder).
        $document = LlmsDocument::where('model_type', get_class($this->model))
            ->where('scope', LlmsScope::Full)
            ->first();

        if ($document === null) {
            return;
        }

        // ── Load GEO profile for richer LLMs content ─────────────────────────
        $geoProfile = method_exists($this->model, 'geoProfile')
            ? $this->model->geoProfile
            : null;

        // ── Title: try title first (BlogPost), fall back to name (Product / Category) ──
        $title = (string) ($this->model->getAttribute('title')
            ?? $this->model->getAttribute('name')
            ?? '');

        // ── Canonical URL ─────────────────────────────────────────────────────
        $slug = (string) ($this->model->getAttribute('slug') ?? '');
        $url  = rtrim((string) config('app.url'), '/')
            . (self::URL_PREFIXES[$morphAlias] ?? '/')
            . $slug;

        // ── Summary from GEO profile ──────────────────────────────────────────
        $summary = (string) ($geoProfile?->ai_summary ?? '');

        // ── key_facts jsonb → plain text ──────────────────────────────────────
        // GeoEntityProfile stores key_facts as {"Fact Label": "Fact Value", ...}
        $keyFacts     = (array) ($geoProfile?->key_facts ?? []);
        $keyFactsText = collect($keyFacts)
            ->map(fn (string $value, string $key): string => "- {$key}: {$value}")
            ->implode("\n");

        // ── faq jsonb → plain text ────────────────────────────────────────────
        // GeoEntityProfile stores faq as [{"question": "...", "answer": "..."}, ...]
        $faq     = (array) ($geoProfile?->faq ?? []);
        $faqText = collect($faq)
            ->map(function (array $item): string {
                $q = trim((string) ($item['question'] ?? ''));
                $a = trim((string) ($item['answer'] ?? ''));
                return "Q: {$q}\nA: {$a}";
            })
            ->implode("\n\n");

        // ── Upsert LlmsEntry ──────────────────────────────────────────────────
        LlmsEntry::updateOrCreate(
            [
                'llms_document_id' => $document->id,
                'model_type'       => $morphAlias,
                'model_id'         => $this->model->getKey(),
            ],
            [
                'title'          => $title,
                'url'            => $url,
                'summary'        => $summary,
                'key_facts_text' => $keyFactsText,
                'faq_text'       => $faqText,
                'is_active'      => (bool) ($this->model->getAttribute('is_active') ?? true),
            ]
        );

        // ── Keep parent document entry_count + last_generated_at in sync ──────
        $document->update([
            'entry_count'       => LlmsEntry::where('llms_document_id', $document->id)
                ->where('is_active', true)
                ->count(),
            'last_generated_at' => now(),
        ]);
    }
}
