<?php

namespace App\Filament\Resources;

use App\Enums\BlogPostStatus;
use App\Filament\Resources\BlogPostResource\Pages;
use App\Forms\Components\MediaFileUpload;
use App\Forms\Plugins\MediaRichEditorPlugin;
use App\Models\Author;
use App\Models\BlogPost;
use BackedEnum;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
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
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class BlogPostResource extends Resource
{
    protected static ?string $model = BlogPost::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected static \UnitEnum|string|null $navigationGroup = 'Blog';

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Tabs::make('Tabs')
                ->tabs([

                    // ── Tab 1: General ────────────────────────────────────────
                    Tab::make('General')
                        ->schema([
                            Forms\Components\Select::make('blog_category_id')
                                ->label('Category')
                                ->relationship('blogCategory', 'name')
                                ->searchable()
                                ->preload()
                                ->nullable(),

                            MediaFileUpload::make('featured_image')
                                ->label('Featured Image')
                                ->image()
                                ->nullable()
                                ->columnSpanFull(),

                            Forms\Components\Select::make('tags')
                                ->label('Tags')
                                ->relationship('tags', 'name')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('name')
                                        ->required()
                                        ->live(debounce: 500)
                                        ->afterStateUpdated(fn (Set $set, ?string $state) =>
                                            $set('slug', Str::slug($state ?? ''))
                                        ),
                                    Forms\Components\TextInput::make('slug')
                                        ->required(),
                                ])
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    // ── Tab 2: Content ────────────────────────────────────────
                    Tab::make('Content')
                        ->icon('heroicon-o-language')
                        ->schema([
                            Tabs::make('LocaleTabs')
                                ->tabs([
                                    Tab::make('🇻🇳 Tiếng Việt (vi)')
                                        ->schema([
                                            Forms\Components\TextInput::make('translations.vi.title')
                                                ->label('Tiêu đề (vi)')
                                                ->required()
                                                ->live(onBlur: true)
                                                ->afterStateUpdated(fn ($state, Set $set) =>
                                                    $set('translations.vi.slug', Str::slug($state ?? '')))
                                                ->columnSpanFull(),

                                            Forms\Components\TextInput::make('translations.vi.slug')
                                                ->label('Slug (vi)')
                                                ->helperText('Auto-generated from title. Must be unique per locale.')
                                                ->columnSpanFull(),

                                            Forms\Components\Textarea::make('translations.vi.excerpt')
                                                ->label('Tóm tắt (vi)')
                                                ->rows(3)
                                                ->columnSpanFull(),

                                            Forms\Components\RichEditor::make('translations.vi.body')
                                                ->label('Nội dung (vi)')
                                                ->plugins([MediaRichEditorPlugin::make()])
                                                ->columnSpanFull(),
                                        ]),

                                    Tab::make('🇬🇧 English (en)')
                                        ->schema([
                                            Forms\Components\TextInput::make('translations.en.title')
                                                ->label('Title (en)')
                                                ->live(onBlur: true)
                                                ->afterStateUpdated(fn ($state, Set $set) =>
                                                    $set('translations.en.slug', Str::slug($state ?? '')))
                                                ->columnSpanFull(),

                                            Forms\Components\TextInput::make('translations.en.slug')
                                                ->label('Slug (en)')
                                                ->helperText('Auto-generated from title. Must be unique per locale.')
                                                ->columnSpanFull(),

                                            Forms\Components\Textarea::make('translations.en.excerpt')
                                                ->label('Excerpt (en)')
                                                ->rows(3)
                                                ->columnSpanFull(),

                                            Forms\Components\RichEditor::make('translations.en.body')
                                                ->label('Body (en)')
                                                ->plugins([MediaRichEditorPlugin::make()])
                                                ->columnSpanFull(),
                                        ]),
                                ])
                                ->columnSpanFull(),
                        ]),

                    // ── Tab 3: Publishing ─────────────────────────────────────
                    Tab::make('Publishing')
                        ->schema([
                            Forms\Components\Select::make('status')
                                ->options(collect(BlogPostStatus::cases())->mapWithKeys(
                                    fn (BlogPostStatus $case) => [$case->value => ucfirst($case->value)]
                                ))
                                ->required()
                                ->default(BlogPostStatus::Draft->value),

                            Forms\Components\DateTimePicker::make('published_at')
                                ->nullable(),

                            Forms\Components\Select::make('author_id')
                                ->label('Author')
                                ->relationship('author', 'name')
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->native(false)
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('name')
                                        ->required()
                                        ->live(debounce: 500)
                                        ->afterStateUpdated(fn (Set $set, ?string $state) =>
                                            $set('slug', \Illuminate\Support\Str::slug($state ?? ''))
                                        ),
                                    Forms\Components\TextInput::make('slug')
                                        ->required(),
                                    Forms\Components\TextInput::make('title')
                                        ->label('Job Title'),
                                ])
                                ->placeholder('— Select or create author —'),
                        ])
                        ->columns(2),

                    // ── Tab 3: FAQ ────────────────────────────────────────────
                    Tab::make('FAQ')
                        ->icon('heroicon-o-question-mark-circle')
                        ->schema([
                            Forms\Components\Placeholder::make('faq_hint')
                                ->label('')
                                ->content(new HtmlString(
                                    '<p class="text-sm text-gray-500">'
                                    . 'FAQ items are automatically injected into the <strong>FAQPage JSON-LD schema</strong> '
                                    . 'and the <strong>LLMs document</strong> when the post is published.'
                                    . '</p>'
                                ))
                                ->columnSpanFull(),

                            Section::make('🇻🇳 Tiếng Việt')
                                ->schema([
                                    Forms\Components\Repeater::make('faq_items_vi')
                                        ->label('')
                                        ->schema([
                                            Forms\Components\TextInput::make('question')
                                                ->label('Câu hỏi')
                                                ->required()
                                                ->placeholder('VD: KNX là gì?')
                                                ->columnSpanFull(),

                                            Forms\Components\Textarea::make('answer')
                                                ->label('Trả lời')
                                                ->required()
                                                ->rows(3)
                                                ->columnSpanFull(),
                                        ])
                                        ->afterStateHydrated(function (Forms\Components\Repeater $component, $record): void {
                                            $component->state($record?->faq_items_vi ?? []);
                                        })
                                        ->addActionLabel('Thêm câu hỏi')
                                        ->reorderable()
                                        ->collapsible()
                                        ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                                        ->defaultItems(0)
                                        ->columnSpanFull(),
                                ])
                                ->columnSpanFull(),

                            Section::make('🇬🇧 English')
                                ->schema([
                                    Forms\Components\Repeater::make('faq_items_en')
                                        ->label('')
                                        ->schema([
                                            Forms\Components\TextInput::make('question')
                                                ->label('Question')
                                                ->required()
                                                ->placeholder('e.g. What is KNX?')
                                                ->columnSpanFull(),

                                            Forms\Components\Textarea::make('answer')
                                                ->label('Answer')
                                                ->required()
                                                ->rows(3)
                                                ->columnSpanFull(),
                                        ])
                                        ->afterStateHydrated(function (Forms\Components\Repeater $component, $record): void {
                                            $component->state($record?->faq_items_en ?? []);
                                        })
                                        ->addActionLabel('Add question')
                                        ->reorderable()
                                        ->collapsible()
                                        ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                                        ->defaultItems(0)
                                        ->columnSpanFull(),
                                ])
                                ->columnSpanFull(),
                        ]),

                    // ── Tab 4: SEO ───────────────────────────────────────────
                    Tab::make('SEO')
                        ->icon('heroicon-o-magnifying-glass')
                        ->schema([
                            Tabs::make('SeoLocaleTabs')
                                ->tabs([
                                    Tab::make('🇻🇳 Tiếng Việt')
                                        ->schema([
                                            Placeholder::make('canonical_url_auto_vi')
                                                ->label('Canonical URL (auto-generated)')
                                                ->content(function ($record): string {
                                                    $slug = $record?->translations()->where('locale', 'vi')->value('slug');
                                                    if (! $slug) return '—';
                                                    $record->loadMissing(['blogCategory.translations']);
                                                    return \App\Support\LocaleUrl::forBlogPost($record, 'vi') ?: '—';
                                                }),

                                            Group::make()
                                                ->relationship('seoMetaVi')
                                                ->mutateRelationshipDataBeforeCreateUsing(
                                                    fn (array $data) => ['locale' => 'vi', ...$data]
                                                )
                                                ->schema([
                                                    Section::make('Meta Tags')
                                                        ->schema([
                                                            Forms\Components\TextInput::make('meta_title')
                                                                ->label('Meta Title (vi)')
                                                                ->placeholder('Tự điền từ tiêu đề bài viết')
                                                                ->helperText('Tối ưu: 50–60 ký tự. Google cắt bớt nếu quá dài.')
                                                                ->live(debounce: 500)
                                                                ->hint(fn ($state): string => mb_strlen($state ?? '') . '/60')
                                                                ->hintColor(fn ($state): string => mb_strlen($state ?? '') > 60 ? 'warning' : 'success')
                                                                ->columnSpanFull(),

                                                            Forms\Components\Textarea::make('meta_description')
                                                                ->label('Meta Description (vi)')
                                                                ->placeholder('Mô tả ngắn hiển thị trên Google')
                                                                ->helperText('Tối ưu: 120–155 ký tự. Google cắt bớt nếu quá dài.')
                                                                ->rows(3)
                                                                ->live(debounce: 500)
                                                                ->hint(fn ($state): string => mb_strlen($state ?? '') . '/155')
                                                                ->hintColor(fn ($state): string => mb_strlen($state ?? '') > 155 ? 'warning' : 'success')
                                                                ->columnSpanFull(),

                                                            Forms\Components\TextInput::make('canonical_url')
                                                                ->label('Override canonical URL (chỉ điền nếu syndicated)')
                                                                ->url()
                                                                ->placeholder('Để trống → dùng URL auto ở trên')
                                                                ->columnSpanFull(),

                                                            Forms\Components\Select::make('robots')
                                                                ->label('Robots (vi)')
                                                                ->options([
                                                                    'index,follow'     => 'index, follow — Default',
                                                                    'noindex,follow'   => 'noindex, follow — Exclude from index',
                                                                    'noindex,nofollow' => 'noindex, nofollow — Block completely',
                                                                ])
                                                                ->default('index,follow')
                                                                ->native(false),
                                                        ])
                                                        ->columns(2),

                                                    Section::make('Open Graph (vi)')
                                                        ->schema([
                                                            MediaFileUpload::make('og_image')
                                                                ->label('OG Image (vi)')
                                                                ->helperText('Facebook, Zalo. Recommended: 1200×630px.')
                                                                ->image()
                                                                ->nullable()
                                                                ->columnSpanFull(),

                                                            Forms\Components\TextInput::make('og_title')
                                                                ->label('OG Title (vi)')
                                                                ->placeholder('Tự điền từ Meta Title')
                                                                ->afterStateHydrated(function (Forms\Components\TextInput $component, $state, $record): void {
                                                                    if (blank($state)) {
                                                                        $component->state($record?->meta_title);
                                                                    }
                                                                })
                                                                ->columnSpanFull(),

                                                            Forms\Components\Textarea::make('og_description')
                                                                ->label('OG Description (vi)')
                                                                ->rows(2)
                                                                ->afterStateHydrated(function (Forms\Components\Textarea $component, $state, $record): void {
                                                                    if (blank($state)) {
                                                                        $component->state($record?->meta_description);
                                                                    }
                                                                })
                                                                ->columnSpanFull(),
                                                        ])
                                                        ->columns(2)
                                                        ->collapsible()
                                                        ->collapsed(),
                                                ]),
                                        ]),

                                    Tab::make('🇬🇧 English')
                                        ->schema([
                                            Placeholder::make('canonical_url_auto_en')
                                                ->label('Canonical URL (auto-generated)')
                                                ->content(function ($record): string {
                                                    $slug = $record?->translations()->where('locale', 'en')->value('slug');
                                                    if (! $slug) return '—';
                                                    $record->loadMissing(['blogCategory.translations']);
                                                    return \App\Support\LocaleUrl::forBlogPost($record, 'en') ?: '—';
                                                }),

                                            Group::make()
                                                ->relationship('seoMetaEn')
                                                ->mutateRelationshipDataBeforeCreateUsing(
                                                    fn (array $data) => ['locale' => 'en', ...$data]
                                                )
                                                ->schema([
                                                    Section::make('Meta Tags')
                                                        ->schema([
                                                            Forms\Components\TextInput::make('meta_title')
                                                                ->label('Meta Title (en)')
                                                                ->placeholder('Auto-filled from post title')
                                                                ->helperText('Optimal: 50–60 characters. Google truncates if too long.')
                                                                ->live(debounce: 500)
                                                                ->hint(fn ($state): string => mb_strlen($state ?? '') . '/60')
                                                                ->hintColor(fn ($state): string => mb_strlen($state ?? '') > 60 ? 'warning' : 'success')
                                                                ->columnSpanFull(),

                                                            Forms\Components\Textarea::make('meta_description')
                                                                ->label('Meta Description (en)')
                                                                ->placeholder('Short description shown in Google results')
                                                                ->helperText('Optimal: 120–155 characters. Google truncates if too long.')
                                                                ->rows(3)
                                                                ->live(debounce: 500)
                                                                ->hint(fn ($state): string => mb_strlen($state ?? '') . '/155')
                                                                ->hintColor(fn ($state): string => mb_strlen($state ?? '') > 155 ? 'warning' : 'success')
                                                                ->columnSpanFull(),

                                                            Forms\Components\TextInput::make('canonical_url')
                                                                ->label('Override canonical URL (only if syndicated)')
                                                                ->url()
                                                                ->placeholder('Leave empty → uses auto URL above')
                                                                ->columnSpanFull(),

                                                            Forms\Components\Select::make('robots')
                                                                ->label('Robots (en)')
                                                                ->options([
                                                                    'index,follow'     => 'index, follow — Default',
                                                                    'noindex,follow'   => 'noindex, follow — Exclude from index',
                                                                    'noindex,nofollow' => 'noindex, nofollow — Block completely',
                                                                ])
                                                                ->default('index,follow')
                                                                ->native(false),
                                                        ])
                                                        ->columns(2),

                                                    Section::make('Open Graph (en)')
                                                        ->schema([
                                                            MediaFileUpload::make('og_image')
                                                                ->label('OG Image (en)')
                                                                ->helperText('Facebook, Zalo. Recommended: 1200×630px.')
                                                                ->image()
                                                                ->nullable()
                                                                ->columnSpanFull(),

                                                            Forms\Components\TextInput::make('og_title')
                                                                ->label('OG Title (en)')
                                                                ->placeholder('Auto-filled from Meta Title')
                                                                ->afterStateHydrated(function (Forms\Components\TextInput $component, $state, $record): void {
                                                                    if (blank($state)) {
                                                                        $component->state($record?->meta_title);
                                                                    }
                                                                })
                                                                ->columnSpanFull(),

                                                            Forms\Components\Textarea::make('og_description')
                                                                ->label('OG Description (en)')
                                                                ->rows(2)
                                                                ->afterStateHydrated(function (Forms\Components\Textarea $component, $state, $record): void {
                                                                    if (blank($state)) {
                                                                        $component->state($record?->meta_description);
                                                                    }
                                                                })
                                                                ->columnSpanFull(),
                                                        ])
                                                        ->columns(2)
                                                        ->collapsible()
                                                        ->collapsed(),
                                                ]),
                                        ]),
                                ]),
                        ]),

                    // ── Tab 5: JSON-LD ────────────────────────────────────────
                    Tab::make('JSON-LD')
                        ->icon('heroicon-o-code-bracket')
                        ->schema([

                            Section::make('How JSON-LD schemas work')
                                ->schema([
                                    Placeholder::make('jsonld_info')
                                        ->label('')
                                        ->content(new HtmlString('
                                            <ul class="list-disc pl-5 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                                <li>Schemas marked <strong>Auto</strong> are regenerated every time this post is saved — do not manually edit their payload here.</li>
                                                <li>To customize a payload, go to <strong>SEO &amp; GEO → JSON-LD Schemas</strong> and set <em>Auto Generated = off</em> first.</li>
                                                <li>Toggle <strong>Active</strong> to include / exclude a schema from the page <code>&lt;head&gt;</code>.</li>
                                                <li>Schemas are only generated for <strong>Published</strong> posts.</li>
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
                                                            if (! $record) { return new HtmlString(''); }
                                                            $type  = $record->schema_type?->value ?? '—';
                                                            $label = e($record->label ?? '');
                                                            $auto  = $record->is_auto_generated
                                                                ? '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:9999px;font-size:0.7rem;font-weight:600;background:#fef9c3;color:#854d0e;">⚡ Auto</span>'
                                                                : '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:9999px;font-size:0.7rem;font-weight:600;background:#dcfce7;color:#166534;">✎ Manual</span>';
                                                            return new HtmlString("<div style='display:flex;align-items:center;gap:10px;flex-wrap:wrap;'><span style='font-weight:700;font-size:0.95rem;color:#1e293b;'>{$type}</span>" . (filled($label) ? "<span style='color:#64748b;font-size:0.85rem;'>— {$label}</span>" : '') . "{$auto}</div>");
                                                        })
                                                        ->columnSpanFull(),
                                                    Placeholder::make('payload_preview')
                                                        ->label('Payload (what Google reads)')
                                                        ->content(function ($record): HtmlString {
                                                            if (! $record || empty($record->payload)) {
                                                                return new HtmlString('<em class="text-gray-400">No payload yet — publish the post to generate.</em>');
                                                            }
                                                            $json = json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                                            return new HtmlString('<pre style="white-space:pre-wrap;font-size:0.75rem;line-height:1.6;background:#0f172a;border-radius:6px;padding:14px;color:#e2e8f0;overflow-x:auto;">' . e($json) . '</pre>');
                                                        })
                                                        ->columnSpanFull(),
                                                    Forms\Components\Toggle::make('is_active')
                                                        ->label('Active (inject into page <head>)')
                                                        ->inline(false),
                                                    Placeholder::make('schema_updated_at')
                                                        ->label('Last generated')
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
                                                        $post = $livewire->record;
                                                        if (! $post?->exists) { return; }
                                                        app(\App\Services\Seo\JsonldService::class)->syncForModel($post, 'vi');
                                                        Notification::make()->title('JSON-LD (vi) regenerated')->success()->send();
                                                        redirect(BlogPostResource::getUrl('edit', ['record' => $post]));
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
                                                            if (! $record) { return new HtmlString(''); }
                                                            $type  = $record->schema_type?->value ?? '—';
                                                            $label = e($record->label ?? '');
                                                            $auto  = $record->is_auto_generated
                                                                ? '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:9999px;font-size:0.7rem;font-weight:600;background:#fef9c3;color:#854d0e;">⚡ Auto</span>'
                                                                : '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:9999px;font-size:0.7rem;font-weight:600;background:#dcfce7;color:#166534;">✎ Manual</span>';
                                                            return new HtmlString("<div style='display:flex;align-items:center;gap:10px;flex-wrap:wrap;'><span style='font-weight:700;font-size:0.95rem;color:#1e293b;'>{$type}</span>" . (filled($label) ? "<span style='color:#64748b;font-size:0.85rem;'>— {$label}</span>" : '') . "{$auto}</div>");
                                                        })
                                                        ->columnSpanFull(),
                                                    Placeholder::make('payload_preview')
                                                        ->label('Payload (what Google reads)')
                                                        ->content(function ($record): HtmlString {
                                                            if (! $record || empty($record->payload)) {
                                                                return new HtmlString('<em class="text-gray-400">No payload yet — publish the post to generate.</em>');
                                                            }
                                                            $json = json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                                            return new HtmlString('<pre style="white-space:pre-wrap;font-size:0.75rem;line-height:1.6;background:#0f172a;border-radius:6px;padding:14px;color:#e2e8f0;overflow-x:auto;">' . e($json) . '</pre>');
                                                        })
                                                        ->columnSpanFull(),
                                                    Forms\Components\Toggle::make('is_active')
                                                        ->label('Active (inject into page <head>)')
                                                        ->inline(false),
                                                    Placeholder::make('schema_updated_at')
                                                        ->label('Last generated')
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
                                                        $post = $livewire->record;
                                                        if (! $post?->exists) { return; }
                                                        app(\App\Services\Seo\JsonldService::class)->syncForModel($post, 'en');
                                                        Notification::make()->title('JSON-LD (en) regenerated')->success()->send();
                                                        redirect(BlogPostResource::getUrl('edit', ['record' => $post]));
                                                    }),
                                            ]),
                                        ]),
                                ])
                                ->columnSpanFull(),
                        ])
                        ->hidden(fn ($record) => $record === null),

                    // ── Tab 6: LLMs ───────────────────────────────────────────
                    Tab::make('LLMs')
                        ->icon('heroicon-o-document-text')
                        ->schema([

                            Section::make('How LLMs entries work')
                                ->schema([
                                    Placeholder::make('llms_source_hint')
                                        ->label('')
                                        ->content(new HtmlString('
                                            <ul class="list-disc pl-5 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                                <li>Content is <strong>auto-assembled</strong> from the <strong>FAQ</strong> tab and GEO profile when this post is saved.</li>
                                                <li>To change the output — edit the <strong>FAQ</strong> tab or add a GEO profile, not here.</li>
                                                <li>Use <strong>Regenerate</strong> below to force a re-sync without re-saving the post.</li>
                                                <li>Toggle <strong>Published</strong> to include / exclude from the AI document file.</li>
                                                <li>Entries are only generated for <strong>Published</strong> posts.</li>
                                            </ul>
                                        '))
                                        ->columnSpanFull(),
                                ])
                                ->collapsed()
                                ->collapsible(),

                            Forms\Components\Repeater::make('llmsEntries')
                                ->relationship()
                                ->label('Published Entries')
                                ->schema([

                                    Placeholder::make('llms_preview')
                                        ->label('Preview (llms.txt output)')
                                        ->content(function ($record): HtmlString {
                                            if (! $record) {
                                                return new HtmlString('<em class="text-gray-400">Not generated yet — publish the post to trigger sync.</em>');
                                            }

                                            $lines   = [];
                                            $lines[] = '## ' . e($record->title);
                                            $lines[] = 'URL: ' . e($record->url);

                                            if (filled($record->summary)) {
                                                $lines[] = '';
                                                $lines[] = 'Summary: ' . e($record->summary);
                                            }

                                            if (filled($record->key_facts_text)) {
                                                $lines[] = '';
                                                $lines[] = 'Key Facts:';
                                                $lines[] = e($record->key_facts_text);
                                            }

                                            if (filled($record->faq_text)) {
                                                $lines[] = '';
                                                $lines[] = 'FAQ:';
                                                $lines[] = e($record->faq_text);
                                            }

                                            $content = implode("\n", $lines);

                                            return new HtmlString(
                                                '<pre style="white-space:pre-wrap;font-size:0.8rem;line-height:1.6;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:12px;color:#334155;">'
                                                . $content
                                                . '</pre>'
                                            );
                                        })
                                        ->columnSpanFull(),

                                    Forms\Components\Toggle::make('is_active')
                                        ->label('Published to llms.txt')
                                        ->helperText('Toggle off to exclude this entry from the AI document.')
                                        ->inline(false),

                                    Placeholder::make('updated_at')
                                        ->label('Last synced')
                                        ->content(fn ($record) => $record?->updated_at
                                            ? $record->updated_at->diffForHumans() . ' (' . $record->updated_at->format('d/m/Y H:i') . ')'
                                            : '—'
                                        ),
                                ])
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->defaultItems(0)
                                ->columnSpanFull(),

                            \Filament\Schemas\Components\Actions::make([
                                \Filament\Actions\Action::make('regenerate_llms')
                                    ->label('Regenerate')
                                    ->icon('heroicon-o-arrow-path')
                                    ->color('gray')
                                    ->requiresConfirmation()
                                    ->modalHeading('Regenerate LLMs Entry')
                                    ->modalDescription('This will re-pull data from the FAQ tab and GEO profile and overwrite the current entry. Proceed?')
                                    ->action(function ($livewire): void {
                                        $post = $livewire->record;

                                        if (! $post?->exists) {
                                            return;
                                        }

                                        app(\App\Services\Seo\LlmsGeneratorService::class)->upsertEntry($post);

                                        Notification::make()
                                            ->title('LLMs entry regenerated')
                                            ->body('The entry has been updated successfully.')
                                            ->success()
                                            ->send();

                                        redirect(BlogPostResource::getUrl('edit', ['record' => $post]));
                                    }),
                            ]),
                        ])
                        ->hidden(fn ($record) => $record === null),

                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title_bilingual')
                    ->label('Title')
                    ->html()
                    ->getStateUsing(function ($record): string {
                        $vi = $record->translations->firstWhere('locale', 'vi')?->title;
                        $en = $record->translations->firstWhere('locale', 'en')?->title;
                        $top    = e($vi ?? '—');
                        $bottom = $en ? '<br><span style="font-size:0.75rem;color:#6b7280">' . e($en) . '</span>' : '';
                        return $top . $bottom;
                    })
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('translations', fn ($q) => $q->where('title', 'ilike', "%{$search}%"));
                    })
                    ->wrap(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'secondary' => BlogPostStatus::Draft->value,
                        'success'   => BlogPostStatus::Published->value,
                        'danger'    => BlogPostStatus::Archived->value,
                    ]),

                Tables\Columns\TextColumn::make('blogCategory.name')
                    ->label('Category')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('author.name')
                    ->label('Author')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(timezone: 'Asia/Ho_Chi_Minh')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(BlogPostStatus::cases())->mapWithKeys(
                        fn (BlogPostStatus $case) => [$case->value => ucfirst($case->value)]
                    )),

                Tables\Filters\SelectFilter::make('blog_category_id')
                    ->label('Category')
                    ->relationship('blogCategory', 'name'),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with('translations'))
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
            'index'  => Pages\ListBlogPosts::route('/'),
            'create' => Pages\CreateBlogPost::route('/create'),
            'edit'   => Pages\EditBlogPost::route('/{record}/edit'),
        ];
    }
}
