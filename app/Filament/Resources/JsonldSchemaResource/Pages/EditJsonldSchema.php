<?php

namespace App\Filament\Resources\JsonldSchemaResource\Pages;

use App\Filament\Resources\JsonldSchemaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJsonldSchema extends EditRecord
{
    protected static string $resource = JsonldSchemaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
