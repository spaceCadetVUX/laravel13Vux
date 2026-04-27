<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function afterCreate(): void
    {
        $record           = $this->getRecord();
        $translationsData = $this->data['translations'] ?? [];

        foreach (config('app.supported_locales') as $locale) {
            $localeData = $translationsData[$locale] ?? [];

            if (empty($localeData['name'])) {
                continue;
            }

            $record->translations()->updateOrCreate(
                ['locale' => $locale],
                collect($localeData)
                    ->only(['name', 'slug', 'short_description', 'description', 'price', 'currency', 'meta_title', 'meta_description'])
                    ->filter(fn ($v) => $v !== null && $v !== '')
                    ->toArray()
            );
        }
    }
}
