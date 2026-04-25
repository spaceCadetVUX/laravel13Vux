<?php

namespace App\Filament\Resources\BusinessProfileResource\Pages;

use App\Filament\Resources\BusinessProfileResource;
use App\Models\BusinessProfile;
use Filament\Resources\Pages\EditRecord;

class EditBusinessProfile extends EditRecord
{
    protected static string $resource = BusinessProfileResource::class;

    public function mount(int|string $record = null): void
    {
        parent::mount(BusinessProfile::instance()->getKey());
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
