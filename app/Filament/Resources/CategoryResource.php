<?php

namespace App\Filament\Resources;

use App\Enums\OgType;
use App\Filament\Resources\CategoryResource\Pages;
use App\Forms\Components\MediaFileUpload;
use App\Forms\Plugins\MediaRichEditorPlugin;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
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
            Tabs::make('CategoryTabs')
                ->tabs([

                    // ── General ───────────────────────────────────────────────────
                    Tab::make('General')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->schema([
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
                                ->helperText('Tên ngắn gọn để nhận biết danh mục trong hệ thống.')
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
                                ->helperText('URL công khai dùng slug từ tab Content.')
                                ->required()
                                ->unique(table: Category::class, column: 'slug', ignoreRecord: true),

                            Forms\Components\Textarea::make('description')
                                ->label('Internal Description')
                                ->hint('Không hiển thị trực tiếp — dùng làm gợi ý nội dung')
                                ->hintIcon('heroicon-o-information-circle')
                                ->hintColor('warning')
                                ->nullable()
                                ->rows(3)
                                ->columnSpanFull(),

                            MediaFileUpload::make('image_path')
                                ->label('Category Image')
                                ->image()
                                ->nullable()
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('sort_order')
                                ->numeric()
                                ->default(0),

                            Forms\Components\Toggle::make('is_active')
                                ->default(true),
                        ])
                        ->columns(2),

                    // ── Content ───────────────────────────────────────────────────
                    Tab::make('Content')
                        ->icon('heroicon-o-language')
                        ->schema([
                            Tabs::make('ContentLocaleTabs')
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

                                            RichEditor::make('translations.vi.rich_content')
                                                ->label('Nội dung phong phú (vi)')
                                                ->hint('Nội dung dài, có thể chèn ảnh — hiển thị ở phần dưới trang danh mục')
                                                ->hintIcon('heroicon-o-document-text')
                                                ->hintColor('success')
                                                ->plugins([MediaRichEditorPlugin::make()])
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

                                            RichEditor::make('translations.en.rich_content')
                                                ->label('Rich Content (en)')
                                                ->hint('Long-form content with images — displayed at the bottom of the category page')
                                                ->hintIcon('heroicon-o-document-text')
                                                ->hintColor('success')
                                                ->plugins([MediaRichEditorPlugin::make()])
                                                ->columnSpanFull(),
                                        ]),
                                ])
                                ->columnSpanFull(),
                        ]),

                    // ── SEO ───────────────────────────────────────────────────────
                    Tab::make('SEO')
                        ->icon('heroicon-o-magnifying-glass')
                        ->schema([
                            Tabs::make('SeoLocaleTabs')
                                ->tabs([
                                    Tab::make('🇻🇳 Tiếng Việt')
                                        ->schema([
                                            Group::make()
                                                ->relationship('seoMetaVi')
                                                ->schema([
                                                    Forms\Components\Hidden::make('locale')
                                                        ->default('vi'),

                                                    Section::make('Meta Tags')
                                                        ->schema([
                                                            Forms\Components\TextInput::make('meta_title')
                                                                ->label('Meta Title (vi)')
                                                                ->live(debounce: 400)
                                                                ->placeholder('Tự điền từ tên danh mục')
                                                                ->hint(fn (?string $state): string => self::charCounter($state, 50, 70))
                                                                ->hintColor(fn (?string $state): string => self::charCounterColor($state, 50, 70))
                                                                ->helperText('Tối ưu: 50–70 ký tự.')
                                                                ->afterStateHydrated(function ($state, $set, $livewire): void {
                                                                    if (empty($state)) {
                                                                        $name = $livewire->record?->translation('vi')?->name ?? $livewire->record?->name;
                                                                        if ($name) {
                                                                            $set('meta_title', $name);
                                                                        }
                                                                    }
                                                                })
                                                                ->columnSpanFull(),

                                                            Forms\Components\Textarea::make('meta_description')
                                                                ->label('Meta Description (vi)')
                                                                ->rows(3)
                                                                ->live(debounce: 400)
                                                                ->hint(fn (?string $state): string => self::charCounter($state, 120, 160))
                                                                ->hintColor(fn (?string $state): string => self::charCounterColor($state, 120, 160))
                                                                ->helperText('Tối ưu: 120–160 ký tự.')
                                                                ->afterStateHydrated(function ($state, $set, $livewire): void {
                                                                    if (empty($state)) {
                                                                        $desc = $livewire->record?->translation('vi')?->description;
                                                                        if ($desc) {
                                                                            $set('meta_description', $desc);
                                                                        }
                                                                    }
                                                                })
                                                                ->columnSpanFull(),

                                                            Forms\Components\TextInput::make('meta_keywords')
                                                                ->label('Meta Keywords (vi)')
                                                                ->helperText('Phân cách bằng dấu phẩy')
                                                                ->columnSpanFull(),

                                                            Forms\Components\TextInput::make('canonical_url')
                                                                ->label('Canonical URL (vi)')
                                                                ->url()
                                                                ->placeholder('Tự tạo từ slug (vi)')
                                                                ->hint('Tự tạo từ slug (vi)')
                                                                ->hintIcon('heroicon-o-sparkles')
                                                                ->hintColor('info')
                                                                ->afterStateHydrated(function ($state, $set, $livewire): void {
                                                                    if (empty($state)) {
                                                                        $slug = $livewire->record?->translation('vi')?->slug ?? $livewire->record?->slug;
                                                                        if ($slug) {
                                                                            $set('canonical_url', \App\Support\LocaleUrl::for('category', $slug, 'vi'));
                                                                        }
                                                                    }
                                                                })
                                                                ->columnSpanFull(),

                                                            Forms\Components\Select::make('robots')
                                                                ->label('Robots')
                                                                ->options([
                                                                    'index,follow'     => 'index, follow (default)',
                                                                    'noindex,follow'   => 'noindex,follow',
                                                                    'index,nofollow'   => 'index,nofollow',
                                                                    'noindex,nofollow' => 'noindex,nofollow',
                                                                ])
                                                                ->default('index,follow')
                                                                ->native(false),
                                                        ])
                                                        ->columns(2),

                                                    Section::make('Open Graph (vi)')
                                                        ->schema([
                                                            Forms\Components\TextInput::make('og_title')
                                                                ->label('OG Title (vi)')
                                                                ->placeholder('Tự điền từ Meta Title (vi)')
                                                                ->hint('Tự điền từ Meta Title (vi)')
                                                                ->hintIcon('heroicon-o-sparkles')
                                                                ->hintColor('info')
                                                                ->afterStateHydrated(function ($state, $set, $record, $livewire): void {
                                                                    if (empty($state)) {
                                                                        $set('og_title', $record?->meta_title
                                                                            ?? $livewire->record?->translation('vi')?->name
                                                                            ?? $livewire->record?->name);
                                                                    }
                                                                })
                                                                ->columnSpanFull(),

                                                            Forms\Components\Textarea::make('og_description')
                                                                ->label('OG Description (vi)')
                                                                ->rows(2)
                                                                ->placeholder('Tự điền từ Meta Description (vi)')
                                                                ->hint('Tự điền từ Meta Description (vi)')
                                                                ->hintIcon('heroicon-o-sparkles')
                                                                ->hintColor('info')
                                                                ->afterStateHydrated(function ($state, $set, $record): void {
                                                                    if (empty($state) && $record?->meta_description) {
                                                                        $set('og_description', $record->meta_description);
                                                                    }
                                                                })
                                                                ->columnSpanFull(),

                                                            Forms\Components\TextInput::make('og_image')
                                                                ->label('OG Image URL')
                                                                ->url()
                                                                ->placeholder('Tự điền từ ảnh danh mục')
                                                                ->hint('Tự điền từ ảnh danh mục')
                                                                ->hintIcon('heroicon-o-sparkles')
                                                                ->hintColor('info')
                                                                ->helperText('Khuyến nghị: 1200×630px.')
                                                                ->afterStateHydrated(function ($state, $set, $livewire): void {
                                                                    if (empty($state) && $livewire->record?->image_path) {
                                                                        $set('og_image', Storage::disk('public')->url($livewire->record->image_path));
                                                                    }
                                                                })
                                                                ->columnSpanFull(),

                                                            Forms\Components\Select::make('og_type')
                                                                ->label('OG Type')
                                                                ->options(collect(OgType::cases())->mapWithKeys(
                                                                    fn (OgType $case) => [$case->value => $case->value]
                                                                ))
                                                                ->default(OgType::Website->value)
                                                                ->native(false),
                                                        ])
                                                        ->columns(2)
                                                        ->collapsed(),

                                                    Section::make('Twitter Card (vi)')
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
                                                                ->label('Twitter Title (vi)')
                                                                ->placeholder('Tự điền từ Meta Title (vi)')
                                                                ->hint('Tự điền từ Meta Title (vi)')
                                                                ->hintIcon('heroicon-o-sparkles')
                                                                ->hintColor('info')
                                                                ->afterStateHydrated(function ($state, $set, $record, $livewire): void {
                                                                    if (empty($state)) {
                                                                        $set('twitter_title', $record?->meta_title
                                                                            ?? $livewire->record?->translation('vi')?->name
                                                                            ?? $livewire->record?->name);
                                                                    }
                                                                }),

                                                            Forms\Components\Textarea::make('twitter_description')
                                                                ->label('Twitter Description (vi)')
                                                                ->rows(2)
                                                                ->placeholder('Tự điền từ Meta Description (vi)')
                                                                ->hint('Tự điền từ Meta Description (vi)')
                                                                ->hintIcon('heroicon-o-sparkles')
                                                                ->hintColor('info')
                                                                ->afterStateHydrated(function ($state, $set, $record): void {
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

                                    Tab::make('🇬🇧 English')
                                        ->schema([
                                            Group::make()
                                                ->relationship('seoMetaEn')
                                                ->schema([
                                                    Forms\Components\Hidden::make('locale')
                                                        ->default('en'),

                                                    Section::make('Meta Tags')
                                                        ->schema([
                                                            Forms\Components\TextInput::make('meta_title')
                                                                ->label('Meta Title (en)')
                                                                ->live(debounce: 400)
                                                                ->placeholder('Auto-filled from category name')
                                                                ->hint(fn (?string $state): string => self::charCounter($state, 50, 70))
                                                                ->hintColor(fn (?string $state): string => self::charCounterColor($state, 50, 70))
                                                                ->helperText('Optimal: 50–70 chars.')
                                                                ->afterStateHydrated(function ($state, $set, $livewire): void {
                                                                    if (empty($state)) {
                                                                        $name = $livewire->record?->translation('en')?->name ?? $livewire->record?->name;
                                                                        if ($name) {
                                                                            $set('meta_title', $name);
                                                                        }
                                                                    }
                                                                })
                                                                ->columnSpanFull(),

                                                            Forms\Components\Textarea::make('meta_description')
                                                                ->label('Meta Description (en)')
                                                                ->rows(3)
                                                                ->live(debounce: 400)
                                                                ->hint(fn (?string $state): string => self::charCounter($state, 120, 160))
                                                                ->hintColor(fn (?string $state): string => self::charCounterColor($state, 120, 160))
                                                                ->helperText('Optimal: 120–160 chars.')
                                                                ->afterStateHydrated(function ($state, $set, $livewire): void {
                                                                    if (empty($state)) {
                                                                        $desc = $livewire->record?->translation('en')?->description;
                                                                        if ($desc) {
                                                                            $set('meta_description', $desc);
                                                                        }
                                                                    }
                                                                })
                                                                ->columnSpanFull(),

                                                            Forms\Components\TextInput::make('meta_keywords')
                                                                ->label('Meta Keywords (en)')
                                                                ->helperText('Comma-separated')
                                                                ->columnSpanFull(),

                                                            Forms\Components\TextInput::make('canonical_url')
                                                                ->label('Canonical URL (en)')
                                                                ->url()
                                                                ->placeholder('Auto-generated from slug (en)')
                                                                ->hint('Auto-generated from slug (en)')
                                                                ->hintIcon('heroicon-o-sparkles')
                                                                ->hintColor('info')
                                                                ->afterStateHydrated(function ($state, $set, $livewire): void {
                                                                    if (empty($state)) {
                                                                        $slug = $livewire->record?->translation('en')?->slug ?? $livewire->record?->slug;
                                                                        if ($slug) {
                                                                            $set('canonical_url', \App\Support\LocaleUrl::for('category', $slug, 'en'));
                                                                        }
                                                                    }
                                                                })
                                                                ->columnSpanFull(),

                                                            Forms\Components\Select::make('robots')
                                                                ->label('Robots')
                                                                ->options([
                                                                    'index,follow'     => 'index, follow (default)',
                                                                    'noindex,follow'   => 'noindex,follow',
                                                                    'index,nofollow'   => 'index,nofollow',
                                                                    'noindex,nofollow' => 'noindex,nofollow',
                                                                ])
                                                                ->default('index,follow')
                                                                ->native(false),
                                                        ])
                                                        ->columns(2),

                                                    Section::make('Open Graph (en)')
                                                        ->schema([
                                                            Forms\Components\TextInput::make('og_title')
                                                                ->label('OG Title (en)')
                                                                ->placeholder('Auto-filled from Meta Title (en)')
                                                                ->hint('Auto-filled from Meta Title (en)')
                                                                ->hintIcon('heroicon-o-sparkles')
                                                                ->hintColor('info')
                                                                ->afterStateHydrated(function ($state, $set, $record, $livewire): void {
                                                                    if (empty($state)) {
                                                                        $set('og_title', $record?->meta_title
                                                                            ?? $livewire->record?->translation('en')?->name
                                                                            ?? $livewire->record?->name);
                                                                    }
                                                                })
                                                                ->columnSpanFull(),

                                                            Forms\Components\Textarea::make('og_description')
                                                                ->label('OG Description (en)')
                                                                ->rows(2)
                                                                ->placeholder('Auto-filled from Meta Description (en)')
                                                                ->hint('Auto-filled from Meta Description (en)')
                                                                ->hintIcon('heroicon-o-sparkles')
                                                                ->hintColor('info')
                                                                ->afterStateHydrated(function ($state, $set, $record): void {
                                                                    if (empty($state) && $record?->meta_description) {
                                                                        $set('og_description', $record->meta_description);
                                                                    }
                                                                })
                                                                ->columnSpanFull(),

                                                            Forms\Components\TextInput::make('og_image')
                                                                ->label('OG Image URL')
                                                                ->url()
                                                                ->placeholder('Auto-filled from category image')
                                                                ->hint('Auto-filled from category image')
                                                                ->hintIcon('heroicon-o-sparkles')
                                                                ->hintColor('info')
                                                                ->helperText('Recommended: 1200×630px.')
                                                                ->afterStateHydrated(function ($state, $set, $livewire): void {
                                                                    if (empty($state) && $livewire->record?->image_path) {
                                                                        $set('og_image', Storage::disk('public')->url($livewire->record->image_path));
                                                                    }
                                                                })
                                                                ->columnSpanFull(),

                                                            Forms\Components\Select::make('og_type')
                                                                ->label('OG Type')
                                                                ->options(collect(OgType::cases())->mapWithKeys(
                                                                    fn (OgType $case) => [$case->value => $case->value]
                                                                ))
                                                                ->default(OgType::Website->value)
                                                                ->native(false),
                                                        ])
                                                        ->columns(2)
                                                        ->collapsed(),

                                                    Section::make('Twitter Card (en)')
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
                                                                ->label('Twitter Title (en)')
                                                                ->placeholder('Auto-filled from Meta Title (en)')
                                                                ->hint('Auto-filled from Meta Title (en)')
                                                                ->hintIcon('heroicon-o-sparkles')
                                                                ->hintColor('info')
                                                                ->afterStateHydrated(function ($state, $set, $record, $livewire): void {
                                                                    if (empty($state)) {
                                                                        $set('twitter_title', $record?->meta_title
                                                                            ?? $livewire->record?->translation('en')?->name
                                                                            ?? $livewire->record?->name);
                                                                    }
                                                                }),

                                                            Forms\Components\Textarea::make('twitter_description')
                                                                ->label('Twitter Description (en)')
                                                                ->rows(2)
                                                                ->placeholder('Auto-filled from Meta Description (en)')
                                                                ->hint('Auto-filled from Meta Description (en)')
                                                                ->hintIcon('heroicon-o-sparkles')
                                                                ->hintColor('info')
                                                                ->afterStateHydrated(function ($state, $set, $record): void {
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
                                ]),
                        ]),

                    // ── GEO / AI ──────────────────────────────────────────────────
                    Tab::make('GEO / AI')
                        ->icon('heroicon-o-cpu-chip')
                        ->schema([
                            Tabs::make('GeoLocaleTabs')
                                ->tabs([
                                    Tab::make('🇻🇳 Tiếng Việt')
                                        ->schema([
                                            Group::make()
                                                ->relationship('geoProfileVi')
                                                ->schema([
                                                    Forms\Components\Hidden::make('locale')
                                                        ->default('vi'),

                                                    Section::make('AI Context')
                                                        ->schema([
                                                            Forms\Components\Textarea::make('ai_summary')
                                                                ->label('AI Summary (vi)')
                                                                ->hint('Đoạn tóm tắt ngắn cho AI / chatbot hiểu danh mục này')
                                                                ->rows(4)
                                                                ->placeholder('Mô tả 2–4 câu về danh mục: sản phẩm nào có trong đó, đối tượng khách hàng, điểm nổi bật...')
                                                                ->columnSpanFull(),

                                                            Forms\Components\Textarea::make('use_cases')
                                                                ->label('Use Cases (vi)')
                                                                ->hint('Ứng dụng thực tế — AI dùng để trả lời "danh mục này phù hợp cho ai / dùng ở đâu"')
                                                                ->rows(3)
                                                                ->placeholder('VD: Phù hợp cho nhà ở, văn phòng, công trình thương mại...')
                                                                ->columnSpanFull(),

                                                            Forms\Components\TextInput::make('target_audience')
                                                                ->label('Target Audience (vi)')
                                                                ->hint('Đối tượng mục tiêu — AI dùng để phân loại và gợi ý')
                                                                ->placeholder('VD: Kỹ sư điện, nhà thầu, hộ gia đình...')
                                                                ->columnSpanFull(),

                                                            Forms\Components\Textarea::make('llm_context_hint')
                                                                ->label('LLM Context Hint (vi)')
                                                                ->hint('Gợi ý thêm cho LLM khi sinh nội dung về danh mục')
                                                                ->rows(2)
                                                                ->columnSpanFull(),
                                                        ]),

                                                    Section::make('Key Facts (vi)')
                                                        ->schema([
                                                            Forms\Components\Repeater::make('key_facts')
                                                                ->label('')
                                                                ->schema([
                                                                    Forms\Components\TextInput::make('label')
                                                                        ->label('Nhãn')
                                                                        ->required()
                                                                        ->placeholder('VD: Số sản phẩm'),
                                                                    Forms\Components\TextInput::make('value')
                                                                        ->label('Giá trị')
                                                                        ->required()
                                                                        ->placeholder('VD: 120+'),
                                                                ])
                                                                ->columns(2)
                                                                ->addActionLabel('Thêm fact')
                                                                ->reorderable()
                                                                ->collapsible()
                                                                ->defaultItems(0)
                                                                ->columnSpanFull(),
                                                        ])
                                                        ->collapsible(),

                                                    Section::make('FAQ (vi)')
                                                        ->schema([
                                                            Forms\Components\Repeater::make('faq')
                                                                ->label('')
                                                                ->schema([
                                                                    Forms\Components\TextInput::make('question')
                                                                        ->label('Câu hỏi')
                                                                        ->required()
                                                                        ->placeholder('VD: Danh mục này có những sản phẩm gì?')
                                                                        ->columnSpanFull(),
                                                                    Forms\Components\Textarea::make('answer')
                                                                        ->label('Trả lời')
                                                                        ->required()
                                                                        ->rows(3)
                                                                        ->placeholder('Câu trả lời ngắn gọn, rõ ràng...')
                                                                        ->columnSpanFull(),
                                                                ])
                                                                ->addActionLabel('Thêm câu hỏi')
                                                                ->reorderable()
                                                                ->collapsible()
                                                                ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                                                                ->defaultItems(0)
                                                                ->columnSpanFull(),
                                                        ])
                                                        ->collapsible(),
                                                ]),
                                        ]),

                                    Tab::make('🇬🇧 English')
                                        ->schema([
                                            Group::make()
                                                ->relationship('geoProfileEn')
                                                ->schema([
                                                    Forms\Components\Hidden::make('locale')
                                                        ->default('en'),

                                                    Section::make('AI Context')
                                                        ->schema([
                                                            Forms\Components\Textarea::make('ai_summary')
                                                                ->label('AI Summary (en)')
                                                                ->hint('Short summary for AI / chatbot understanding of this category')
                                                                ->rows(4)
                                                                ->placeholder('Describe the category in 2–4 sentences: what products, who it\'s for, key highlights...')
                                                                ->columnSpanFull(),

                                                            Forms\Components\Textarea::make('use_cases')
                                                                ->label('Use Cases (en)')
                                                                ->hint('Practical applications — AI uses this to answer "who is this for / where is it used"')
                                                                ->rows(3)
                                                                ->placeholder('E.g. Suitable for residential, commercial, and industrial projects...')
                                                                ->columnSpanFull(),

                                                            Forms\Components\TextInput::make('target_audience')
                                                                ->label('Target Audience (en)')
                                                                ->hint('Target demographic — AI uses this for classification and recommendations')
                                                                ->placeholder('E.g. Electricians, contractors, homeowners...')
                                                                ->columnSpanFull(),

                                                            Forms\Components\Textarea::make('llm_context_hint')
                                                                ->label('LLM Context Hint (en)')
                                                                ->hint('Additional context hint for LLMs when generating content about this category')
                                                                ->rows(2)
                                                                ->columnSpanFull(),
                                                        ]),

                                                    Section::make('Key Facts (en)')
                                                        ->schema([
                                                            Forms\Components\Repeater::make('key_facts')
                                                                ->label('')
                                                                ->schema([
                                                                    Forms\Components\TextInput::make('label')
                                                                        ->label('Label')
                                                                        ->required()
                                                                        ->placeholder('E.g. Products count'),
                                                                    Forms\Components\TextInput::make('value')
                                                                        ->label('Value')
                                                                        ->required()
                                                                        ->placeholder('E.g. 120+'),
                                                                ])
                                                                ->columns(2)
                                                                ->addActionLabel('Add fact')
                                                                ->reorderable()
                                                                ->collapsible()
                                                                ->defaultItems(0)
                                                                ->columnSpanFull(),
                                                        ])
                                                        ->collapsible(),

                                                    Section::make('FAQ (en)')
                                                        ->schema([
                                                            Forms\Components\Repeater::make('faq')
                                                                ->label('')
                                                                ->schema([
                                                                    Forms\Components\TextInput::make('question')
                                                                        ->label('Question')
                                                                        ->required()
                                                                        ->placeholder('E.g. What products does this category include?')
                                                                        ->columnSpanFull(),
                                                                    Forms\Components\Textarea::make('answer')
                                                                        ->label('Answer')
                                                                        ->required()
                                                                        ->rows(3)
                                                                        ->placeholder('Short, clear answer...')
                                                                        ->columnSpanFull(),
                                                                ])
                                                                ->addActionLabel('Add FAQ')
                                                                ->reorderable()
                                                                ->collapsible()
                                                                ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                                                                ->defaultItems(0)
                                                                ->columnSpanFull(),
                                                        ])
                                                        ->collapsible(),
                                                ]),
                                        ]),
                                ]),
                        ]),

                    // ── JSON-LD ───────────────────────────────────────────────────
                    Tab::make('JSON-LD')
                        ->icon('heroicon-o-code-bracket')
                        ->schema([
                            Section::make('Schemas hoạt động như thế nào?')
                                ->schema([
                                    Placeholder::make('jsonld_info')
                                        ->label('')
                                        ->content(new HtmlString('
                                            <ul class="list-disc pl-5 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                                <li>Schemas được tạo tự động bởi <strong>CategoryObserver</strong> mỗi khi lưu danh mục.</li>
                                                <li>Schema đánh dấu <strong>Auto</strong> sẽ bị ghi đè mỗi lần lưu — không sửa payload thủ công.</li>
                                                <li>Để tùy chỉnh payload, tắt <em>Auto Generated</em> trước khi sửa.</li>
                                                <li>Toggle <strong>Active</strong> để bật/tắt schema khỏi <code>&lt;head&gt;</code> của trang.</li>
                                            </ul>
                                        '))
                                        ->columnSpanFull(),
                                ])
                                ->collapsed()
                                ->collapsible(),

                            Tabs::make('JsonldLocaleTabs')
                                ->tabs([
                                    Tab::make('🇻🇳 Tiếng Việt')
                                        ->schema([
                                            Forms\Components\Repeater::make('jsonldSchemasVi')
                                                ->relationship()
                                                ->label('Schemas (vi)')
                                                ->schema([
                                                    Placeholder::make('schema_header')
                                                        ->label('')
                                                        ->content(function ($record): HtmlString {
                                                            if (! $record) {
                                                                return new HtmlString('');
                                                            }
                                                            $type  = is_object($record->schema_type) ? $record->schema_type->value : (string) ($record->schema_type ?? '—');
                                                            $label = e($record->label ?? '');
                                                            $auto  = $record->is_auto_generated
                                                                ? '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:9999px;font-size:0.7rem;font-weight:600;background:#fef9c3;color:#854d0e;">⚡ Auto</span>'
                                                                : '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:9999px;font-size:0.7rem;font-weight:600;background:#dcfce7;color:#166534;">✎ Manual</span>';
                                                            return new HtmlString("
                                                                <div style='display:flex;align-items:center;gap:10px;flex-wrap:wrap;'>
                                                                    <span style='font-weight:700;font-size:0.95rem;color:#1e293b;'>{$type}</span>
                                                                    " . (filled($label) ? "<span style='color:#64748b;font-size:0.85rem;'>— {$label}</span>" : '') . "
                                                                    {$auto}
                                                                </div>
                                                            ");
                                                        })
                                                        ->columnSpanFull(),

                                                    Placeholder::make('payload_preview')
                                                        ->label('Payload (Google reads this)')
                                                        ->content(function ($record): HtmlString {
                                                            if (! $record || empty($record->payload)) {
                                                                return new HtmlString('<em class="text-gray-400">Chưa có payload — lưu danh mục để tạo.</em>');
                                                            }
                                                            $json = json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                                            return new HtmlString(
                                                                '<pre style="white-space:pre-wrap;font-size:0.75rem;line-height:1.6;background:#0f172a;border-radius:6px;padding:14px;color:#e2e8f0;overflow-x:auto;">'
                                                                . e($json)
                                                                . '</pre>'
                                                            );
                                                        })
                                                        ->columnSpanFull(),

                                                    Forms\Components\Toggle::make('is_active')
                                                        ->label('Active (inject vào <head> trang)')
                                                        ->inline(false),

                                                    Placeholder::make('schema_updated_at')
                                                        ->label('Cập nhật lần cuối')
                                                        ->content(fn ($record) => $record?->updated_at
                                                            ? $record->updated_at->diffForHumans() . ' (' . $record->updated_at->format('d/m/Y H:i') . ')'
                                                            : '—'
                                                        ),
                                                ])
                                                ->itemLabel(fn (array $state): ?string =>
                                                    filled($state['schema_type'] ?? '')
                                                        ? (is_object($state['schema_type']) ? $state['schema_type']->value : (string) $state['schema_type'])
                                                        : null
                                                )
                                                ->collapsed()
                                                ->addable(false)
                                                ->deletable(false)
                                                ->reorderable(false)
                                                ->defaultItems(0)
                                                ->columnSpanFull(),

                                            \Filament\Schemas\Components\Actions::make([
                                                \Filament\Actions\Action::make('regenerate_jsonld_vi')
                                                    ->label('Regenerate vi')
                                                    ->icon('heroicon-o-arrow-path')
                                                    ->color('gray')
                                                    ->requiresConfirmation()
                                                    ->modalHeading('Regenerate JSON-LD (vi)')
                                                    ->modalDescription('Re-generate all Auto schemas for the Vietnamese locale. Manual schemas will not be affected.')
                                                    ->action(function ($livewire): void {
                                                        $category = $livewire->record;
                                                        if (! $category?->exists) { return; }
                                                        app(\App\Services\Seo\JsonldService::class)->syncForModel($category, 'vi');
                                                        Notification::make()->title('JSON-LD (vi) đã được regenerate')->success()->send();
                                                        redirect(CategoryResource::getUrl('edit', ['record' => $category]));
                                                    }),
                                            ]),
                                        ]),

                                    Tab::make('🇬🇧 English')
                                        ->schema([
                                            Forms\Components\Repeater::make('jsonldSchemasEn')
                                                ->relationship()
                                                ->label('Schemas (en)')
                                                ->schema([
                                                    Placeholder::make('schema_header')
                                                        ->label('')
                                                        ->content(function ($record): HtmlString {
                                                            if (! $record) {
                                                                return new HtmlString('');
                                                            }
                                                            $type  = is_object($record->schema_type) ? $record->schema_type->value : (string) ($record->schema_type ?? '—');
                                                            $label = e($record->label ?? '');
                                                            $auto  = $record->is_auto_generated
                                                                ? '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:9999px;font-size:0.7rem;font-weight:600;background:#fef9c3;color:#854d0e;">⚡ Auto</span>'
                                                                : '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:9999px;font-size:0.7rem;font-weight:600;background:#dcfce7;color:#166534;">✎ Manual</span>';
                                                            return new HtmlString("
                                                                <div style='display:flex;align-items:center;gap:10px;flex-wrap:wrap;'>
                                                                    <span style='font-weight:700;font-size:0.95rem;color:#1e293b;'>{$type}</span>
                                                                    " . (filled($label) ? "<span style='color:#64748b;font-size:0.85rem;'>— {$label}</span>" : '') . "
                                                                    {$auto}
                                                                </div>
                                                            ");
                                                        })
                                                        ->columnSpanFull(),

                                                    Placeholder::make('payload_preview')
                                                        ->label('Payload (Google reads this)')
                                                        ->content(function ($record): HtmlString {
                                                            if (! $record || empty($record->payload)) {
                                                                return new HtmlString('<em class="text-gray-400">No payload yet — save the category to generate.</em>');
                                                            }
                                                            $json = json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                                            return new HtmlString(
                                                                '<pre style="white-space:pre-wrap;font-size:0.75rem;line-height:1.6;background:#0f172a;border-radius:6px;padding:14px;color:#e2e8f0;overflow-x:auto;">'
                                                                . e($json)
                                                                . '</pre>'
                                                            );
                                                        })
                                                        ->columnSpanFull(),

                                                    Forms\Components\Toggle::make('is_active')
                                                        ->label('Active (inject into <head>)')
                                                        ->inline(false),

                                                    Placeholder::make('schema_updated_at')
                                                        ->label('Last updated')
                                                        ->content(fn ($record) => $record?->updated_at
                                                            ? $record->updated_at->diffForHumans() . ' (' . $record->updated_at->format('d/m/Y H:i') . ')'
                                                            : '—'
                                                        ),
                                                ])
                                                ->itemLabel(fn (array $state): ?string =>
                                                    filled($state['schema_type'] ?? '')
                                                        ? (is_object($state['schema_type']) ? $state['schema_type']->value : (string) $state['schema_type'])
                                                        : null
                                                )
                                                ->collapsed()
                                                ->addable(false)
                                                ->deletable(false)
                                                ->reorderable(false)
                                                ->defaultItems(0)
                                                ->columnSpanFull(),

                                            \Filament\Schemas\Components\Actions::make([
                                                \Filament\Actions\Action::make('regenerate_jsonld_en')
                                                    ->label('Regenerate en')
                                                    ->icon('heroicon-o-arrow-path')
                                                    ->color('gray')
                                                    ->requiresConfirmation()
                                                    ->modalHeading('Regenerate JSON-LD (en)')
                                                    ->modalDescription('Re-generate all Auto schemas for the English locale. Manual schemas will not be affected.')
                                                    ->action(function ($livewire): void {
                                                        $category = $livewire->record;
                                                        if (! $category?->exists) { return; }
                                                        app(\App\Services\Seo\JsonldService::class)->syncForModel($category, 'en');
                                                        Notification::make()->title('JSON-LD (en) regenerated')->success()->send();
                                                        redirect(CategoryResource::getUrl('edit', ['record' => $category]));
                                                    }),
                                            ]),
                                        ]),
                                ]),
                        ]),
                ])
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

    // ── Char counter helpers ──────────────────────────────────────────────────

    private static function charCounter(?string $state, int $min, int $max): string
    {
        $len = mb_strlen($state ?? '');
        return "{$len} / {$max} chars";
    }

    private static function charCounterColor(?string $state, int $min, int $max): string
    {
        $len = mb_strlen($state ?? '');
        if ($len === 0) return 'gray';
        if ($len < $min || $len > $max) return 'warning';
        return 'success';
    }
}
