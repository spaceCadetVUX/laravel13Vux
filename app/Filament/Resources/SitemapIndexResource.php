<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SitemapIndexResource\Pages;
use App\Models\Seo\SitemapIndex;
use App\Services\Seo\SitemapService;
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

    protected static \UnitEnum|string|null $navigationGroup = 'Setting';

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
                    ->url(fn (SitemapIndex $record): string => url($record->filename))
                    ->openUrlInNewTab()
                    ->color('primary')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->iconPosition(\Filament\Support\Enums\IconPosition::After)
                    ->copyable()
                    ->copyMessage('URL copied'),

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
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-globe-alt')
                    ->color('gray')
                    ->url(fn (SitemapIndex $record): string => url($record->filename))
                    ->openUrlInNewTab(),

                Action::make('regenerate')
                    ->label('Regenerate')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Regenerate Sitemap')
                    ->modalDescription('This will regenerate the sitemap file synchronously. The table entry count and last_generated_at will be updated.')
                    ->action(function (SitemapIndex $record, SitemapService $service): void {
                        $service->generateChild($record);

                        Notification::make()
                            ->title('Sitemap regenerated: ' . $record->filename)
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
