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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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

            // ── Basic fields ──────────────────────────────────────────────────────
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

            MediaFileUpload::make('image_path')
                ->label('Category Image')
                ->image()
                ->nullable(),

            Forms\Components\TextInput::make('sort_order')
                ->numeric()
                ->default(0),

            Forms\Components\Toggle::make('is_active')
                ->default(true),

            // ── Translations ──────────────────────────────────────────────────────
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

                                    RichEditor::make('translations.vi.rich_content')
                                        ->label('Nội dung phong phú (vi)')
                                        ->hint('Nội dung dài, có thể chèn ảnh — hiển thị ở phần dưới trang danh mục')
                                        ->hintIcon('heroicon-o-document-text')
                                        ->hintColor('success')
                                        ->plugins([MediaRichEditorPlugin::make()])
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

                                    Forms\Components\TextInput::make('translations.vi.og_title')
                                        ->label('OG Title (vi)')
                                        ->hint('Tiêu đề khi chia sẻ lên Facebook / Zalo. Để trống → dùng Meta Title.')
                                        ->hintIcon('heroicon-o-share')
                                        ->hintColor('primary')
                                        ->maxLength(160)
                                        ->columnSpanFull(),

                                    Forms\Components\Textarea::make('translations.vi.og_description')
                                        ->label('OG Description (vi)')
                                        ->hint('Mô tả khi chia sẻ lên Facebook / Zalo. Để trống → dùng Meta Description.')
                                        ->hintIcon('heroicon-o-share')
                                        ->hintColor('primary')
                                        ->maxLength(320)
                                        ->rows(2)
                                        ->columnSpanFull(),

                                    Forms\Components\TextInput::make('translations.vi.twitter_title')
                                        ->label('Twitter Title (vi)')
                                        ->hint('Tiêu đề khi chia sẻ lên X/Twitter. Để trống → dùng OG Title.')
                                        ->hintIcon('heroicon-o-share')
                                        ->hintColor('primary')
                                        ->maxLength(160)
                                        ->columnSpanFull(),

                                    Forms\Components\Textarea::make('translations.vi.twitter_description')
                                        ->label('Twitter Description (vi)')
                                        ->hint('Mô tả khi chia sẻ lên X/Twitter. Để trống → dùng OG Description.')
                                        ->hintIcon('heroicon-o-share')
                                        ->hintColor('primary')
                                        ->maxLength(320)
                                        ->rows(2)
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

                                    Forms\Components\TextInput::make('translations.en.og_title')
                                        ->label('OG Title (en)')
                                        ->hint('Title when shared on Facebook / LinkedIn. Leave blank to use Meta Title.')
                                        ->hintIcon('heroicon-o-share')
                                        ->hintColor('primary')
                                        ->maxLength(160)
                                        ->columnSpanFull(),

                                    Forms\Components\Textarea::make('translations.en.og_description')
                                        ->label('OG Description (en)')
                                        ->hint('Description when shared on Facebook / LinkedIn. Leave blank to use Meta Description.')
                                        ->hintIcon('heroicon-o-share')
                                        ->hintColor('primary')
                                        ->maxLength(320)
                                        ->rows(2)
                                        ->columnSpanFull(),

                                    Forms\Components\TextInput::make('translations.en.twitter_title')
                                        ->label('Twitter Title (en)')
                                        ->hint('Title when shared on X/Twitter. Leave blank to use OG Title.')
                                        ->hintIcon('heroicon-o-share')
                                        ->hintColor('primary')
                                        ->maxLength(160)
                                        ->columnSpanFull(),

                                    Forms\Components\Textarea::make('translations.en.twitter_description')
                                        ->label('Twitter Description (en)')
                                        ->hint('Description when shared on X/Twitter. Leave blank to use OG Description.')
                                        ->hintIcon('heroicon-o-share')
                                        ->hintColor('primary')
                                        ->maxLength(320)
                                        ->rows(2)
                                        ->columnSpanFull(),
                                ]),
                        ])
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->columnSpanFull(),

            // ── SEO ───────────────────────────────────────────────────────────────
            Section::make('SEO')
                ->icon('heroicon-o-magnifying-glass')
                ->description('Các trường dùng chung cho tất cả ngôn ngữ. Tiêu đề / mô tả OG+Twitter theo ngôn ngữ → nhập trong tab Translations.')
                ->schema([
                    MediaFileUpload::make('seo_og_image')
                        ->label('OG Image')
                        ->helperText('Ảnh hiển thị khi chia sẻ lên Facebook, Zalo, LinkedIn. Khuyến nghị 1200 × 630px.')
                        ->image()
                        ->nullable()
                        ->columnSpanFull(),

                    Forms\Components\Select::make('seo_og_type')
                        ->label('OG Type')
                        ->options(collect(OgType::cases())->mapWithKeys(
                            fn (OgType $case) => [$case->value => ucfirst($case->value)]
                        ))
                        ->default(OgType::Website->value)
                        ->native(false),

                    Forms\Components\Select::make('seo_twitter_card')
                        ->label('Twitter Card')
                        ->options([
                            'summary'             => 'Summary — tiêu đề + mô tả + ảnh nhỏ',
                            'summary_large_image' => 'Summary Large Image — ảnh to chiếm chỗ',
                        ])
                        ->default('summary_large_image')
                        ->native(false),

                    Forms\Components\Select::make('seo_robots')
                        ->label('Robots')
                        ->options([
                            'index,follow'     => 'index, follow — Default (Google index trang này)',
                            'noindex,follow'   => 'noindex, follow — Không index',
                            'noindex,nofollow' => 'noindex, nofollow — Chặn hoàn toàn',
                        ])
                        ->default('index,follow')
                        ->native(false),

                    Forms\Components\TextInput::make('seo_canonical_url')
                        ->label('Canonical URL')
                        ->url()
                        ->placeholder('Để trống → tự dùng URL của trang danh mục')
                        ->helperText('Chỉ điền khi trang này có nội dung trùng lặp từ nguồn khác.')
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->collapsible()
                ->columnSpanFull(),

            // ── GEO / AI ──────────────────────────────────────────────────────────
            Section::make('GEO & AI')
                ->icon('heroicon-o-cpu-chip')
                ->description('Dữ liệu này dùng cho LLMs.txt, AI chatbot context, và câu hỏi thường gặp (FAQPage JSON-LD).')
                ->schema([
                    Forms\Components\Textarea::make('geo_ai_summary')
                        ->label('AI Summary (vi)')
                        ->hint('Đoạn tóm tắt ngắn cho AI / chatbot hiểu danh mục này')
                        ->rows(4)
                        ->placeholder('Mô tả 2–4 câu về danh mục: sản phẩm nào có trong đó, đối tượng khách hàng, điểm nổi bật...')
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('geo_use_cases')
                        ->label('Use Cases (vi)')
                        ->hint('Ứng dụng thực tế — AI dùng để trả lời "danh mục này phù hợp cho ai / dùng ở đâu"')
                        ->rows(3)
                        ->placeholder('VD: Phù hợp cho nhà ở, văn phòng, công trình thương mại...')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('geo_target_audience')
                        ->label('Target Audience (vi)')
                        ->hint('Đối tượng mục tiêu — AI dùng để phân loại và gợi ý')
                        ->placeholder('VD: Kỹ sư điện, nhà thầu, hộ gia đình...')
                        ->columnSpanFull(),

                    Forms\Components\Repeater::make('geo_key_facts')
                        ->label('Key Facts (vi)')
                        ->hint('Các sự kiện quan trọng — hiển thị dạng list, AI dùng để trích dẫn')
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

                    Forms\Components\Repeater::make('geo_faq')
                        ->label('FAQ (vi)')
                        ->hint('Câu hỏi thường gặp — được inject vào FAQPage JSON-LD và LLMs.txt')
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
                ->collapsible()
                ->collapsed()
                ->columnSpanFull(),

            // ── JSON-LD ───────────────────────────────────────────────────────────
            Section::make('JSON-LD')
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
                                        <li>Để tùy chỉnh payload, vào <strong>SEO &amp; GEO → JSON-LD Schemas</strong> và tắt <em>Auto Generated</em> trước.</li>
                                        <li>Toggle <strong>Active</strong> để bật/tắt schema khỏi <code>&lt;head&gt;</code> của trang.</li>
                                    </ul>
                                '))
                                ->columnSpanFull(),
                        ])
                        ->collapsed()
                        ->collapsible(),

                    Forms\Components\Repeater::make('jsonldSchemas')
                        ->relationship()
                        ->label('Schemas')
                        ->schema([
                            Placeholder::make('schema_header')
                                ->label('')
                                ->content(function ($record): HtmlString {
                                    if (! $record) {
                                        return new HtmlString('');
                                    }

                                    $type  = is_object($record->schema_type)
                                        ? $record->schema_type->value
                                        : (string) ($record->schema_type ?? '—');
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

                                    $json = json_encode(
                                        $record->payload,
                                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                                    );

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
                                ? (is_object($state['schema_type'])
                                    ? $state['schema_type']->value
                                    : (string) $state['schema_type'])
                                : null
                        )
                        ->collapsed()
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed()
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
