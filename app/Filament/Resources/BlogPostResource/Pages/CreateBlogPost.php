<?php

namespace App\Filament\Resources\BlogPostResource\Pages;

use App\Filament\Resources\BlogPostResource;
use App\Models\Seo\GeoEntityProfile;
use App\Models\Seo\SeoMeta;
use Filament\Resources\Pages\CreateRecord;

class CreateBlogPost extends CreateRecord
{
    protected static string $resource = BlogPostResource::class;

    /**
     * Sau khi tạo bài viết, lưu SEO meta nếu admin đã điền.
     * Chỉ tạo row khi có ít nhất một trường được điền.
     */
    protected function afterCreate(): void
    {
        $state      = $this->data;
        $morphClass = $this->record->getMorphClass();
        $modelId    = $this->record->getKey();

        // ── SEO meta — only create row if at least one field is filled ────────
        $hasAnySeo = filled($state['seo_meta_title'] ?? null)
            || filled($state['seo_meta_description'] ?? null)
            || filled($state['seo_og_image'] ?? null)
            || filled($state['seo_canonical_url'] ?? null);

        if ($hasAnySeo) {
            $ogImage = $state['seo_og_image'] ?? null;
            if (is_array($ogImage)) {
                $ogImage = $ogImage[0] ?? null;
            }

            SeoMeta::create([
                'model_type'       => $morphClass,
                'model_id'         => $modelId,
                'meta_title'       => $state['seo_meta_title']       ?? null,
                'meta_description' => $state['seo_meta_description'] ?? null,
                'og_image'         => $ogImage,
                'canonical_url'    => $state['seo_canonical_url']    ?? null,
                'robots'           => $state['seo_robots']           ?? 'index,follow',
            ]);
        }

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
                    ->only(['title', 'slug', 'excerpt', 'body', 'meta_title', 'meta_description'])
                    ->filter(fn ($v) => $v !== null && $v !== '')
                    ->toArray()
            );
        }
    }
}
