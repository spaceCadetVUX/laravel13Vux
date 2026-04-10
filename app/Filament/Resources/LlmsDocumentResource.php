<?php

namespace App\Filament\Resources;

use App\Enums\LlmsScope;
use App\Filament\Resources\LlmsDocumentResource\Pages;
use App\Models\Seo\LlmsDocument;
use App\Services\Seo\LlmsGeneratorService;
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

class LlmsDocumentResource extends Resource
{
    protected static ?string $model = LlmsDocument::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected static \UnitEnum|string|null $navigationGroup = 'SEO & GEO';

    protected static ?string $navigationLabel = 'LLMs Documents';

    // No create/edit — seeded data only
    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Placeholder::make('info')
                ->content('LLMs documents are managed programmatically. Use the Regenerate action in the table.'),
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

                TextColumn::make('slug')
                    ->label('Slug')
                    ->copyable()
                    ->copyMessage('Slug copied')
                    ->color('gray'),

                TextColumn::make('scope')
                    ->label('Scope')
                    ->badge()
                    ->formatStateUsing(fn (LlmsScope $state): string => ucfirst($state->value))
                    ->color(fn (LlmsScope $state): string => match ($state) {
                        LlmsScope::Index => 'primary',
                        LlmsScope::Full  => 'info',
                    }),

                TextColumn::make('model_type')
                    ->label('Model Type')
                    ->placeholder('—')
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

                Tables\Filters\SelectFilter::make('scope')
                    ->options([
                        LlmsScope::Index->value => 'Index',
                        LlmsScope::Full->value  => 'Full',
                    ]),
            ])
            ->actions([
                Action::make('regenerate')
                    ->label('Regenerate')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Regenerate LLMs Document')
                    ->modalDescription('This will regenerate the .txt file for this document.')
                    ->action(function (LlmsDocument $record, LlmsGeneratorService $service): void {
                        $service->generateDocument($record);

                        Notification::make()
                            ->title('LLMs document regenerated: ' . $record->slug . '.txt')
                            ->success()
                            ->send();
                    }),

                Action::make('toggleActive')
                    ->label(fn (LlmsDocument $record): string => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (LlmsDocument $record): string => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (LlmsDocument $record): string => $record->is_active ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (LlmsDocument $record) => $record->update(['is_active' => ! $record->is_active])),
            ])
            ->bulkActions([
                BulkActionGroup::make([]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLlmsDocuments::route('/'),
        ];
    }
}
