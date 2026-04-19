<?php

namespace App\Filament\Resources;

use App\Enums\OgType;
use App\Filament\Resources\ProductResource\Pages;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
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

                            Forms\Components\Select::make('brand_id')
                                ->label('Brand')
                                ->relationship('brand', 'name')
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->native(false),

                            Forms\Components\Select::make('manufacturer_id')
                                ->label('Manufacturer')
                                ->relationship('manufacturer', 'name')
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->native(false),

                            Forms\Components\TextInput::make('name')
                                ->required()
                                ->live(debounce: 500)
                                ->afterStateUpdated(fn (Set $set, ?string $state) =>
                                    $set('slug', Str::slug($state ?? ''))
                                )
                                ->columnSpanFull(),

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
                                        ->getUploadedFileNameForStorageUsing(function ($file): string {
                                            $dir  = 'products/' . now()->format('Y/m');
                                            $name = Str::slug(
                                                pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
                                            );
                                            $ext  = strtolower($file->getClientOriginalExtension());

                                            // Fallback if slug is empty (e.g. all special chars)
                                            if (empty($name)) {
                                                $name = 'image-' . now()->format('YmdHis');
                                            }

                                            $filename = "{$name}.{$ext}";
                                            $counter  = 1;

                                            while (Storage::disk('public')->exists("{$dir}/{$filename}")) {
                                                $filename = "{$name}-{$counter}.{$ext}";
                                                $counter++;
                                            }

                                            return $filename;
                                        })
                                        ->hint('The file name will automatically be converted to Latin without accents. If the name is duplicated, the system will automatically add a numeric suffix (e.g., den-led-1.jpg)).')
                                        ->hintIcon('heroicon-o-information-circle')
                                        ->hintColor('warning')
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
                                    // ── Files ─────────────────────────────────
                                    Forms\Components\FileUpload::make('path')
                                        ->label('Video File')
                                        ->disk('public')
                                        ->directory(fn () => 'products/' . now()->format('Y/m'))
                                        ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/ogg'])
                                        ->required()
                                        ->columnSpan(1),

                                    Forms\Components\FileUpload::make('thumbnail_path')
                                        ->label('Thumbnail')
                                        ->disk('public')
                                        ->directory(fn () => 'products/' . now()->format('Y/m'))
                                        ->image()
                                        ->imagePreviewHeight('100')
                                        ->columnSpan(1),

                                    // ── SEO fields ────────────────────────────
                                    Forms\Components\TextInput::make('title')
                                        ->label('Title')
                                        ->maxLength(255)
                                        ->hint('Required for VideoObject rich results')
                                        ->hintIcon('heroicon-o-magnifying-glass')
                                        ->hintColor('warning')
                                        ->columnSpan(2),

                                    Forms\Components\Textarea::make('description')
                                        ->label('Description')
                                        ->rows(2)
                                        ->hint('Required for VideoObject rich results')
                                        ->hintIcon('heroicon-o-magnifying-glass')
                                        ->hintColor('warning')
                                        ->columnSpan(2),

                                    Forms\Components\TextInput::make('duration')
                                        ->label('Duration (ISO 8601)')
                                        ->placeholder('PT2M30S')
                                        ->hint('e.g. PT30S = 30s, PT2M30S = 2m30s, PT1H = 1h')
                                        ->hintIcon('heroicon-o-clock')
                                        ->hintColor('info')
                                        ->columnSpan(1),

                                    Forms\Components\TextInput::make('sort_order')
                                        ->label('Sort Order')
                                        ->numeric()
                                        ->default(0)
                                        ->columnSpan(1),
                                ])
                                ->orderColumn('sort_order')
                                ->reorderable()
                                ->reorderableWithDragAndDrop()
                                ->defaultItems(0)
                                ->columns(2)
                                ->columnSpanFull(),
                        ]),

                    // ── Tab 6: SEO ────────────────────────────────────────────
                    Tab::make('SEO')
                        ->icon('heroicon-o-magnifying-glass')
                        ->schema([
                            Group::make()
                                ->relationship('seoMeta')
                                ->schema([
                                    Section::make('Meta Tags')
                                        ->schema([
                                            Forms\Components\TextInput::make('meta_title')
                                                ->label('Meta Title')
                                                ->maxLength(70)
                                                ->placeholder('Auto-filled from product name')
                                                ->hint('Auto-filled from product name')
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
                                                ->helperText('Optimal: 120–160 characters')
                                                ->columnSpanFull(),

                                            Forms\Components\TextInput::make('meta_keywords')
                                                ->label('Meta Keywords')
                                                ->helperText('Comma separated')
                                                ->columnSpanFull(),

                                            Forms\Components\TextInput::make('canonical_url')
                                                ->label('Canonical URL')
                                                ->url()
                                                ->placeholder('Auto-generated from slug')
                                                ->hint('Auto-generated from slug')
                                                ->hintIcon('heroicon-o-sparkles')
                                                ->hintColor('info')
                                                ->helperText('Leave blank to auto-generate from product slug.')
                                                ->afterStateHydrated(function ($state, $set, $livewire): void {
                                                    if (empty($state) && $livewire->record?->slug) {
                                                        $set('canonical_url', url('/products/' . $livewire->record->slug));
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
                                        ->columns(2),

                                    Section::make('Open Graph (Facebook / LinkedIn)')
                                        ->schema([
                                            Forms\Components\TextInput::make('og_title')
                                                ->label('OG Title')
                                                ->placeholder('Auto-filled from product name')
                                                ->hint('Auto-filled from product name')
                                                ->hintIcon('heroicon-o-sparkles')
                                                ->hintColor('info')
                                                ->afterStateHydrated(function ($state, $set, $livewire): void {
                                                    if (empty($state) && $livewire->record?->name) {
                                                        $set('og_title', $livewire->record->name);
                                                    }
                                                })
                                                ->columnSpanFull(),

                                            Forms\Components\Textarea::make('og_description')
                                                ->label('OG Description')
                                                ->rows(2)
                                                ->placeholder('Auto-filled from meta description')
                                                ->hint('Auto-filled from meta description')
                                                ->hintIcon('heroicon-o-sparkles')
                                                ->hintColor('info')
                                                ->afterStateHydrated(function ($state, $set, $record): void {
                                                    // $record = SeoMeta (child model inside relationship group)
                                                    if (empty($state) && $record?->meta_description) {
                                                        $set('og_description', $record->meta_description);
                                                    }
                                                })
                                                ->columnSpanFull(),

                                            Forms\Components\TextInput::make('og_image')
                                                ->label('OG Image URL')
                                                ->url()
                                                ->placeholder('Auto-filled from first product image')
                                                ->hint('Auto-filled from first product image')
                                                ->hintIcon('heroicon-o-sparkles')
                                                ->hintColor('info')
                                                ->helperText('Recommended: 1200×630px. Leave blank to auto-use the first product image.')
                                                ->afterStateHydrated(function ($state, $set, $livewire): void {
                                                    if (empty($state)) {
                                                        // $livewire->record = Product (parent model)
                                                        $firstImage = $livewire->record?->images()->orderBy('sort_order')->first();
                                                        if ($firstImage?->path) {
                                                            $set('og_image', Storage::disk('public')->url($firstImage->path));
                                                        }
                                                    }
                                                })
                                                ->columnSpanFull(),

                                            Forms\Components\Select::make('og_type')
                                                ->label('OG Type')
                                                ->options(collect(OgType::cases())->mapWithKeys(
                                                    fn (OgType $case) => [$case->value => $case->value]
                                                ))
                                                ->default(OgType::Product->value)
                                                ->native(false),
                                        ])
                                        ->columns(2)
                                        ->collapsed(),

                                    Section::make('Twitter Card')
                                        ->schema([
                                            Forms\Components\Select::make('twitter_card')
                                                ->label('Card Type')
                                                ->options([
                                                    'summary'             => 'Summary',
                                                    'summary_large_image' => 'Summary Large Image',
                                                ])
                                                ->default('summary_large_image')
                                                ->native(false),

                                            Forms\Components\TextInput::make('twitter_title')
                                                ->label('Twitter Title')
                                                ->placeholder('Auto-filled from product name')
                                                ->hint('Auto-filled from product name')
                                                ->hintIcon('heroicon-o-sparkles')
                                                ->hintColor('info')
                                                ->afterStateHydrated(function ($state, $set, $livewire): void {
                                                    if (empty($state) && $livewire->record?->name) {
                                                        $set('twitter_title', $livewire->record->name);
                                                    }
                                                }),

                                            Forms\Components\Textarea::make('twitter_description')
                                                ->label('Twitter Description')
                                                ->rows(2)
                                                ->placeholder('Auto-filled from meta description')
                                                ->hint('Auto-filled from meta description')
                                                ->hintIcon('heroicon-o-sparkles')
                                                ->hintColor('info')
                                                ->afterStateHydrated(function ($state, $set, $record): void {
                                                    // $record = SeoMeta (child model inside relationship group)
                                                    if (empty($state) && $record?->meta_description) {
                                                        $set('twitter_description', $record->meta_description);
                                                    }
                                                })
                                                ->columnSpanFull(),
                                        ])
                                        ->columns(2)
                                        ->collapsed(),
                                ]),
                        ]),

                    // ── Tab 7: GEO / AI ───────────────────────────────────────
                    Tab::make('GEO / AI')
                        ->icon('heroicon-o-cpu-chip')
                        ->schema([
                            Group::make()
                                ->relationship('geoProfile')
                                ->schema([
                                    Section::make('AI Context')
                                        ->description('Used by ChatGPT, Gemini, Perplexity when answering questions about this product.')
                                        ->schema([
                                            Forms\Components\Textarea::make('ai_summary')
                                                ->label('AI Summary')
                                                ->rows(4)
                                                ->helperText('2–4 sentences describing this product for AI engines.')
                                                ->columnSpanFull(),

                                            Forms\Components\Textarea::make('use_cases')
                                                ->label('Use Cases')
                                                ->rows(3)
                                                ->helperText('When/where should this product be used?')
                                                ->columnSpanFull(),

                                            Forms\Components\TextInput::make('target_audience')
                                                ->label('Target Audience')
                                                ->helperText('e.g. "Lighting designers, electrical contractors"')
                                                ->columnSpanFull(),

                                            Forms\Components\Textarea::make('llm_context_hint')
                                                ->label('LLM Context Hint')
                                                ->rows(2)
                                                ->helperText('Additional context hint for LLM — e.g. competitor comparisons, certifications.')
                                                ->columnSpanFull(),
                                        ]),

                                    Section::make('Key Facts')
                                        ->description('Structured facts about this product (e.g. wattage, protocol, warranty).')
                                        ->schema([
                                            Forms\Components\KeyValue::make('key_facts')
                                                ->label('')
                                                ->keyLabel('Fact')
                                                ->valueLabel('Value')
                                                ->addActionLabel('Add fact')
                                                ->columnSpanFull(),
                                        ])
                                        ->collapsed(),

                                    Section::make('FAQ')
                                        ->description('Frequently asked questions — used in JSON-LD FAQPage schema and AI answers.')
                                        ->schema([
                                            Forms\Components\Repeater::make('faq')
                                                ->label('')
                                                ->schema([
                                                    Forms\Components\TextInput::make('question')
                                                        ->label('Question')
                                                        ->required()
                                                        ->columnSpanFull(),

                                                    Forms\Components\Textarea::make('answer')
                                                        ->label('Answer')
                                                        ->rows(2)
                                                        ->required()
                                                        ->columnSpanFull(),
                                                ])
                                                ->defaultItems(0)
                                                ->addActionLabel('Add Q&A')
                                                ->columnSpanFull(),
                                        ])
                                        ->collapsed(),
                                ]),
                        ]),

                    // ── Tab 8: LLMs ───────────────────────────────────────────
                    Tab::make('LLMs')
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            Placeholder::make('llms_info')
                                ->label('')
                                ->content('LLMs entries are auto-generated by the system when this product is saved. Use the fields below to review or override the published content.')
                                ->columnSpanFull(),

                            Forms\Components\Repeater::make('llmsEntries')
                                ->relationship()
                                ->label('LLMs Entries')
                                ->schema([
                                    Forms\Components\TextInput::make('title')
                                        ->label('Title')
                                        ->required()
                                        ->columnSpanFull(),

                                    Forms\Components\TextInput::make('url')
                                        ->label('URL')
                                        ->url()
                                        ->columnSpanFull(),

                                    Forms\Components\Textarea::make('summary')
                                        ->label('Summary')
                                        ->rows(3)
                                        ->columnSpanFull(),

                                    Forms\Components\Textarea::make('key_facts_text')
                                        ->label('Key Facts (plain text)')
                                        ->rows(3)
                                        ->helperText('Plain text version of key facts for llms.txt')
                                        ->columnSpanFull(),

                                    Forms\Components\Textarea::make('faq_text')
                                        ->label('FAQ (plain text)')
                                        ->rows(3)
                                        ->helperText('Plain text Q&A for llms.txt')
                                        ->columnSpanFull(),

                                    Forms\Components\Toggle::make('is_active')
                                        ->label('Active')
                                        ->default(true),
                                ])
                                ->defaultItems(0)
                                ->addable(false)
                                ->columnSpanFull(),
                        ]),

                    // ── Tab 9: JSON-LD ────────────────────────────────────────
                    Tab::make('JSON-LD')
                        ->icon('heroicon-o-code-bracket')
                        ->schema([
                            Placeholder::make('jsonld_info')
                                ->label('')
                                ->content('JSON-LD schemas are auto-generated by the system when this product is saved. To manually edit a schema payload, go to SEO & GEO → JSON-LD Schemas.')
                                ->columnSpanFull(),

                            Forms\Components\Repeater::make('jsonldSchemas')
                                ->relationship()
                                ->label('Schemas')
                                ->schema([
                                    Forms\Components\TextInput::make('schema_type')
                                        ->label('Type')
                                        ->disabled(),

                                    Forms\Components\TextInput::make('label')
                                        ->label('Label')
                                        ->disabled(),

                                    Forms\Components\Toggle::make('is_active')
                                        ->label('Active')
                                        ->inline(),

                                    Forms\Components\Toggle::make('is_auto_generated')
                                        ->label('Auto Generated')
                                        ->disabled()
                                        ->inline(),

                                    Forms\Components\TextInput::make('sort_order')
                                        ->label('Order')
                                        ->numeric(),
                                ])
                                ->defaultItems(0)
                                ->addable(false)
                                ->deletable(false)
                                ->columns(3)
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
                \Filament\Actions\Action::make('toggleActive')
                    ->label(fn (Product $record) => $record->is_active ? 'Hide' : 'Show')
                    ->icon(fn (Product $record) => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (Product $record) => $record->is_active ? 'warning' : 'success')
                    ->action(fn (Product $record) => $record->update(['is_active' => ! $record->is_active])),
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
