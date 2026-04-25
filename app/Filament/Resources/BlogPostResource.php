<?php

namespace App\Filament\Resources;

use App\Enums\BlogPostStatus;
use App\Filament\Resources\BlogPostResource\Pages;
use App\Models\Author;
use App\Models\BlogPost;
use App\Models\Seo\SeoMeta;
use BackedEnum;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
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
                                        ->label('Payload (what Google reads)')
                                        ->content(function ($record): HtmlString {
                                            if (! $record || empty($record->payload)) {
                                                return new HtmlString('<em class="text-gray-400">No payload yet — publish the post to generate.</em>');
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

                            \Filament\Schemas\Components\Actions::make([
                                \Filament\Actions\Action::make('regenerate_jsonld')
                                    ->label('Regenerate all schemas')
                                    ->icon('heroicon-o-arrow-path')
                                    ->color('gray')
                                    ->requiresConfirmation()
                                    ->modalHeading('Regenerate JSON-LD Schemas')
                                    ->modalDescription('This will re-generate all Auto schemas from the current post data. Manual schemas will not be affected.')
                                    ->action(function ($livewire): void {
                                        $post = $livewire->record;

                                        if (! $post?->exists) {
                                            return;
                                        }

                                        app(\App\Services\Seo\JsonldService::class)->syncForModel($post);

                                        Notification::make()
                                            ->title('JSON-LD schemas regenerated')
                                            ->body('All auto schemas have been updated.')
                                            ->success()
                                            ->send();

                                        redirect(BlogPostResource::getUrl('edit', ['record' => $post]));
                                    }),
                            ]),
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
