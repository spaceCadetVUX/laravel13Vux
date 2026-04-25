<?php

namespace App\Filament\Resources;

use App\Enums\LlmsScope;
use App\Filament\Resources\LlmsDocumentResource\Pages;
use App\Filament\Resources\LlmsDocumentResource\RelationManagers\EntriesRelationManager;
use App\Models\Seo\LlmsDocument;
use App\Services\Seo\LlmsGeneratorService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LlmsDocumentResource extends Resource
{
    protected static ?string $model = LlmsDocument::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected static \UnitEnum|string|null $navigationGroup = 'Setting';

    protected static ?string $navigationLabel = 'LLMs Documents';

    // ── Infolist ──────────────────────────────────────────────────────────────

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Document Details')
                ->schema([
                    TextEntry::make('name')
                        ->label('Name'),

                    TextEntry::make('slug')
                        ->label('Slug')
                        ->copyable(),

                    TextEntry::make('scope')
                        ->label('Scope')
                        ->badge()
                        ->formatStateUsing(fn (LlmsScope $state): string => ucfirst($state->value))
                        ->color(fn (LlmsScope $state): string => match ($state) {
                            LlmsScope::Index => 'primary',
                            LlmsScope::Full  => 'info',
                        }),

                    TextEntry::make('model_type')
                        ->label('Model Type')
                        ->placeholder('—'),

                    TextEntry::make('title')
                        ->label('Title')
                        ->placeholder('—'),

                    IconEntry::make('is_active')
                        ->label('Active')
                        ->boolean()
                        ->trueColor('success')
                        ->falseColor('danger'),

                    TextEntry::make('description')
                        ->label('Description')
                        ->placeholder('—')
                        ->columnSpanFull(),

                    TextEntry::make('entry_count')
                        ->label('Entries')
                        ->numeric(),

                    TextEntry::make('last_generated_at')
                        ->label('Last Generated')
                        ->dateTime()
                        ->placeholder('Never'),
                ])
                ->columns(3),
        ]);
    }

    // ── Form (not used — documents are seeded) ────────────────────────────────

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    // ── Table ─────────────────────────────────────────────────────────────────

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
                ViewAction::make(),

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

    public static function getRelationManagers(): array
    {
        return [
            EntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLlmsDocuments::route('/'),
            'view'  => Pages\ViewLlmsDocument::route('/{record}'),
        ];
    }
}
