<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Models\Seo\GeoEntityProfile;
use App\Models\Seo\SeoMeta;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        $record->loadMissing('seoMetas', 'geoProfiles');

        // ── SEO meta ──────────────────────────────────────────────────────────
        $seo = $record->seoMeta('vi');

        $data['seo_og_image']      = $seo?->og_image;
        $data['seo_og_type']       = $seo?->og_type instanceof \BackedEnum
            ? $seo->og_type->value
            : ($seo?->og_type ?? 'website');
        $data['seo_twitter_card']  = $seo?->twitter_card  ?? 'summary_large_image';
        $data['seo_robots']        = $seo?->robots        ?? 'index,follow';
        $data['seo_canonical_url'] = $seo?->canonical_url;

        // ── GEO profile ───────────────────────────────────────────────────────
        $geo = $record->geoProfile('vi');

        $data['geo_ai_summary']      = $geo?->ai_summary;
        $data['geo_use_cases']       = $geo?->use_cases;
        $data['geo_target_audience'] = $geo?->target_audience;
        $data['geo_key_facts']       = $geo?->key_facts ?? [];
        $data['geo_faq']             = $geo?->faq        ?? [];

        // ── Translations ──────────────────────────────────────────────────────
        foreach (config('app.supported_locales') as $locale) {
            $translation = $record->translations()->where('locale', $locale)->first();

            if ($translation) {
                $data['translations'][$locale] = $translation->only([
                    'name', 'slug', 'description', 'rich_content',
                    'meta_title', 'meta_description',
                    'og_title', 'og_description',
                    'twitter_title', 'twitter_description',
                ]);
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $state      = $this->data;
        $morphClass = $this->record->getMorphClass();
        $modelId    = $this->record->getKey();

        // ── SEO meta ──────────────────────────────────────────────────────────
        $ogImage = $state['seo_og_image'] ?? null;
        if (is_array($ogImage)) {
            $ogImage = $ogImage[0] ?? null;
        }

        SeoMeta::updateOrCreate(
            ['model_type' => $morphClass, 'model_id' => $modelId, 'locale' => 'vi'],
            [
                'og_image'      => $ogImage,
                'og_type'       => $state['seo_og_type']       ?? 'website',
                'twitter_card'  => $state['seo_twitter_card']  ?? 'summary_large_image',
                'robots'        => $state['seo_robots']        ?? 'index,follow',
                'canonical_url' => $state['seo_canonical_url'] ?? null,
            ]
        );

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

        GeoEntityProfile::updateOrCreate(
            ['model_type' => $morphClass, 'model_id' => $modelId, 'locale' => 'vi'],
            [
                'ai_summary'      => $state['geo_ai_summary']      ?? null,
                'use_cases'       => $state['geo_use_cases']        ?? null,
                'target_audience' => $state['geo_target_audience']  ?? null,
                'key_facts'       => $keyFacts ?: null,
                'faq'             => $faqItems ?: null,
            ]
        );

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
