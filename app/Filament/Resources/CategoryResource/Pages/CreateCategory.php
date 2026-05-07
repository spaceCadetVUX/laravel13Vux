<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Models\Seo\GeoEntityProfile;
use App\Models\Seo\SeoMeta;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    protected function afterCreate(): void
    {
        $state      = $this->data;
        $morphClass = $this->record->getMorphClass();
        $modelId    = $this->record->getKey();

        // ── SEO meta ──────────────────────────────────────────────────────────
        $ogImage = $state['seo_og_image'] ?? null;
        if (is_array($ogImage)) {
            $ogImage = $ogImage[0] ?? null;
        }

        $hasAnySeo = filled($ogImage)
            || filled($state['seo_og_type'] ?? null)
            || filled($state['seo_twitter_card'] ?? null)
            || filled($state['seo_canonical_url'] ?? null);

        if ($hasAnySeo) {
            SeoMeta::create([
                'model_type'   => $morphClass,
                'model_id'     => $modelId,
                'locale'       => 'vi',
                'og_image'     => $ogImage,
                'og_type'      => $state['seo_og_type']      ?? 'website',
                'twitter_card' => $state['seo_twitter_card'] ?? 'summary_large_image',
                'robots'       => $state['seo_robots']        ?? 'index,follow',
                'canonical_url'=> $state['seo_canonical_url'] ?? null,
            ]);
        }

        // ── GEO profile ───────────────────────────────────────────────────────
        $keyFacts = collect($state['geo_key_facts'] ?? [])
            ->filter(fn (array $row): bool => filled($row['label'] ?? null))
            ->values()
            ->toArray();

        $faqItems = collect($state['geo_faq'] ?? [])
            ->filter(fn (array $row): bool => filled($row['question'] ?? null))
            ->map(fn (array $row): array => [
                'question' => trim($row['question']),
                'answer'   => trim($row['answer'] ?? ''),
            ])
            ->values()
            ->toArray();

        $hasAnyGeo = filled($state['geo_ai_summary'] ?? null)
            || filled($state['geo_use_cases'] ?? null)
            || filled($state['geo_target_audience'] ?? null)
            || ! empty($keyFacts)
            || ! empty($faqItems);

        if ($hasAnyGeo) {
            GeoEntityProfile::create([
                'model_type'      => $morphClass,
                'model_id'        => $modelId,
                'locale'          => 'vi',
                'ai_summary'      => $state['geo_ai_summary']      ?? null,
                'use_cases'       => $state['geo_use_cases']        ?? null,
                'target_audience' => $state['geo_target_audience']  ?? null,
                'key_facts'       => $keyFacts ?: null,
                'faq'             => $faqItems ?: null,
            ]);
        }

        // ── Translations ──────────────────────────────────────────────────────
        $translationsData = $state['translations'] ?? [];

        foreach (config('app.supported_locales') as $locale) {
            $localeData = $translationsData[$locale] ?? [];

            if (empty($localeData['name'])) {
                continue;
            }

            $this->record->translations()->updateOrCreate(
                ['locale' => $locale],
                collect($localeData)
                    ->only([
                        'name', 'slug', 'description', 'rich_content',
                        'meta_title', 'meta_description',
                        'og_title', 'og_description',
                        'twitter_title', 'twitter_description',
                    ])
                    ->filter(fn ($v) => $v !== null && $v !== '')
                    ->toArray()
            );
        }
    }
}
