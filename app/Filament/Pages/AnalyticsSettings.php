<?php

namespace App\Filament\Pages;

use App\Models\BusinessProfile;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class AnalyticsSettings extends Page
{
    protected static BackedEnum|string|null $navigationIcon  = 'heroicon-o-chart-bar';
    protected static \UnitEnum|string|null  $navigationGroup = 'Setting';
    protected static ?string               $navigationLabel = 'Analytics & Search Console';
    protected static ?int                  $navigationSort  = 99;

    protected string $view = 'filament.pages.analytics-settings';

    // ── Form state ────────────────────────────────────────────────────────────

    public ?array $data = [];

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $extra = (array) (BusinessProfile::instance()->extra ?? []);

        $this->form->fill([
            'ga4_id'           => $extra['ga4_id']           ?? null,
            'gtm_id'           => $extra['gtm_id']           ?? null,
            'gsc_meta'         => $extra['gsc_meta']         ?? null,
            'ga4_active'       => (bool) ($extra['ga4_active'] ?? true),
            'default_og_image' => $extra['default_og_image'] ?? null,
        ]);
    }

    // ── Form schema ───────────────────────────────────────────────────────────

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([

                Section::make('Default OG Image')
                    ->icon('heroicon-o-photo')
                    ->description('Ảnh mặc định khi share trang web lên Facebook, Zalo, Telegram... Khuyến nghị 1200×630px, ≤1MB.')
                    ->schema([
                        FileUpload::make('default_og_image')
                            ->label('OG Image')
                            ->image()
                            ->disk('public')
                            ->directory('og')
                            ->imagePreviewHeight('180')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(2048)
                            ->helperText('Định dạng: JPG, PNG, WebP. Kích thước tối đa 2MB. Tỉ lệ lý tưởng 1.91:1 (1200×630px).')
                            ->columnSpanFull(),
                    ]),

                Section::make('Google Analytics 4')
                    ->icon('heroicon-o-chart-bar')
                    ->description('Measurement ID từ GA4 Property → Data Streams.')
                    ->schema([
                        TextInput::make('ga4_id')
                            ->label('Measurement ID')
                            ->placeholder('G-XXXXXXXXXX')
                            ->helperText('Tìm tại GA4 → Admin → Data Streams → chọn stream → Measurement ID.')
                            ->columnSpan(1),

                        TextInput::make('gtm_id')
                            ->label('GTM Container ID (optional)')
                            ->placeholder('GTM-XXXXXXX')
                            ->helperText('Nếu dùng Google Tag Manager thay vì nhúng GA4 trực tiếp.')
                            ->columnSpan(1),

                        Toggle::make('ga4_active')
                            ->label('Enable GA4 tracking')
                            ->helperText('Tắt để disable script trên frontend mà không cần xoá ID.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Google Search Console')
                    ->icon('heroicon-o-magnifying-glass')
                    ->description('Xác minh quyền sở hữu website với Google Search Console.')
                    ->schema([
                        TextInput::make('gsc_meta')
                            ->label('Verification Meta Content')
                            ->placeholder('abc123xyz...')
                            ->helperText('Chỉ lấy phần content="..." trong thẻ meta. Tìm tại GSC → Settings → Ownership verification → HTML tag.')
                            ->live(debounce: 400)
                            ->columnSpanFull(),

                        Placeholder::make('gsc_preview')
                            ->label('Thẻ sẽ inject vào <head>')
                            ->content(function (): HtmlString {
                                $val = $this->data['gsc_meta'] ?? null;
                                return new HtmlString(
                                    filled($val)
                                        ? '<code style="font-size:0.8rem;background:#f1f5f9;padding:6px 10px;border-radius:4px;display:block;">'
                                          . e('<meta name="google-site-verification" content="' . $val . '">')
                                          . '</code>'
                                        : '<em style="color:#94a3b8;">Chưa có verification code.</em>'
                                );
                            })
                            ->columnSpanFull(),
                    ]),

            ])
            ->statePath('data');
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Lưu cài đặt')
                ->icon('heroicon-o-check')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $profile = BusinessProfile::instance();
        $extra   = (array) ($profile->extra ?? []);

        $extra['ga4_id']           = filled($data['ga4_id'])   ? trim($data['ga4_id'])   : null;
        $extra['gtm_id']           = filled($data['gtm_id'])   ? trim($data['gtm_id'])   : null;
        $extra['gsc_meta']         = filled($data['gsc_meta']) ? trim($data['gsc_meta']) : null;
        $extra['ga4_active']       = (bool) ($data['ga4_active'] ?? true);
        $extra['default_og_image'] = $data['default_og_image'] ?? null;

        $profile->extra = $extra;
        $profile->saveQuietly();

        Notification::make()
            ->title('Đã lưu settings')
            ->success()
            ->send();
    }
}
