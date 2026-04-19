<?php

namespace App\Filament\Resources\ReviewResource\Pages;

use App\Filament\Resources\ReviewResource;
use Filament\Resources\Pages\ListRecords;

class ListReviews extends ListRecords
{
    protected static string $resource = ReviewResource::class;

    // No Create button — reviews must come from real users via frontend.
    // Admin can only approve / reject / delete.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
