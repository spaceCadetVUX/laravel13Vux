<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\FilterGroup;
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
                    'price', 'sale_price', 'currency',
                ]);
            }
        }

        // Pre-populate per-group CheckboxLists
        $selectedIds = $record->filterValues()->pluck('filter_values.id')->toArray();
        foreach (FilterGroup::active()->with('activeValues')->get() as $group) {
            $groupValueIds = $group->activeValues->pluck('id')->toArray();
            $data["filter_group_{$group->id}"] = array_values(
                array_intersect($selectedIds, $groupValueIds)
            );
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $vi = $data['translations']['vi'] ?? [];

        if (filled($vi['name'] ?? null)) {
            $data['name']              = $vi['name'];
            $data['slug']              = $vi['slug'] ?? $data['slug'] ?? null;
            $data['short_description'] = $vi['short_description'] ?? null;
            $data['description']       = $vi['description'] ?? null;
            $data['price']             = $vi['price'] ?? $data['price'] ?? 0;
            $data['sale_price']        = $vi['sale_price'] ?? null;
            $data['currency']          = $vi['currency'] ?? 'VND';
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->saveTranslations();
        $this->saveFilterValues();
    }

    private function saveFilterValues(): void
    {
        $allSelectedIds = [];

        foreach (FilterGroup::active()->pluck('id') as $groupId) {
            $selected = $this->data["filter_group_{$groupId}"] ?? [];
            array_push($allSelectedIds, ...$selected);
        }

        $this->getRecord()->filterValues()->sync($allSelectedIds);
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

            $numericFields = ['price', 'sale_price'];

            $updateData = collect($localeData)
                ->only(['name', 'slug', 'short_description', 'description', 'price', 'sale_price', 'currency'])
                ->map(fn ($v, $k) => (in_array($k, $numericFields) && $v === '') ? null : $v)
                ->filter(fn ($v, $k) => in_array($k, $numericFields) ? $v !== '' : ($v !== null && $v !== ''))
                ->toArray();

            $record->translations()->updateOrCreate(['locale' => $locale], $updateData);
        }
    }
}
