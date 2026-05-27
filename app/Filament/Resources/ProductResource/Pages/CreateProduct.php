<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $vi = $data['translations']['vi'] ?? [];

        $data['name']              = $vi['name'] ?? null;
        $data['slug']              = $vi['slug'] ?? null;
        $data['short_description'] = $vi['short_description'] ?? null;
        $data['description']       = $vi['description'] ?? null;
        $data['price']             = $vi['price'] ?? null;
        $data['sale_price']        = $vi['sale_price'] ?? null;
        $data['currency']          = $vi['currency'] ?? 'VND';

        return $data;
    }

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
                    ->only(['name', 'slug', 'short_description', 'description', 'price', 'currency'])
                    ->filter(fn ($v) => $v !== null && $v !== '')
                    ->toArray()
            );
        }
    }
}
