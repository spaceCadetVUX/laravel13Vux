<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BusinessProfileResource\Pages;
use App\Models\BusinessProfile;
use App\Models\Seo\LlmsDocument;
use App\Services\Seo\BusinessJsonldService;
use BackedEnum;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class BusinessProfileResource extends Resource
{
    protected static ?string $model = BusinessProfile::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static \UnitEnum|string|null $navigationGroup = 'Setting';

    protected static ?string $navigationLabel = 'Business Profile';

    // ── Form ──────────────────────────────────────────────────────────────────

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Tabs::make('Tabs')
                ->tabs([

                    // ── Identity ──────────────────────────────────────────────
                    Tab::make('Identity')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Business Name')
                                ->required()
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('legal_name')
                                ->label('🇻🇳 Legal Name')
                                ->placeholder('Công ty Cổ phần...'),

                            Forms\Components\TextInput::make('extra.legal_name_en')
                                ->label('🇬🇧 Legal Name (EN)')
                                ->placeholder('Company Ltd.'),

                            Forms\Components\TextInput::make('tagline')
                                ->label('🇻🇳 Tagline')
                                ->placeholder('Khẩu hiệu ngắn'),

                            Forms\Components\TextInput::make('extra.tagline_en')
                                ->label('🇬🇧 Tagline (EN)')
                                ->placeholder('Short slogan in English'),

                            Forms\Components\Textarea::make('description')
                                ->label('🇻🇳 Description')
                                ->rows(4)
                                ->columnSpanFull(),

                            Forms\Components\Textarea::make('extra.description_en')
                                ->label('🇬🇧 Description (EN)')
                                ->rows(4)
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('logo_path')
                                ->label('Logo Path / URL')
                                ->columnSpanFull(),

                            Forms\Components\FileUpload::make('extra.og_image')
                                ->label('Default OG Image (Facebook / Zalo share)')
                                ->helperText('Ảnh hiển thị khi share link trang chủ. Khuyến nghị: 1200×630px.')
                                ->image()
                                ->disk('public')
                                ->directory('og')
                                ->imagePreviewHeight('120')
                                ->columnSpanFull(),

                            Forms\Components\FileUpload::make('extra.favicon')
                                ->label('Favicon')
                                ->helperText('Hiển thị trên tab trình duyệt và kết quả Google Search. Yêu cầu: file .ico hoặc PNG tối thiểu 48×48px. SVG không được Google Search hỗ trợ.')
                                ->image()
                                ->disk('public')
                                ->directory('favicon')
                                ->acceptedFileTypes(['image/x-icon', 'image/vnd.microsoft.icon', 'image/png', 'image/gif'])
                                ->imagePreviewHeight('80')
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('currency')
                                ->label('Currency')
                                ->default('VND'),

                            Forms\Components\TextInput::make('founded_year')
                                ->label('Founded Year')
                                ->numeric()
                                ->minValue(1800)
                                ->maxValue(now()->year),

                            Forms\Components\TextInput::make('vat_number')
                                ->label('VAT / Tax Number'),
                        ])
                        ->columns(2),

                    // ── Contact ───────────────────────────────────────────────
                    Tab::make('Contact & Location')
                        ->schema([
                            Forms\Components\TextInput::make('email')
                                ->label('Email')
                                ->email(),

                            Forms\Components\TextInput::make('phone')
                                ->label('Phone')
                                ->tel(),

                            Forms\Components\Textarea::make('address_line')
                                ->label('Address')
                                ->rows(2)
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('city')
                                ->label('City'),

                            Forms\Components\TextInput::make('state')
                                ->label('State / Province'),

                            Forms\Components\TextInput::make('country')
                                ->label('Country'),

                            Forms\Components\TextInput::make('postal_code')
                                ->label('Postal Code'),

                            Forms\Components\TextInput::make('latitude')
                                ->label('Latitude')
                                ->numeric()
                                ->placeholder('10.7769'),

                            Forms\Components\TextInput::make('longitude')
                                ->label('Longitude')
                                ->numeric()
                                ->placeholder('106.7009'),
                        ])
                        ->columns(2),

                    // ── Online ────────────────────────────────────────────────
                    Tab::make('Online Presence')
                        ->schema([
                            Forms\Components\KeyValue::make('social_links')
                                ->label('Social Links')
                                ->keyLabel('Platform')
                                ->valueLabel('URL')
                                ->keyPlaceholder('facebook')
                                ->valuePlaceholder('https://facebook.com/...')
                                ->reorderable()
                                ->columnSpanFull(),

                            Forms\Components\Repeater::make('business_hours')
                                ->label('Business Hours')
                                ->helperText('Giờ mở cửa theo ngày — tự động đưa vào JSON-LD openingHours.')
                                ->schema([
                                    Forms\Components\Select::make('day')
                                        ->label('Day')
                                        ->options([
                                            'Monday'    => 'Monday',
                                            'Tuesday'   => 'Tuesday',
                                            'Wednesday' => 'Wednesday',
                                            'Thursday'  => 'Thursday',
                                            'Friday'    => 'Friday',
                                            'Saturday'  => 'Saturday',
                                            'Sunday'    => 'Sunday',
                                        ])
                                        ->required()
                                        ->columnSpan(1),
                                    Forms\Components\TextInput::make('open')
                                        ->label('Open')
                                        ->placeholder('08:00')
                                        ->columnSpan(1),
                                    Forms\Components\TextInput::make('close')
                                        ->label('Close')
                                        ->placeholder('17:30')
                                        ->columnSpan(1),
                                ])
                                ->columns(3)
                                ->reorderable()
                                ->addActionLabel('Thêm ngày')
                                ->columnSpanFull(),

                            Forms\Components\Repeater::make('extra.faq')
                                ->label('🇻🇳 FAQ — Tiếng Việt (schema.org FAQPage)')
                                ->helperText('Câu hỏi thường gặp — inject JSON-LD FAQPage vào trang chủ /vi/.')
                                ->schema([
                                    Forms\Components\TextInput::make('question')
                                        ->label('Câu hỏi')
                                        ->required()
                                        ->columnSpanFull(),
                                    Forms\Components\Textarea::make('answer')
                                        ->label('Trả lời')
                                        ->required()
                                        ->rows(3)
                                        ->columnSpanFull(),
                                ])
                                ->reorderable()
                                ->addActionLabel('+ Thêm câu hỏi')
                                ->columnSpanFull(),

                            Forms\Components\Repeater::make('extra.faq_en')
                                ->label('🇬🇧 FAQ — English (schema.org FAQPage)')
                                ->helperText('Frequently asked questions — injected into JSON-LD FAQPage on /en/ homepage.')
                                ->schema([
                                    Forms\Components\TextInput::make('question')
                                        ->label('Question')
                                        ->required()
                                        ->columnSpanFull(),
                                    Forms\Components\Textarea::make('answer')
                                        ->label('Answer')
                                        ->required()
                                        ->rows(3)
                                        ->columnSpanFull(),
                                ])
                                ->reorderable()
                                ->addActionLabel('+ Add Q&A')
                                ->columnSpanFull(),
                        ]),

                    // ── JSON-LD ───────────────────────────────────────────────
                    Tab::make('JSON-LD')
                        ->icon('heroicon-o-code-bracket')
                        ->schema([
                            Section::make('Live Schemas')
                                ->description('Auto-generated từ BusinessProfile data. Cache 24h Redis.')
                                ->schema([
                                    Placeholder::make('jsonld_preview')
                                        ->label('')
                                        ->content(function (): HtmlString {
                                            $service = app(BusinessJsonldService::class);
                                            $html = '';
                                            foreach (['vi' => '🇻🇳 VI', 'en' => '🇬🇧 EN'] as $locale => $label) {
                                                $html .= "<h3 style='font-size:0.9rem;font-weight:700;color:#1e293b;margin:24px 0 8px;border-bottom:2px solid #e2e8f0;padding-bottom:6px;'>{$label}</h3>";
                                                foreach ($service->getSchemas($locale) as $schema) {
                                                    $type = htmlspecialchars($schema['@type'] ?? 'Unknown');
                                                    $json = htmlspecialchars(json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                                                    $html .= "<p style='font-weight:600;font-size:0.85rem;color:#1e293b;margin:12px 0 4px;'>{$type}</p>";
                                                    $html .= "<pre style='white-space:pre-wrap;font-size:0.72rem;line-height:1.6;background:#0f172a;border-radius:6px;padding:14px;color:#e2e8f0;overflow-x:auto;'>{$json}</pre>";
                                                }
                                            }
                                            return new HtmlString($html ?: '<em>No schemas generated.</em>');
                                        })
                                        ->columnSpanFull(),
                                ]),

                            \Filament\Schemas\Components\Actions::make([
                                \Filament\Actions\Action::make('flush_jsonld_cache')
                                    ->label('Flush Cache')
                                    ->icon('heroicon-o-arrow-path')
                                    ->color('warning')
                                    ->requiresConfirmation()
                                    ->modalHeading('Flush JSON-LD Cache')
                                    ->modalDescription('Xoá Redis cache của Business schemas. Request tiếp theo sẽ rebuild từ DB.')
                                    ->action(function (): void {
                                        app(BusinessJsonldService::class)->flushCache();
                                        Notification::make()->title('JSON-LD cache flushed')->success()->send();
                                    }),
                            ]),
                        ]),

                    // ── LLMs ──────────────────────────────────────────────────
                    Tab::make('LLMs')
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            Section::make('LLMs Documents')
                                ->description('Nội dung tổng hợp dùng cho llms.txt — AI context documents.')
                                ->schema([
                                    Placeholder::make('llms_vi')
                                        ->label('🇻🇳 business-vi')
                                        ->content(function (): HtmlString {
                                            $doc = LlmsDocument::where('slug', 'business-vi')->first();
                                            if (! $doc) {
                                                return new HtmlString('<em class="text-gray-400">Document not found.</em>');
                                            }
                                            $file    = 'llms/' . $doc->slug . '.txt';
                                            $content = \Illuminate\Support\Facades\Storage::disk('public')->exists($file)
                                                ? htmlspecialchars(\Illuminate\Support\Facades\Storage::disk('public')->get($file))
                                                : '';
                                            $updated = $doc->last_generated_at
                                                ? \Carbon\Carbon::parse($doc->last_generated_at)->format('d/m/Y H:i')
                                                : '—';
                                            return new HtmlString(
                                                "<div style='font-size:0.75rem;color:#64748b;margin-bottom:6px;'>Last generated: {$updated}</div>"
                                                . "<pre style='white-space:pre-wrap;font-size:0.72rem;line-height:1.6;background:#0f172a;border-radius:6px;padding:14px;color:#e2e8f0;overflow-x:auto;max-height:320px;'>"
                                                . ($content ?: '<em style="color:#94a3b8;">(empty — nhấn Regenerate để tạo)</em>')
                                                . '</pre>'
                                            );
                                        })
                                        ->columnSpanFull(),

                                    Placeholder::make('llms_en')
                                        ->label('🇬🇧 business-en')
                                        ->content(function (): HtmlString {
                                            $doc = LlmsDocument::where('slug', 'business-en')->first();
                                            if (! $doc) {
                                                return new HtmlString('<em class="text-gray-400">Document not found.</em>');
                                            }
                                            $file    = 'llms/' . $doc->slug . '.txt';
                                            $content = \Illuminate\Support\Facades\Storage::disk('public')->exists($file)
                                                ? htmlspecialchars(\Illuminate\Support\Facades\Storage::disk('public')->get($file))
                                                : '';
                                            $updated = $doc->last_generated_at
                                                ? \Carbon\Carbon::parse($doc->last_generated_at)->format('d/m/Y H:i')
                                                : '—';
                                            return new HtmlString(
                                                "<div style='font-size:0.75rem;color:#64748b;margin-bottom:6px;'>Last generated: {$updated}</div>"
                                                . "<pre style='white-space:pre-wrap;font-size:0.72rem;line-height:1.6;background:#0f172a;border-radius:6px;padding:14px;color:#e2e8f0;overflow-x:auto;max-height:320px;'>"
                                                . ($content ?: '<em style="color:#94a3b8;">(empty — nhấn Regenerate để tạo)</em>')
                                                . '</pre>'
                                            );
                                        })
                                        ->columnSpanFull(),
                                ]),

                            \Filament\Schemas\Components\Actions::make([
                                \Filament\Actions\Action::make('regenerate_llms')
                                    ->label('Regenerate LLMs')
                                    ->icon('heroicon-o-arrow-path')
                                    ->color('gray')
                                    ->requiresConfirmation()
                                    ->modalHeading('Regenerate Business LLMs Documents')
                                    ->modalDescription('Generate lại business-vi.txt và business-en.txt từ dữ liệu BusinessProfile hiện tại.')
                                    ->action(function (): void {
                                        $service = app(\App\Services\Seo\LlmsGeneratorService::class);
                                        $docs = LlmsDocument::where('is_active', true)
                                            ->where(fn ($q) => $q->where('slug', 'business')
                                                ->orWhere('slug', 'like', 'business-%'))
                                            ->get();
                                        foreach ($docs as $doc) {
                                            $service->generateDocument($doc);
                                        }
                                        Notification::make()->title('LLMs documents regenerated')->success()->send();
                                        redirect(BusinessProfileResource::getUrl());
                                    }),
                            ]),
                        ]),

                ])
                ->columnSpanFull(),
        ]);
    }

    // ── Table (not used — singleton) ──────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table->columns([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\EditBusinessProfile::route('/'),
        ];
    }
}
