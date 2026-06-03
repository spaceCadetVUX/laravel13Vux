<?php

namespace App\Filament\Resources\ActivityLogResource\Pages;

use App\Filament\Resources\ActivityLogResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Spatie\Activitylog\Models\Activity;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clear_all')
                ->label('Xóa tất cả')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Xóa toàn bộ Activity Log?')
                ->modalDescription('Hành động này không thể hoàn tác. Tất cả log sẽ bị xóa vĩnh viễn.')
                ->modalSubmitActionLabel('Xóa tất cả')
                ->action(function (): void {
                    $count = Activity::query()->count();
                    Activity::query()->delete();
                    Notification::make()
                        ->title("Đã xóa {$count} activity logs")
                        ->success()
                        ->send();
                }),
        ];
    }
}
