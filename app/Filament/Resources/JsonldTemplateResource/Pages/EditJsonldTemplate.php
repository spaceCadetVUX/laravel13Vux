<?php

namespace App\Filament\Resources\JsonldTemplateResource\Pages;

use App\Filament\Resources\JsonldTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJsonldTemplate extends EditRecord
{
    protected static string $resource = JsonldTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
