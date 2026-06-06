<?php

namespace App\Filament\Resources;

use App\Enums\OgType;
use App\Filament\Resources\BrandResource\Pages;
use App\Support\LocaleUrl;
use App\Models\Brand;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
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
            Tabs::make('BrandTabs')
                ->tabs([

                    // ── General ───────────────────────────────────────────────────
                    Tab::make('General')
                        ->icon('heroicon-o-cog-6-tooth')
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
                                ->default(0)
                                ->helperText('Lower = appears first. Drag to reorder in the list view.')
                                ->minValue(0),

                            Forms\Components\Toggle::make('is_active')
                                ->default(true),
                        ])
                        ->columns(2),

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
                                                                ->placeholder('Tự điền từ tên thương hiệu')
                                                                ->hint(fn (?string $state): string => self::charCounter($state, 50, 70))
                                                                ->hintColor(fn (?string $state): string => self::charCounterColor($state, 50, 70))
                                                                ->helperText('Tối ưu: 50–70 ký tự.')
                                                                ->afterStateHydrated(function ($state, $set, $livewire): void {
                                                                    if (empty($state) && $livewire->record?->name) {
                                                                        $set('meta_title', $livewire->record->name);
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
                                                                    if (empty($state) && $livewire->record?->description) {
                                                                        $set('meta_description', $livewire->record->description);
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
                                                                ->placeholder('Tự tạo từ slug — /vi/thuong-hieu/{slug}')
                                                                ->hint('Tự tạo từ slug')
                                                                ->hintIcon('heroicon-o-sparkles')
                                                                ->hintColor('info')
                                                                ->afterStateHydrated(function ($state, $set, $livewire): void {
                                                                    if (empty($state) && $livewire->record?->slug) {
                                                                        $set('canonical_url', LocaleUrl::for('brand', $livewire->record->slug, 'vi'));
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
                                                                        $set('og_title', $record?->meta_title ?? $livewire->record?->name);
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
                                                                ->placeholder('Tự điền từ logo thương hiệu')
                                                                ->hint('Tự điền từ logo thương hiệu')
                                                                ->hintIcon('heroicon-o-sparkles')
                                                                ->hintColor('info')
                                                                ->helperText('Khuyến nghị: 1200×630px.')
                                                                ->afterStateHydrated(function ($state, $set, $livewire): void {
                                                                    if (empty($state) && $livewire->record?->logo) {
                                                                        $set('og_image', asset('storage/' . $livewire->record->logo));
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
                                                                        $set('twitter_title', $record?->meta_title ?? $livewire->record?->name);
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
                                                                ->placeholder('Auto-filled from brand name')
                                                                ->hint(fn (?string $state): string => self::charCounter($state, 50, 70))
                                                                ->hintColor(fn (?string $state): string => self::charCounterColor($state, 50, 70))
                                                                ->helperText('Optimal: 50–70 chars.')
                                                                ->afterStateHydrated(function ($state, $set, $livewire): void {
                                                                    if (empty($state) && $livewire->record?->name) {
                                                                        $set('meta_title', $livewire->record->name);
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
                                                                    if (empty($state) && $livewire->record?->description) {
                                                                        $set('meta_description', $livewire->record->description);
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
                                                                ->placeholder('Auto-generated from slug — /en/brands/{slug}')
                                                                ->hint('Auto-generated from slug')
                                                                ->hintIcon('heroicon-o-sparkles')
                                                                ->hintColor('info')
                                                                ->afterStateHydrated(function ($state, $set, $livewire): void {
                                                                    if (empty($state) && $livewire->record?->slug) {
                                                                        $set('canonical_url', LocaleUrl::for('brand', $livewire->record->slug, 'en'));
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
                                                                        $set('og_title', $record?->meta_title ?? $livewire->record?->name);
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
                                                                ->placeholder('Auto-filled from brand logo')
                                                                ->hint('Auto-filled from brand logo')
                                                                ->hintIcon('heroicon-o-sparkles')
                                                                ->hintColor('info')
                                                                ->helperText('Recommended: 1200×630px.')
                                                                ->afterStateHydrated(function ($state, $set, $livewire): void {
                                                                    if (empty($state) && $livewire->record?->logo) {
                                                                        $set('og_image', asset('storage/' . $livewire->record->logo));
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
                                                                        $set('twitter_title', $record?->meta_title ?? $livewire->record?->name);
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
                                                                ->hint('Đoạn tóm tắt ngắn cho AI / chatbot hiểu thương hiệu này')
                                                                ->rows(4)
                                                                ->placeholder('Mô tả 2–4 câu về thương hiệu: sản phẩm gì, xuất xứ, điểm nổi bật...')
                                                                ->columnSpanFull(),

                                                            Forms\Components\Textarea::make('use_cases')
                                                                ->label('Use Cases (vi)')
                                                                ->hint('Ứng dụng thực tế — AI dùng để trả lời "thương hiệu này phù hợp cho ai"')
                                                                ->rows(3)
                                                                ->placeholder('VD: Phù hợp cho công trình dân dụng, công nghiệp...')
                                                                ->columnSpanFull(),

                                                            Forms\Components\TextInput::make('target_audience')
                                                                ->label('Target Audience (vi)')
                                                                ->hint('Đối tượng mục tiêu — AI dùng để phân loại và gợi ý')
                                                                ->placeholder('VD: Kỹ sư điện, nhà thầu, hộ gia đình...')
                                                                ->columnSpanFull(),

                                                            Forms\Components\Textarea::make('llm_context_hint')
                                                                ->label('LLM Context Hint (vi)')
                                                                ->hint('Gợi ý thêm cho LLM khi sinh nội dung về thương hiệu')
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
                                                                        ->placeholder('VD: Năm thành lập'),
                                                                    Forms\Components\TextInput::make('value')
                                                                        ->label('Giá trị')
                                                                        ->required()
                                                                        ->placeholder('VD: 1995'),
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
                                                                        ->placeholder('VD: Thương hiệu này xuất xứ từ đâu?')
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
                                                                ->hint('Short summary for AI / chatbot understanding of this brand')
                                                                ->rows(4)
                                                                ->placeholder('Describe the brand in 2–4 sentences: products, origin, key highlights...')
                                                                ->columnSpanFull(),

                                                            Forms\Components\Textarea::make('use_cases')
                                                                ->label('Use Cases (en)')
                                                                ->hint('Practical applications — AI uses this to answer "who is this brand for"')
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
                                                                ->hint('Additional context hint for LLMs when generating content about this brand')
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
                                                                        ->placeholder('E.g. Founded'),
                                                                    Forms\Components\TextInput::make('value')
                                                                        ->label('Value')
                                                                        ->required()
                                                                        ->placeholder('E.g. 1995'),
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
                                                                        ->placeholder('E.g. Where is this brand from?')
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
                                                <li>Schemas được tạo tự động bởi <strong>BrandObserver</strong> mỗi khi lưu thương hiệu.</li>
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
                                                                return new HtmlString('<em class="text-gray-400">Chưa có payload — lưu thương hiệu để tạo.</em>');
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
                                                                return new HtmlString('<em class="text-gray-400">No payload yet — save the brand to generate.</em>');
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
            ->reorderable('sort_order')
            ->modifyQueryUsing(fn ($query) => $query->orderBy('sort_order')->orderBy('name'))
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
                    ->url(fn (Brand $record): ?string => $record->website ?: null)
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
                    ->label('Order')
                    ->sortable()
                    ->alignCenter()
                    ->width('80px'),
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
