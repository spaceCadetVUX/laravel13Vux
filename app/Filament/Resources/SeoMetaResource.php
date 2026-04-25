<?php

namespace App\Filament\Resources;

use App\Enums\OgType;
use App\Filament\Resources\SeoMetaResource\Pages;
use App\Models\Seo\SeoMeta;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SeoMetaResource extends Resource
{
    protected static ?string $model = SeoMeta::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static \UnitEnum|string|null $navigationGroup = 'Setting';

    protected static ?string $navigationLabel = 'SEO Meta';

    protected static bool $shouldRegisterNavigation = false;

    // ── Form ──────────────────────────────────────────────────────────────────

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Tabs::make('Tabs')
                ->tabs([

                    // ── Tab 1: Basic SEO ──────────────────────────────────────
                    Tab::make('Basic SEO')
                        ->schema([
                            Forms\Components\TextInput::make('model_type')
                                ->label('Model Type')
                                ->disabled()
                                ->dehydrated(false),

                            Forms\Components\TextInput::make('model_id')
                                ->label('Model ID')
                                ->disabled()
                                ->dehydrated(false),

                            Forms\Components\TextInput::make('meta_title')
                                ->label('Meta Title')
                                ->maxLength(160)
                                ->live(debounce: 300)
                                ->suffix(fn ($state) => strlen($state ?? '') . ' / 160')
                                ->columnSpanFull(),

                            Forms\Components\Textarea::make('meta_description')
                                ->label('Meta Description')
                                ->maxLength(320)
                                ->rows(3)
                                ->live(debounce: 300)
                                ->hint(fn ($state) => strlen($state ?? '') . ' / 320')
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('meta_keywords')
                                ->label('Meta Keywords')
                                ->placeholder('keyword1, keyword2, keyword3')
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('canonical_url')
                                ->label('Canonical URL')
                                ->url()
                                ->columnSpanFull(),

                            Forms\Components\Select::make('robots')
                                ->label('Robots')
                                ->options([
                                    'index, follow'     => 'index, follow',
                                    'noindex, nofollow' => 'noindex, nofollow',
                                    'noindex, follow'   => 'noindex, follow',
                                ])
                                ->default('index, follow'),
                        ])
                        ->columns(2),

                    // ── Tab 2: Open Graph ─────────────────────────────────────
                    Tab::make('Open Graph')
                        ->schema([
                            Forms\Components\TextInput::make('og_title')
                                ->label('OG Title')
                                ->maxLength(160)
                                ->live(debounce: 300)
                                ->suffix(fn ($state) => strlen($state ?? '') . ' / 160')
                                ->columnSpanFull(),

                            Forms\Components\Textarea::make('og_description')
                                ->label('OG Description')
                                ->rows(3)
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('og_image')
                                ->label('OG Image URL')
                                ->url()
                                ->placeholder('https://example.com/image.jpg')
                                ->columnSpanFull(),

                            Forms\Components\Select::make('og_type')
                                ->label('OG Type')
                                ->options(collect(OgType::cases())->mapWithKeys(
                                    fn (OgType $case) => [$case->value => ucfirst($case->value)]
                                ))
                                ->default(OgType::Website->value),
                        ])
                        ->columns(2),

                    // ── Tab 3: Twitter ────────────────────────────────────────
                    Tab::make('Twitter')
                        ->schema([
                            Forms\Components\Select::make('twitter_card')
                                ->label('Twitter Card')
                                ->options([
                                    'summary'             => 'Summary',
                                    'summary_large_image' => 'Summary Large Image',
                                    'app'                 => 'App',
                                    'player'              => 'Player',
                                ])
                                ->default('summary_large_image'),

                            Forms\Components\TextInput::make('twitter_title')
                                ->label('Twitter Title')
                                ->columnSpanFull(),

                            Forms\Components\Textarea::make('twitter_description')
                                ->label('Twitter Description')
                                ->rows(3)
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

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

                TextColumn::make('meta_title')
                    ->label('Meta Title')
                    ->limit(60)
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('robots')
                    ->label('Robots')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'index, follow'     => 'success',
                        'noindex, nofollow' => 'danger',
                        'noindex, follow'   => 'warning',
                        default             => 'gray',
                    }),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('model_type')
                    ->label('Model Type')
                    ->options(fn () => SeoMeta::query()
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
            'index' => Pages\ListSeoMeta::route('/'),
            'edit'  => Pages\EditSeoMeta::route('/{record}/edit'),
        ];
    }
}
