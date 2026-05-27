<?php

namespace App\Filament\Resources\BlogPostResource\Pages;

use App\Filament\Resources\BlogPostResource;
use App\Models\Seo\GeoEntityProfile;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBlogPost extends EditRecord
{
    protected static string $resource = BlogPostResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->loadMissing('geoProfiles');

        $data['faq_items_vi'] = $this->record->geoProfile('vi')?->faq ?? [];
        $data['faq_items_en'] = $this->record->geoProfile('en')?->faq ?? [];

        foreach (config('app.supported_locales') as $locale) {
            $translation = $this->record->translations()->where('locale', $locale)->first();

            if ($translation) {
                $data['translations'][$locale] = $translation->only([
                    'title', 'slug', 'excerpt', 'body',
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

        // ── FAQ → geo_entity_profiles.faq (per locale) ───────────────────────
        $normalizeFaq = fn (array $items): array => collect($items)
            ->filter(fn (array $item): bool => filled($item['question'] ?? null))
            ->map(fn (array $item): array => [
                'question' => trim($item['question']),
                'answer'   => trim($item['answer'] ?? ''),
            ])
            ->values()
            ->toArray();

        foreach (['vi' => 'faq_items_vi', 'en' => 'faq_items_en'] as $locale => $field) {
            GeoEntityProfile::updateOrCreate(
                ['model_type' => $morphClass, 'model_id' => $modelId, 'locale' => $locale],
                ['faq' => $normalizeFaq($state[$field] ?? [])]
            );
        }

        $this->saveTranslations();
    }

    private function saveTranslations(): void
    {
        $record           = $this->getRecord();
        $translationsData = $this->data['translations'] ?? [];

        foreach (config('app.supported_locales') as $locale) {
            $localeData = $translationsData[$locale] ?? [];

            if (empty($localeData['title'])) {
                continue;
            }

            $record->translations()->updateOrCreate(
                ['locale' => $locale],
                collect($localeData)
                    ->only(['title', 'slug', 'excerpt', 'body'])
                    ->filter(fn ($v) => $v !== null && $v !== '')
                    ->toArray()
            );
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
