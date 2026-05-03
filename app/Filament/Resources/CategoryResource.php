<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-tag';

    protected static \UnitEnum|string|null $navigationGroup = 'Catalog';

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('parent_id')
                ->label('Parent Category')
                ->relationship('parent', 'name')
                ->searchable()
                ->preload()
                ->nullable(),

            Forms\Components\TextInput::make('name')
                ->label('Internal Name')
                ->hint('Dùng trong admin — không hiển thị cho người dùng')
                ->hintIcon('heroicon-o-information-circle')
                ->hintColor('warning')
                ->helperText('Tên ngắn gọn để nhận biết danh mục trong hệ thống. Tên hiển thị thật sự nhập trong tab Translations bên dưới.')
                ->required()
                ->live(debounce: 500)
                ->afterStateUpdated(fn (Set $set, ?string $state) =>
                    $set('slug', Str::slug($state ?? ''))
                ),

            Forms\Components\TextInput::make('slug')
                ->label('Internal Slug')
                ->hint('Dùng trong JSON-LD và API nội bộ — không phải URL công khai')
                ->hintIcon('heroicon-o-information-circle')
                ->hintColor('warning')
                ->helperText('URL công khai dùng slug từ tab Translations.')
                ->required()
                ->unique(table: Category::class, column: 'slug', ignoreRecord: true),

            Forms\Components\Textarea::make('description')
                ->label('Internal Description')
                ->hint('Không hiển thị trực tiếp — dùng làm gợi ý nội dung')
                ->hintIcon('heroicon-o-information-circle')
                ->hintColor('warning')
                ->nullable()
                ->rows(3),

            Forms\Components\FileUpload::make('image_path')
                ->disk('public')
                ->directory('categories')
                ->image()
                ->nullable(),

            Forms\Components\TextInput::make('sort_order')
                ->numeric()
                ->default(0),

            Forms\Components\Toggle::make('is_active')
                ->default(true),

            // ── Translations ──────────────────────────────────────────────────
            Section::make('Translations')
                ->icon('heroicon-o-language')
                ->schema([
                    Tabs::make('LocaleTabs')
                        ->tabs([
                            Tab::make('🇻🇳 Tiếng Việt (vi)')
                                ->schema([
                                    Forms\Components\TextInput::make('translations.vi.name')
                                        ->label('Tên hiển thị (vi)')
                                        ->hint('Hiển thị trên trang web cho người dùng Việt Nam')
                                        ->hintIcon('heroicon-o-eye')
                                        ->hintColor('success')
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, Set $set) =>
                                            $set('translations.vi.slug', Str::slug($state ?? '')))
                                        ->columnSpanFull(),

                                    Forms\Components\TextInput::make('translations.vi.slug')
                                        ->label('URL Slug (vi)')
                                        ->hint('Tạo URL: /vi/categories/{slug}')
                                        ->hintIcon('heroicon-o-link')
                                        ->hintColor('success')
                                        ->helperText('Tự động tạo từ tên. Phải unique theo từng ngôn ngữ.')
                                        ->columnSpanFull(),

                                    Forms\Components\Textarea::make('translations.vi.description')
                                        ->label('Mô tả (vi)')
                                        ->hint('Hiển thị trên trang danh mục — Google đọc để hiểu nội dung')
                                        ->hintIcon('heroicon-o-eye')
                                        ->hintColor('success')
                                        ->rows(3)
                                        ->columnSpanFull(),

                                    Forms\Components\TextInput::make('translations.vi.meta_title')
                                        ->label('Meta Title (vi)')
                                        ->hint('Tiêu đề xanh trên Google Search + tab trình duyệt. Tối ưu 50–70 ký tự.')
                                        ->hintIcon('heroicon-o-magnifying-glass')
                                        ->hintColor('info')
                                        ->maxLength(70)
                                        ->columnSpanFull(),

                                    Forms\Components\Textarea::make('translations.vi.meta_description')
                                        ->label('Meta Description (vi)')
                                        ->hint('Đoạn xám dưới tiêu đề trên Google Search. Tối ưu 120–160 ký tự.')
                                        ->hintIcon('heroicon-o-magnifying-glass')
                                        ->hintColor('info')
                                        ->maxLength(160)
                                        ->rows(3)
                                        ->columnSpanFull(),
                                ]),

                            Tab::make('🇬🇧 English (en)')
                                ->schema([
                                    Forms\Components\TextInput::make('translations.en.name')
                                        ->label('Display Name (en)')
                                        ->hint('Shown on the website to English-speaking visitors')
                                        ->hintIcon('heroicon-o-eye')
                                        ->hintColor('success')
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, Set $set) =>
                                            $set('translations.en.slug', Str::slug($state ?? '')))
                                        ->columnSpanFull(),

                                    Forms\Components\TextInput::make('translations.en.slug')
                                        ->label('URL Slug (en)')
                                        ->hint('Creates URL: /en/categories/{slug}')
                                        ->hintIcon('heroicon-o-link')
                                        ->hintColor('success')
                                        ->helperText('Auto-generated from name. Must be unique per locale.')
                                        ->columnSpanFull(),

                                    Forms\Components\Textarea::make('translations.en.description')
                                        ->label('Description (en)')
                                        ->hint('Shown on the category page — Google reads this to understand content')
                                        ->hintIcon('heroicon-o-eye')
                                        ->hintColor('success')
                                        ->rows(3)
                                        ->columnSpanFull(),

                                    Forms\Components\TextInput::make('translations.en.meta_title')
                                        ->label('Meta Title (en)')
                                        ->hint('Blue title on Google Search + browser tab. Optimal 50–70 chars.')
                                        ->hintIcon('heroicon-o-magnifying-glass')
                                        ->hintColor('info')
                                        ->maxLength(70)
                                        ->columnSpanFull(),

                                    Forms\Components\Textarea::make('translations.en.meta_description')
                                        ->label('Meta Description (en)')
                                        ->hint('Grey snippet under Google title. Optimal 120–160 chars.')
                                        ->hintIcon('heroicon-o-magnifying-glass')
                                        ->hintColor('info')
                                        ->maxLength(160)
                                        ->rows(3)
                                        ->columnSpanFull(),
                                ]),
                        ])
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('products'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('products_count')
                    ->label('Products')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),

                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Parent')
                    ->relationship('parent', 'name'),
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
            'index'  => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit'   => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
