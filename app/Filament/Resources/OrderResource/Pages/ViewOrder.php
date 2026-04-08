<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\OrderResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [

            // ── Update Status ─────────────────────────────────────────────────
            Action::make('updateStatus')
                ->label('Update Status')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->form([
                    Select::make('status')
                        ->label('New Status')
                        ->options(collect(OrderStatus::cases())->mapWithKeys(
                            fn (OrderStatus $case) => [$case->value => ucfirst($case->value)]
                        ))
                        ->default(fn () => $this->record->status->value)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->record->update(['status' => $data['status']]);
                    $this->refreshFormData(['status']);
                })
                ->modalHeading('Update Order Status')
                ->requiresConfirmation(false),

            // ── Mark as Paid ──────────────────────────────────────────────────
            Action::make('markAsPaid')
                ->label('Mark as Paid')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn () => $this->record->payment_status !== PaymentStatus::Paid)
                ->action(function (): void {
                    $this->record->update(['payment_status' => PaymentStatus::Paid->value]);
                    $this->refreshFormData(['payment_status']);
                })
                ->requiresConfirmation()
                ->modalHeading('Mark Order as Paid')
                ->modalDescription('Are you sure this order has been paid?'),

        ];
    }
}
