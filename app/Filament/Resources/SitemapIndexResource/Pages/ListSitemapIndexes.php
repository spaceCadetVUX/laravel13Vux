<?php

namespace App\Filament\Resources\SitemapIndexResource\Pages;

use App\Filament\Resources\SitemapIndexResource;
use Filament\Resources\Pages\ListRecords;

class ListSitemapIndexes extends ListRecords
{
    protected static string $resource = SitemapIndexResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create button — seeded data only
        ];
    }
}
