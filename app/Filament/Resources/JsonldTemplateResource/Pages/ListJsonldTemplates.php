<?php

namespace App\Filament\Resources\JsonldTemplateResource\Pages;

use App\Filament\Resources\JsonldTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJsonldTemplates extends ListRecords
{
    protected static string $resource = JsonldTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
