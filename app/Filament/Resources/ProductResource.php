<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use App\Models\Category;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cube';

    protected static \UnitEnum|string|null $navigationGroup = 'Catalog';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Tabs::make('Tabs')
                ->tabs([

                    // ── Tab 1: General ────────────────────────────────────────
                    Tab::make('General')
                        ->schema([
                            Forms\Components\Select::make('categories')
                                ->label('Categories')
                                ->relationship('categories', 'name')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->live()
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('name')
                                ->required()
                                ->live(debounce: 500)
                                ->afterStateUpdated(fn (Set $set, ?string $state) =>
                                    $set('slug', Str::slug($state ?? ''))
                                ),

                            Forms\Components\TextInput::make('slug')
                                ->required()
                                ->unique(table: Product::class, column: 'slug', ignoreRecord: true),

                            Forms\Components\TextInput::make('sku')
                                ->label('SKU')
                                ->required()
                                ->unique(table: Product::class, column: 'sku', ignoreRecord: true),

                            Forms\Components\Textarea::make('short_description')
                                ->rows(3)
                                ->columnSpanFull(),

                            Forms\Components\Toggle::make('is_active')
                                ->default(true),
                        ])
                        ->columns(2),

                    // ── Tab 2: Pricing & Stock ────────────────────────────────
                    Tab::make('Pricing & Stock')
                        ->schema([
                            Forms\Components\TextInput::make('price')
                                ->numeric()
                                ->prefix('₫')
                                ->required(),

                            Forms\Components\TextInput::make('sale_price')
                                ->numeric()
                                ->prefix('₫'),

                            Forms\Components\TextInput::make('stock_quantity')
                                ->numeric()
                                ->required(),
                        ])
                        ->columns(2),

                    // ── Tab 3: Description ────────────────────────────────────
                    Tab::make('Description')
                        ->schema([
                            Forms\Components\RichEditor::make('description')
                                ->fileAttachmentsDisk('public')
                                ->fileAttachmentsDirectory('products/description')
                                ->fileAttachmentsVisibility('public')
                                ->columnSpanFull(),
                        ]),

                    // ── Tab 4: Images ─────────────────────────────────────────
                    Tab::make('Images')
                        ->schema([
                            Forms\Components\Repeater::make('images')
                                ->relationship()
                                ->schema([
                                    Forms\Components\FileUpload::make('path')
                                        ->label('Image')
                                        ->disk('public')
                                        ->visibility('public')
                                        ->directory(fn () => 'products/' . now()->format('Y/m'))
                                        ->image()
                                        ->imagePreviewHeight('120')
                                        ->imageEditor()
                                        ->required()
                                        ->columnSpan(2),

                                    Forms\Components\TextInput::make('alt_text')
                                        ->label('Alt Text'),

                                    Forms\Components\Select::make('categories')
                                        ->label('Attributes')
                                        ->relationship('categories', 'name')
                                        ->options(function (Get $get) {
                                            $ids = $get('../../categories');
                                            if (empty($ids)) {
                                                return [];
                                            }
                                            return Category::whereIn('id', $ids)
                                                ->pluck('name', 'id');
                                        })
                                        ->multiple()
                                        ->native(false)
                                        ->placeholder('— chọn sau khi chọn categories —')
                                        ->columnSpan(2),

                                    Forms\Components\TextInput::make('price')
                                        ->label('Price override')
                                        ->numeric()
                                        ->prefix('₫')
                                        ->placeholder('Để trống = dùng giá sản phẩm'),
                                ])
                                ->orderColumn('sort_order')
                                ->reorderable()
                                ->reorderableWithDragAndDrop()
                                ->columns(3)
                                ->columnSpanFull(),
                        ]),

                    // ── Tab 5: Videos ─────────────────────────────────────────
                    Tab::make('Videos')
                        ->schema([
                            Forms\Components\Repeater::make('videos')
                                ->relationship()
                                ->schema([
                                    Forms\Components\FileUpload::make('path')
                                        ->label('Video File')
                                        ->disk('public')
                                        ->directory(fn () => 'products/' . now()->format('Y/m'))
                                        ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/ogg'])
                                        ->required(),

                                    Forms\Components\FileUpload::make('thumbnail_path')
                                        ->label('Thumbnail')
                                        ->disk('public')
                                        ->directory(fn () => 'products/' . now()->format('Y/m'))
                                        ->image(),
                                ])
                                ->defaultItems(0)
                                ->columns(2)
                                ->columnSpanFull(),
                        ]),

                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['thumbnail', 'categories']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail.path')
                    ->label('Thumbnail')
                    ->disk('public'),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),

                Tables\Columns\TextColumn::make('categories.name')
                    ->label('Categories')
                    ->badge()
                    ->separator(','),

                Tables\Columns\TextColumn::make('price')
                    ->money('VND')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sale_price')
                    ->money('VND')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),

                Tables\Filters\SelectFilter::make('categories')
                    ->label('Category')
                    ->relationship('categories', 'name')
                    ->multiple(),

                TrashedFilter::make(),
            ])
            ->actions([
                EditAction::make(),
                RestoreAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    RestoreBulkAction::make(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScope(SoftDeletingScope::class);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit'   => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
