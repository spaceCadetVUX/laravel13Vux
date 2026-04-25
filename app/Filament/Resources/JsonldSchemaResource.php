<?php

namespace App\Filament\Resources;

use App\Enums\JsonldSchemaType;
use App\Filament\Resources\JsonldSchemaResource\Pages;
use App\Models\Seo\JsonldSchema;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JsonldSchemaResource extends Resource
{
    protected static ?string $model = JsonldSchema::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-code-bracket';

    protected static \UnitEnum|string|null $navigationGroup = 'SEO & GEO';

    protected static ?string $navigationLabel = 'JSON-LD Schemas';

    protected static bool $shouldRegisterNavigation = false;

    // ── Form ──────────────────────────────────────────────────────────────────

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([

            Forms\Components\TextInput::make('model_type')
                ->label('Model Type')
                ->disabled()
                ->dehydrated(false),

            Forms\Components\TextInput::make('model_id')
                ->label('Model ID')
                ->disabled()
                ->dehydrated(false),

            Forms\Components\Select::make('schema_type')
                ->label('Schema Type')
                ->options(collect(JsonldSchemaType::cases())->mapWithKeys(
                    fn (JsonldSchemaType $case) => [$case->value => $case->value]
                ))
                ->required(),

            Forms\Components\TextInput::make('label')
                ->label('Label')
                ->required(),

            Forms\Components\TextInput::make('sort_order')
                ->label('Sort Order')
                ->numeric()
                ->default(0),

            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true),

            Forms\Components\Toggle::make('is_auto_generated')
                ->label('Auto Generated')
                ->helperText('When OFF, Observer will never overwrite this payload (manual override).')
                ->default(true),

            Forms\Components\Textarea::make('payload')
                ->label('Payload (JSON)')
                ->rows(25)
                ->formatStateUsing(fn ($state) => is_array($state)
                    ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : $state
                )
                ->dehydrateStateUsing(fn ($state) => is_string($state) && filled($state)
                    ? json_decode($state, true) ?? $state
                    : $state
                )
                ->helperText('Set "Auto Generated" to OFF to lock this payload from Observer overwrites.')
                ->columnSpanFull(),

        ])->columns(2);
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

                TextColumn::make('schema_type')
                    ->label('Schema Type')
                    ->badge()
                    ->color('info')
                    ->searchable(),

                TextColumn::make('label')
                    ->searchable()
                    ->limit(40),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),

                IconColumn::make('is_auto_generated')
                    ->label('Auto')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->trueIcon('heroicon-o-cpu-chip')
                    ->falseIcon('heroicon-o-pencil-square'),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('model_type')
                    ->label('Model Type')
                    ->options(fn () => JsonldSchema::query()
                        ->distinct()
                        ->pluck('model_type', 'model_type')
                        ->toArray()
                    ),

                Tables\Filters\SelectFilter::make('schema_type')
                    ->label('Schema Type')
                    ->options(collect(JsonldSchemaType::cases())->mapWithKeys(
                        fn (JsonldSchemaType $case) => [$case->value => $case->value]
                    )),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),

                Tables\Filters\TernaryFilter::make('is_auto_generated')
                    ->label('Auto Generated'),
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
            'index' => Pages\ListJsonldSchemas::route('/'),
            'edit'  => Pages\EditJsonldSchema::route('/{record}/edit'),
        ];
    }
}
