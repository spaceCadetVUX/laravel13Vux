<?php

namespace App\Filament\Resources\BlogPostResource\Pages;

use App\Filament\Resources\BlogPostResource;
use App\Models\Seo\GeoEntityProfile;
use Filament\Resources\Pages\CreateRecord;

class CreateBlogPost extends CreateRecord
{
    protected static string $resource = BlogPostResource::class;

    protected function afterCreate(): void
    {
        $state      = $this->data;
        $morphClass = $this->record->getMorphClass();
        $modelId    = $this->record->getKey();

        // ── FAQ — only create row if at least one question is filled ──────────
        $faqItems = collect($state['faq_items'] ?? [])
            ->filter(fn (array $item): bool => filled($item['question'] ?? null))
            ->map(fn (array $item): array => [
                'question' => trim($item['question']),
                'answer'   => trim($item['answer'] ?? ''),
            ])
            ->values()
            ->toArray();

        if (! empty($faqItems)) {
            GeoEntityProfile::create([
                'model_type' => $morphClass,
                'model_id'   => $modelId,
                'faq'        => $faqItems,
            ]);
        }

        // ── Translations ──────────────────────────────────────────────────────
        $translationsData = $state['translations'] ?? [];

        foreach (config('app.supported_locales') as $locale) {
            $localeData = $translationsData[$locale] ?? [];

            if (empty($localeData['title'])) {
                continue;
            }

            $this->record->translations()->updateOrCreate(
                ['locale' => $locale],
                collect($localeData)
                    ->only(['title', 'slug', 'excerpt', 'body'])
                    ->filter(fn ($v) => $v !== null && $v !== '')
                    ->toArray()
            );
        }
    }
}
