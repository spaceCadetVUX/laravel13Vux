<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static \UnitEnum|string|null $navigationGroup = 'Commerce';

    public static function getNavigationBadge(): ?string
    {
        return (string) Order::where('status', OrderStatus::Pending->value)->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    // ── Infolist ──────────────────────────────────────────────────────────────

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([

            // ── Customer Info ─────────────────────────────────────────────────
            Section::make('Customer')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('user.name')
                            ->label('Name')
                            ->placeholder('—'),

                        TextEntry::make('user.email')
                            ->label('Email')
                            ->placeholder('—'),

                        TextEntry::make('user.phone')
                            ->label('Phone')
                            ->placeholder('—'),
                    ]),
                ]),

            // ── Shipping Address ──────────────────────────────────────────────
            Section::make('Shipping Address')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('shipping_address.full_name')
                            ->label('Full Name'),

                        TextEntry::make('shipping_address.phone')
                            ->label('Phone'),

                        TextEntry::make('shipping_address.address_line')
                            ->label('Address')
                            ->columnSpan(2),

                        TextEntry::make('shipping_address.city')
                            ->label('City'),

                        TextEntry::make('shipping_address.province')
                            ->label('Province'),
                    ]),
                ]),

            // ── Order Items ───────────────────────────────────────────────────
            Section::make('Items')
                ->schema([
                    RepeatableEntry::make('items')
                        ->schema([
                            TextEntry::make('product_name')
                                ->label('Product'),

                            TextEntry::make('product_sku')
                                ->label('SKU'),

                            TextEntry::make('quantity')
                                ->label('Qty'),

                            TextEntry::make('unit_price')
                                ->label('Unit Price')
                                ->money('VND'),

                            TextEntry::make('subtotal')
                                ->label('Subtotal')
                                ->money('VND'),
                        ])
                        ->columns(5),
                ]),

            // ── Order Totals ──────────────────────────────────────────────────
            Section::make('Order Summary')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('total_amount')
                            ->label('Total')
                            ->money('VND'),

                        TextEntry::make('payment_status')
                            ->label('Payment')
                            ->badge()
                            ->color(fn (PaymentStatus $state) => match ($state) {
                                PaymentStatus::Unpaid   => 'warning',
                                PaymentStatus::Paid     => 'success',
                                PaymentStatus::Refunded => 'info',
                            }),

                        TextEntry::make('payment_method')
                            ->label('Method')
                            ->placeholder('—'),
                    ]),

                    TextEntry::make('note')
                        ->label('Note')
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),

        ]);
    }

    // ── Table ─────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('items'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('Order ID')
                    ->formatStateUsing(fn (string $state): string => strtoupper(substr($state, 0, 8)))
                    ->copyable()
                    ->copyMessage('UUID copied')
                    ->searchable(),

                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (OrderStatus $state) => match ($state) {
                        OrderStatus::Pending    => 'warning',
                        OrderStatus::Processing => 'info',
                        OrderStatus::Shipped    => 'primary',
                        OrderStatus::Delivered  => 'success',
                        OrderStatus::Cancelled  => 'danger',
                    }),

                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->color(fn (PaymentStatus $state) => match ($state) {
                        PaymentStatus::Unpaid   => 'warning',
                        PaymentStatus::Paid     => 'success',
                        PaymentStatus::Refunded => 'info',
                    }),

                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('VND')
                    ->sortable(),

                TextColumn::make('items_count')
                    ->label('Items')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(OrderStatus::cases())->mapWithKeys(
                        fn (OrderStatus $case) => [$case->value => ucfirst($case->value)]
                    )),

                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Payment Status')
                    ->options(collect(PaymentStatus::cases())->mapWithKeys(
                        fn (PaymentStatus $case) => [$case->value => ucfirst($case->value)]
                    )),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('From'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'],  fn ($q) => $q->whereDate('created_at', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('created_at', '<=', $data['until']));
                    }),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view'  => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
