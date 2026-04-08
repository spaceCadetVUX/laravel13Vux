<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SitemapIndexResource\Pages;
use App\Models\Seo\SitemapIndex;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SitemapIndexResource extends Resource
{
    protected static ?string $model = SitemapIndex::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-map';

    protected static \UnitEnum|string|null $navigationGroup = 'SEO & GEO';

    protected static ?string $navigationLabel = 'Sitemaps';

    // No create/edit — seeded data only
    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Placeholder::make('info')
                ->content('Sitemap indexes are managed programmatically. Use the Regenerate action in the table.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('filename')
                    ->label('File')
                    ->copyable()
                    ->copyMessage('Filename copied')
                    ->color('gray'),

                TextColumn::make('entry_count')
                    ->label('Entries')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('last_generated_at')
                    ->label('Last Generated')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Action::make('regenerate')
                    ->label('Regenerate')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Regenerate Sitemap')
                    ->modalDescription('This will regenerate the sitemap file. The actual generation logic runs in S36.')
                    ->action(function (SitemapIndex $record): void {
                        // SitemapService::regenerate($record) — wired in S36
                        $record->touch('last_generated_at');

                        Notification::make()
                            ->title('Sitemap queued for regeneration')
                            ->success()
                            ->send();
                    }),

                Action::make('toggleActive')
                    ->label(fn (SitemapIndex $record): string => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (SitemapIndex $record): string => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (SitemapIndex $record): string => $record->is_active ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (SitemapIndex $record) => $record->update(['is_active' => ! $record->is_active])),
            ])
            ->bulkActions([
                BulkActionGroup::make([]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSitemapIndexes::route('/'),
        ];
    }
}
