<?php

namespace App\Filament\Resources\LlmsDocumentResource\Pages;

use App\Filament\Resources\LlmsDocumentResource;
use Filament\Resources\Pages\ListRecords;

class ListLlmsDocuments extends ListRecords
{
    protected static string $resource = LlmsDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create button — seeded data only
        ];
    }
}
