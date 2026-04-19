<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BrandResource\Pages;
use App\Models\Brand;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class BrandResource extends Resource
{
    protected static ?string $model = Brand::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-bookmark-square';

    protected static \UnitEnum|string|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            // ── General ───────────────────────────────────────────────────────
            Section::make('General')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->live(debounce: 500)
                        ->afterStateUpdated(fn (Set $set, ?string $state) =>
                            $set('slug', Str::slug($state ?? ''))
                        ),

                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->unique(table: Brand::class, column: 'slug', ignoreRecord: true),

                    Forms\Components\TextInput::make('website')
                        ->url()
                        ->placeholder('https://...')
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull(),

                    Forms\Components\FileUpload::make('logo')
                        ->label('Logo')
                        ->disk('public')
                        ->directory('brands')
                        ->image()
                        ->imagePreviewHeight('80')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->default(0),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true),
                ])
                ->columns(2),

            // ── SEO ───────────────────────────────────────────────────────────
            Group::make()
                ->relationship('seoMeta')
                ->schema([
                    Section::make('SEO')
                        ->icon('heroicon-o-magnifying-glass')
                        ->schema([
                            Forms\Components\TextInput::make('meta_title')
                                ->label('Meta Title')
                                ->maxLength(70)
                                ->placeholder('Auto-filled from brand name')
                                ->hint('Auto-filled from brand name')
                                ->hintIcon('heroicon-o-sparkles')
                                ->hintColor('info')
                                ->helperText('Optimal: 50–70 characters.')
                                ->afterStateHydrated(function ($state, $set, $livewire): void {
                                    if (empty($state) && $livewire->record?->name) {
                                        $set('meta_title', $livewire->record->name);
                                    }
                                })
                                ->columnSpanFull(),

                            Forms\Components\Textarea::make('meta_description')
                                ->label('Meta Description')
                                ->rows(3)
                                ->maxLength(160)
                                ->placeholder('Auto-filled from brand description')
                                ->hint('Auto-filled from brand description')
                                ->hintIcon('heroicon-o-sparkles')
                                ->hintColor('info')
                                ->helperText('Optimal: 120–160 characters.')
                                ->afterStateHydrated(function ($state, $set, $livewire): void {
                                    if (empty($state) && $livewire->record?->description) {
                                        $set('meta_description', $livewire->record->description);
                                    }
                                })
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('canonical_url')
                                ->label('Canonical URL')
                                ->url()
                                ->placeholder('Auto-generated from slug')
                                ->hint('Auto-generated from slug')
                                ->hintIcon('heroicon-o-sparkles')
                                ->hintColor('info')
                                ->afterStateHydrated(function ($state, $set, $livewire): void {
                                    if (empty($state) && $livewire->record?->slug) {
                                        $set('canonical_url', url('/brands/' . $livewire->record->slug));
                                    }
                                })
                                ->columnSpanFull(),

                            Forms\Components\Select::make('robots')
                                ->label('Robots')
                                ->options([
                                    'index, follow'     => 'index, follow (default)',
                                    'noindex, follow'   => 'noindex, follow',
                                    'index, nofollow'   => 'index, nofollow',
                                    'noindex, nofollow' => 'noindex, nofollow',
                                ])
                                ->default('index, follow')
                                ->native(false),
                        ])
                        ->columns(2)
                        ->collapsible()
                        ->collapsed(),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\ImageColumn::make('logo')
                    ->disk('public')
                    ->height(40),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),

                Tables\Columns\TextColumn::make('website')
                    ->url(fn (Brand $record): string => $record->website ?? '#')
                    ->openUrlInNewTab()
                    ->color('primary')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
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
            'index'  => Pages\ListBrands::route('/'),
            'create' => Pages\CreateBrand::route('/create'),
            'edit'   => Pages\EditBrand::route('/{record}/edit'),
        ];
    }
}
