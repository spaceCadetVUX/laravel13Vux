<?php

namespace App\Filament\Resources;

use App\Enums\RedirectType;
use App\Filament\Resources\RedirectResource\Pages;
use App\Models\Seo\Redirect;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static \UnitEnum|string|null $navigationGroup = 'SEO & GEO';

    protected static ?string $navigationLabel = 'Redirects';

    // ── Form ──────────────────────────────────────────────────────────────────

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([

            Forms\Components\TextInput::make('from_path')
                ->label('From Path')
                ->required()
                ->placeholder('/old-slug')
                ->unique(table: Redirect::class, column: 'from_path', ignoreRecord: true)
                ->helperText('Must start with /. This is the path being redirected away from.'),

            Forms\Components\TextInput::make('to_path')
                ->label('To Path')
                ->required()
                ->placeholder('/products/new-slug')
                ->helperText('Destination path or full URL.'),

            Forms\Components\Select::make('type')
                ->label('Redirect Type')
                ->options([
                    RedirectType::Permanent->value => '301 — Permanent',
                    RedirectType::Temporary->value => '302 — Temporary',
                ])
                ->default(RedirectType::Permanent->value)
                ->required(),

            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true),

        ])->columns(2);
    }

    // ── Table ─────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('from_path')
                    ->label('From')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Path copied'),

                TextColumn::make('to_path')
                    ->label('To')
                    ->searchable()
                    ->limit(60),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (RedirectType $state): string => $state->value . ' ' . ($state === RedirectType::Permanent ? 'Permanent' : 'Temporary'))
                    ->color(fn (RedirectType $state): string => match ($state) {
                        RedirectType::Permanent => 'warning',
                        RedirectType::Temporary => 'info',
                    }),

                TextColumn::make('hits')
                    ->label('Hits')
                    ->numeric()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),

                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        RedirectType::Permanent->value => '301 Permanent',
                        RedirectType::Temporary->value => '302 Temporary',
                    ]),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('toggleActive')
                        ->label('Toggle active')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->action(function ($records): void {
                            foreach ($records as $record) {
                                $record->update(['is_active' => ! $record->is_active]);
                            }
                        })
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRedirects::route('/'),
            'create' => Pages\CreateRedirect::route('/create'),
            'edit'   => Pages\EditRedirect::route('/{record}/edit'),
        ];
    }
}
