<?php

namespace App\Filament\Resources;

use App\Enums\JsonldSchemaType;
use App\Filament\Resources\JsonldTemplateResource\Pages;
use App\Models\Seo\JsonldTemplate;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JsonldTemplateResource extends Resource
{
    protected static ?string $model = JsonldTemplate::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-code-bracket';

    protected static \UnitEnum|string|null $navigationGroup = 'Setting';

    protected static ?string $navigationLabel = 'JSON-LD Templates';

    protected static bool $shouldRegisterNavigation = false;

    // ── Form ──────────────────────────────────────────────────────────────────

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([

            Forms\Components\Select::make('schema_type')
                ->label('Schema Type')
                ->options(collect(JsonldSchemaType::cases())->mapWithKeys(
                    fn (JsonldSchemaType $case) => [$case->value => $case->value]
                ))
                ->disabled(),

            Forms\Components\TextInput::make('label')
                ->label('Label')
                ->disabled(),

            Forms\Components\Toggle::make('is_auto_generated')
                ->label('Auto Generated')
                ->helperText('Managed via code. When ON, the Observer fills payload from this template automatically.')
                ->disabled()
                ->columnSpanFull(),

            Forms\Components\Textarea::make('template')
                ->label('Template (JSON)')
                ->rows(20)
                ->formatStateUsing(fn ($state) => is_array($state)
                    ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : $state
                )
                ->disabled()
                ->columnSpanFull(),

            Forms\Components\KeyValue::make('placeholders')
                ->label('Placeholders')
                ->keyLabel('Placeholder Key')
                ->valueLabel('Description / Example')
                ->disabled()
                ->helperText('Document each {{placeholder}} used in the template above.')
                ->columnSpanFull(),

        ])->columns(2);
    }

    // ── Table ─────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('schema_type')
                    ->label('Schema Type')
                    ->badge()
                    ->color('primary')
                    ->searchable(),

                TextColumn::make('label')
                    ->searchable(),

                IconColumn::make('is_auto_generated')
                    ->label('Auto Generated')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('warning'),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJsonldTemplates::route('/'),
            'view'  => Pages\ViewJsonldTemplate::route('/{record}'),
        ];
    }
}
