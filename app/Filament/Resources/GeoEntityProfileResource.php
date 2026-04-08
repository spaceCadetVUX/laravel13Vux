<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GeoEntityProfileResource\Pages;
use App\Models\Seo\GeoEntityProfile;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GeoEntityProfileResource extends Resource
{
    protected static ?string $model = GeoEntityProfile::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static \UnitEnum|string|null $navigationGroup = 'SEO & GEO';

    protected static ?string $navigationLabel = 'GEO Profiles';

    // ── Form ──────────────────────────────────────────────────────────────────

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Tabs::make('Tabs')
                ->tabs([

                    // ── Tab 1: Entity ─────────────────────────────────────────
                    Tab::make('Entity & Context')
                        ->schema([
                            Forms\Components\TextInput::make('model_type')
                                ->label('Model Type')
                                ->disabled()
                                ->dehydrated(false),

                            Forms\Components\TextInput::make('model_id')
                                ->label('Model ID')
                                ->disabled()
                                ->dehydrated(false),

                            Forms\Components\Textarea::make('ai_summary')
                                ->label('AI Summary')
                                ->rows(4)
                                ->helperText('2-3 sentences, plain text, no marketing language.')
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('target_audience')
                                ->label('Target Audience')
                                ->placeholder('e.g. tech-savvy shoppers aged 25-40')
                                ->columnSpanFull(),

                            Forms\Components\Textarea::make('use_cases')
                                ->label('Use Cases')
                                ->rows(3)
                                ->columnSpanFull(),

                            Forms\Components\Textarea::make('llm_context_hint')
                                ->label('LLM Context Hint')
                                ->rows(3)
                                ->helperText('Extra context to help AI understand this entity correctly.')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    // ── Tab 2: Key Facts ──────────────────────────────────────
                    Tab::make('Key Facts')
                        ->schema([
                            Forms\Components\KeyValue::make('key_facts')
                                ->label('Key Facts')
                                ->keyLabel('Fact')
                                ->valueLabel('Value')
                                ->reorderable()
                                ->columnSpanFull(),
                        ]),

                    // ── Tab 3: FAQ ────────────────────────────────────────────
                    Tab::make('FAQ')
                        ->schema([
                            Forms\Components\Repeater::make('faq')
                                ->label('Frequently Asked Questions')
                                ->schema([
                                    Forms\Components\TextInput::make('question')
                                        ->label('Question')
                                        ->required()
                                        ->columnSpanFull(),

                                    Forms\Components\Textarea::make('answer')
                                        ->label('Answer')
                                        ->rows(3)
                                        ->required()
                                        ->columnSpanFull(),
                                ])
                                ->addActionLabel('Add FAQ')
                                ->reorderable()
                                ->collapsible()
                                ->columnSpanFull(),
                        ]),

                ])
                ->columnSpanFull(),
        ]);
    }

    // ── Table ─────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('model_type')
                    ->label('Model')
                    ->badge()
                    ->color('primary')
                    ->searchable(),

                TextColumn::make('model_id')
                    ->label('Model ID')
                    ->formatStateUsing(fn ($state) => is_string($state) && strlen($state) > 12
                        ? strtoupper(substr($state, 0, 8)) . '…'
                        : $state
                    )
                    ->copyable(),

                IconColumn::make('has_summary')
                    ->label('Summary')
                    ->state(fn (GeoEntityProfile $record): bool => ! empty($record->ai_summary))
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray'),

                TextColumn::make('key_facts_count')
                    ->label('Key Facts')
                    ->state(fn (GeoEntityProfile $record): string => count($record->key_facts ?? []) . ' facts')
                    ->badge()
                    ->color(fn (GeoEntityProfile $record): string => count($record->key_facts ?? []) > 0 ? 'success' : 'gray'),

                TextColumn::make('faq_count')
                    ->label('FAQ')
                    ->state(fn (GeoEntityProfile $record): string => count($record->faq ?? []) . ' Q&A')
                    ->badge()
                    ->color(fn (GeoEntityProfile $record): string => count($record->faq ?? []) > 0 ? 'success' : 'gray'),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('model_type')
                    ->label('Model Type')
                    ->options(fn () => GeoEntityProfile::query()
                        ->distinct()
                        ->pluck('model_type', 'model_type')
                        ->toArray()
                    ),
            ])
            ->actions([
                EditAction::make(),
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
            'index' => Pages\ListGeoEntityProfiles::route('/'),
            'edit'  => Pages\EditGeoEntityProfile::route('/{record}/edit'),
        ];
    }
}
