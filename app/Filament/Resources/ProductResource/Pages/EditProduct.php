<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('toggleActive')
                ->label(fn () => $this->record->is_active ? 'Hide product' : 'Show product')
                ->icon(fn () => $this->record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                ->color(fn () => $this->record->is_active ? 'warning' : 'success')
                ->requiresConfirmation()
                ->modalHeading(fn () => $this->record->is_active ? 'Hide this product?' : 'Show this product?')
                ->modalDescription(fn () => $this->record->is_active
                    ? 'Product will be hidden from storefront immediately.'
                    : 'Product will be visible on storefront immediately.')
                ->action(function () {
                    $this->record->update(['is_active' => ! $this->record->is_active]);
                    $this->refreshFormData(['is_active']);
                }),

            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        foreach (config('app.supported_locales') as $locale) {
            $translation = $record->translations()->where('locale', $locale)->first();

            if ($translation) {
                $data['translations'][$locale] = $translation->only([
                    'name', 'slug', 'short_description', 'description',
                    'price', 'currency', 'meta_title', 'meta_description',
                ]);
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->saveTranslations();
    }

    private function saveTranslations(): void
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
