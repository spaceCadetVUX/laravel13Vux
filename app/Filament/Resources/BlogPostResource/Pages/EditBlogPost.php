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
        $this->record->loadMissing('seoMeta', 'geoProfile');

        // ── SEO meta ──────────────────────────────────────────────────────────
        $seo = $this->record->seoMeta;

        $data['seo_meta_title']       = $seo?->meta_title;
        $data['seo_meta_description'] = $seo?->meta_description;
        // Default to the post's featured image when no custom OG image is saved.
        $data['seo_og_image']         = $seo?->og_image ?? $this->record->featured_image;
        $data['seo_canonical_url']    = $seo?->canonical_url;
        $data['seo_robots']           = $seo?->robots ?? 'index,follow';

        // ── FAQ from geo_entity_profiles ──────────────────────────────────────
        $data['faq_items'] = $this->record->geoProfile?->faq ?? [];

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
            ['model_type' => $morphClass, 'model_id' => $modelId],
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
            ['model_type' => $morphClass, 'model_id' => $modelId],
            ['faq' => $faqItems]
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
