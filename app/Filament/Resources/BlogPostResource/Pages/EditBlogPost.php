<?php

namespace App\Filament\Resources\BlogPostResource\Pages;

use App\Filament\Resources\BlogPostResource;
use App\Models\Seo\GeoEntityProfile;
use App\Models\Seo\SeoMeta;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBlogPost extends EditRecord
{
    protected static string $resource = BlogPostResource::class;

    // ── Load SEO meta vào form ────────────────────────────────────────────────

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->loadMissing('seoMetas', 'geoProfiles');

        // ── SEO meta ──────────────────────────────────────────────────────────
        $seo = $this->record->seoMeta('vi');

        $data['seo_meta_title']       = $seo?->meta_title;
        $data['seo_meta_description'] = $seo?->meta_description;
        // Default to the post's featured image when no custom OG image is saved.
        $data['seo_og_image']         = $seo?->og_image ?? $this->record->featured_image;
        $data['seo_canonical_url']    = $seo?->canonical_url;
        $data['seo_robots']           = $seo?->robots ?? 'index,follow';

        // ── FAQ from geo_entity_profiles ──────────────────────────────────────
        $data['faq_items'] = $this->record->geoProfile('vi')?->faq ?? [];

        // ── Translations ──────────────────────────────────────────────────────
        foreach (config('app.supported_locales') as $locale) {
            $translation = $this->record->translations()->where('locale', $locale)->first();

            if ($translation) {
                $data['translations'][$locale] = $translation->only([
                    'title', 'slug', 'excerpt', 'body', 'meta_title', 'meta_description',
                ]);
            }
        }

        return $data;
    }

    // ── Lưu SEO meta + FAQ sau khi save bài viết ─────────────────────────────

    protected function afterSave(): void
    {
        $state      = $this->data;
        $morphClass = $this->record->getMorphClass();
        $modelId    = $this->record->getKey();

        // ── SEO meta ──────────────────────────────────────────────────────────
        // FileUpload returns an array internally even for single files —
        // flatten to a string path before persisting.
        $ogImage = $state['seo_og_image'] ?? null;
        if (is_array($ogImage)) {
            $ogImage = $ogImage[0] ?? null;
        }

        SeoMeta::updateOrCreate(
            ['model_type' => $morphClass, 'model_id' => $modelId, 'locale' => 'vi'],
            [
                'meta_title'       => $state['seo_meta_title']       ?? null,
                'meta_description' => $state['seo_meta_description'] ?? null,
                'og_image'         => $ogImage,
                'canonical_url'    => $state['seo_canonical_url']    ?? null,
                'robots'           => $state['seo_robots']           ?? 'index,follow',
            ]
        );

        // ── FAQ → geo_entity_profiles.faq ────────────────────────────────────
        // Strip incomplete rows (question required), preserve order.
        $faqItems = collect($state['faq_items'] ?? [])
            ->filter(fn (array $item): bool => filled($item['question'] ?? null))
            ->map(fn (array $item): array => [
                'question' => trim($item['question']),
                'answer'   => trim($item['answer'] ?? ''),
            ])
            ->values()
            ->toArray();

        GeoEntityProfile::updateOrCreate(
            ['model_type' => $morphClass, 'model_id' => $modelId, 'locale' => 'vi'],
            ['faq' => $faqItems]
        );

        // ── Translations ──────────────────────────────────────────────────────
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
                    ->only(['title', 'slug', 'excerpt', 'body', 'meta_title', 'meta_description'])
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
