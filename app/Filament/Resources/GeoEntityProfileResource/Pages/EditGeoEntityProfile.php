<?php

namespace App\Filament\Resources\GeoEntityProfileResource\Pages;

use App\Filament\Resources\GeoEntityProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGeoEntityProfile extends EditRecord
{
    protected static string $resource = GeoEntityProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
