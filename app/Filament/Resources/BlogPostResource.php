<?php

namespace App\Filament\Resources;

use App\Enums\BlogPostStatus;
use App\Filament\Resources\BlogPostResource\Pages;
use App\Models\Author;
use App\Models\BlogPost;
use App\Models\Seo\SeoMeta;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
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

                    // ── Tab 1: Content ────────────────────────────────────────
                    Tab::make('Content')
                        ->schema([
                            Forms\Components\Select::make('blog_category_id')
                                ->label('Category')
                                ->relationship('blogCategory', 'name')
                                ->searchable()
                                ->preload()
                                ->nullable(),

                            Forms\Components\TextInput::make('title')
                                ->required()
                                ->live(debounce: 500)
                                ->afterStateUpdated(fn (Set $set, ?string $state) =>
                                    $set('slug', Str::slug($state ?? ''))
                                ),

                            Forms\Components\TextInput::make('slug')
                                ->required()
                                ->unique(table: BlogPost::class, column: 'slug', ignoreRecord: true),

                            Forms\Components\Textarea::make('excerpt')
                                ->rows(3)
                                ->columnSpanFull(),

                            Forms\Components\RichEditor::make('content')
                                ->columnSpanFull(),

                            Forms\Components\FileUpload::make('featured_image')
                                ->label('Featured Image')
                                ->disk('public')
                                ->directory(fn () => 'blog/' . now()->format('Y/m'))
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

                    // ── Tab 2: Publishing ─────────────────────────────────────
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

                            Forms\Components\Repeater::make('faq_items')
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
                                        ->placeholder('Provide a clear, concise answer...')
                                        ->columnSpanFull(),
                                ])
                                ->addActionLabel('Add question')
                                ->reorderable()
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                                ->defaultItems(0)
                                ->columnSpanFull(),
                        ]),

                    // ── Tab 4: SEO Meta ───────────────────────────────────────
                    Tab::make('SEO')
                        ->icon('heroicon-o-magnifying-glass')
                        ->schema([
                            Forms\Components\TextInput::make('seo_meta_title')
                                ->label('Meta Title')
                                ->placeholder('Leave blank to use the post title')
                                ->helperText('Recommended: 50–60 characters. Leave blank and Google will use the post title.')
                                ->maxLength(60)
                                ->columnSpanFull(),

                            Forms\Components\Textarea::make('seo_meta_description')
                                ->label('Meta Description')
                                ->placeholder('Short description shown in Google search results')
                                ->helperText('Recommended: 120–160 characters.')
                                ->rows(3)
                                ->maxLength(160)
                                ->columnSpanFull(),

                            Forms\Components\FileUpload::make('seo_og_image')
                                ->label('OG Image')
                                ->helperText('Used when sharing on Facebook, Twitter, Zalo. Recommended size: 1200 × 630px. Defaults to the featured image if left blank.')
                                ->disk('public')
                                ->directory('seo/og')
                                ->image()
                                ->nullable()
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('seo_canonical_url')
                                ->label('Canonical URL')
                                ->url()
                                ->placeholder('Leave blank to use the post URL automatically')
                                ->helperText('Only set this if the post is syndicated from another source.')
                                ->columnSpanFull(),

                            Forms\Components\Select::make('seo_robots')
                                ->label('Robots')
                                ->options([
                                    'index,follow'     => 'index, follow — Default (Google indexes this page)',
                                    'noindex,follow'   => 'noindex, follow — Exclude from index',
                                    'noindex,nofollow' => 'noindex, nofollow — Block completely',
                                ])
                                ->default('index,follow')
                                ->native(false)
                                ->columnSpanFull(),
                        ]),

                    // ── Tab 4: JSON-LD Preview ────────────────────────────────
                    Tab::make('JSON-LD')
                        ->icon('heroicon-o-code-bracket')
                        ->schema([
                            Forms\Components\Placeholder::make('jsonld_preview')
                                ->label('')
                                ->content(function ($record): HtmlString {
                                    if (! $record) {
                                        return new HtmlString(
                                            '<p class="text-sm text-gray-400 italic">Lưu bài viết trước để xem JSON-LD.</p>'
                                        );
                                    }

                                    $record->loadMissing('activeSchemas');
                                    $schemas = $record->activeSchemas;

                                    if ($schemas->isEmpty()) {
                                        return new HtmlString(
                                            '<p class="text-sm text-gray-400 italic">Chưa có schema nào. Hãy publish bài viết rồi nhấn "Tạo lại JSON-LD".</p>'
                                        );
                                    }

                                    $html = '';
                                    foreach ($schemas as $schema) {
                                        $label = e($schema->label ?? $schema->schema_type);
                                        $badge = match ($schema->schema_type) {
                                            'Article'        => 'bg-blue-100 text-blue-700',
                                            'BreadcrumbList' => 'bg-green-100 text-green-700',
                                            default          => 'bg-gray-100 text-gray-600',
                                        };
                                        $auto = $schema->is_auto_generated
                                            ? '<span class="ml-2 text-xs text-gray-400">auto</span>'
                                            : '<span class="ml-2 text-xs text-amber-500">manual</span>';
                                        $json = e(json_encode(
                                            $schema->payload,
                                            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                                        ));
                                        $html .= <<<HTML
                                            <div class="mb-6">
                                                <div class="flex items-center gap-2 mb-2">
                                                    <span class="text-xs font-semibold px-2 py-0.5 rounded {$badge}">{$label}</span>
                                                    {$auto}
                                                </div>
                                                <pre class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs leading-relaxed overflow-auto max-h-96 font-mono whitespace-pre-wrap">{$json}</pre>
                                            </div>
                                        HTML;
                                    }

                                    return new HtmlString($html);
                                })
                                ->columnSpanFull(),
                        ])
                        ->hidden(fn ($record) => $record === null),

                    // ── Tab 4: LLMs Preview ───────────────────────────────────
                    Tab::make('LLMs')
                        ->icon('heroicon-o-sparkles')
                        ->schema([
                            Forms\Components\Placeholder::make('llms_preview')
                                ->label('')
                                ->content(function ($record): HtmlString {
                                    if (! $record) {
                                        return new HtmlString(
                                            '<p class="text-sm text-gray-400 italic">Lưu bài viết trước để xem LLMs entry.</p>'
                                        );
                                    }

                                    $record->loadMissing('llmsEntries');
                                    $entries = $record->llmsEntries;

                                    if ($entries->isEmpty()) {
                                        return new HtmlString(
                                            '<p class="text-sm text-gray-400 italic">Chưa có LLMs entry. Hãy publish bài viết rồi nhấn "Tạo lại LLMs".</p>'
                                        );
                                    }

                                    $row = fn (string $lbl, ?string $val): string => filled($val)
                                        ? '<div class="mb-4"><p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">'
                                          . e($lbl) . '</p><p class="text-sm text-gray-800 whitespace-pre-wrap">'
                                          . e($val) . '</p></div>'
                                        : '';

                                    $html = '';
                                    foreach ($entries as $entry) {
                                        $statusBadge = $entry->is_active
                                            ? '<span class="text-xs font-semibold px-2 py-0.5 rounded bg-green-100 text-green-700">Active</span>'
                                            : '<span class="text-xs font-semibold px-2 py-0.5 rounded bg-gray-200 text-gray-500">Inactive</span>';

                                        $html .= '<div class="p-4 bg-gray-50 border border-gray-200 rounded-lg">';
                                        $html .= '<div class="mb-4">' . $statusBadge . '</div>';
                                        $html .= $row('Tiêu đề', $entry->title);
                                        $html .= $row('URL', $entry->url);
                                        $html .= $row('Tóm tắt', $entry->summary);
                                        $html .= $row('Key Facts', $entry->key_facts_text);
                                        $html .= $row('FAQ', $entry->faq_text);
                                        $html .= '</div>';
                                    }

                                    return new HtmlString($html);
                                })
                                ->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(50),

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

                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
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
