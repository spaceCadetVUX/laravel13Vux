<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewResource\Pages;
use App\Models\Review;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-star';

    protected static \UnitEnum|string|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 5;

    public static function getNavigationBadge(): ?string
    {
        return (string) Review::where('is_approved', false)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return Review::where('is_approved', false)->exists() ? 'warning' : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('product_id')
                ->label('Product')
                ->relationship('product', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->columnSpanFull(),

            Forms\Components\TextInput::make('author')
                ->required()
                ->maxLength(100),

            Forms\Components\Select::make('rating')
                ->label('Rating')
                ->options([
                    1 => '⭐ 1 — Very Bad',
                    2 => '⭐⭐ 2 — Bad',
                    3 => '⭐⭐⭐ 3 — OK',
                    4 => '⭐⭐⭐⭐ 4 — Good',
                    5 => '⭐⭐⭐⭐⭐ 5 — Excellent',
                ])
                ->required()
                ->native(false),

            Forms\Components\TextInput::make('title')
                ->label('Review Title')
                ->maxLength(255)
                ->columnSpanFull(),

            Forms\Components\Textarea::make('content')
                ->label('Content')
                ->rows(5)
                ->required()
                ->columnSpanFull(),

            Forms\Components\Toggle::make('is_approved')
                ->label('Approved')
                ->helperText('Only approved reviews are shown publicly and counted in AggregateRating schema.')
                ->default(false),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('author')
                    ->searchable(),

                Tables\Columns\TextColumn::make('rating')
                    ->formatStateUsing(fn (int $state): string => str_repeat('⭐', $state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->placeholder('—')
                    ->limit(40),

                Tables\Columns\TextColumn::make('content')
                    ->limit(60)
                    ->wrap(),

                Tables\Columns\IconColumn::make('is_approved')
                    ->label('Approved')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('warning'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_approved')
                    ->label('Approved')
                    ->trueLabel('Approved only')
                    ->falseLabel('Pending only')
                    ->placeholder('All'),

                Tables\Filters\SelectFilter::make('rating')
                    ->options([
                        1 => '⭐ 1',
                        2 => '⭐⭐ 2',
                        3 => '⭐⭐⭐ 3',
                        4 => '⭐⭐⭐⭐ 4',
                        5 => '⭐⭐⭐⭐⭐ 5',
                    ]),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Review $record): bool => ! $record->is_approved)
                    ->requiresConfirmation()
                    ->action(function (Review $record): void {
                        $record->update(['is_approved' => true]);

                        Notification::make()
                            ->title('Review approved')
                            ->success()
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->visible(fn (Review $record): bool => $record->is_approved)
                    ->requiresConfirmation()
                    ->action(function (Review $record): void {
                        $record->update(['is_approved' => false]);

                        Notification::make()
                            ->title('Review rejected')
                            ->warning()
                            ->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('approve_selected')
                        ->label('Approve selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_approved' => true])),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviews::route('/'),
            'edit'  => Pages\EditReview::route('/{record}/edit'),
        ];
    }
}
