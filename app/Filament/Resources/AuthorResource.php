<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuthorResource\Pages;
use App\Models\Author;
use App\Models\User;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class AuthorResource extends Resource
{
    protected static ?string $model = Author::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user-circle';

    protected static \UnitEnum|string|null $navigationGroup = 'Blog';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([

            // ── Identity ──────────────────────────────────────────────────────
            Section::make('Identity')
                ->description('Basic author information shown on the blog and used in JSON-LD Person schema.')
                ->icon('heroicon-o-user')
                ->schema([
                    Forms\Components\FileUpload::make('avatar')
                        ->label('Profile Photo')
                        ->disk('public')
                        ->directory('authors')
                        ->image()
                        ->imageEditor()
                        ->imagePreviewHeight('100')
                        ->nullable()
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->live(debounce: 500)
                        ->afterStateUpdated(fn (Set $set, ?string $state) =>
                            $set('slug', Str::slug($state ?? ''))
                        )
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->unique(table: Author::class, column: 'slug', ignoreRecord: true)
                        ->rules(['regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'])
                        ->helperText('URL: /authors/{slug}')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('title')
                        ->label('Job Title')
                        ->placeholder('e.g. Senior Editor, KNX Systems Engineer')
                        ->helperText('Shown on author page and in JSON-LD jobTitle')
                        ->columnSpan(2),
                ])
                ->columns(3),

            // ── Bio ───────────────────────────────────────────────────────────
            Section::make('Bio')
                ->icon('heroicon-o-document-text')
                ->schema([
                    Forms\Components\Textarea::make('bio')
                        ->label('Short Bio')
                        ->rows(4)
                        ->helperText('Displayed on the author page. Injected into Article JSON-LD as author description.')
                        ->columnSpanFull(),

                    Forms\Components\TagsInput::make('expertise')
                        ->label('Areas of Expertise')
                        ->placeholder('Add topic and press Enter')
                        ->helperText('e.g. Smart Home, KNX, DALI, IoT — helps Google understand author authority')
                        ->columnSpanFull(),
                ]),

            // ── Social presence ───────────────────────────────────────────────
            Section::make('Social & Web Presence')
                ->description('These URLs are used in JSON-LD Person.sameAs — helps Google link this author to their verified identity.')
                ->icon('heroicon-o-globe-alt')
                ->schema([
                    Forms\Components\TextInput::make('website')
                        ->label('Personal Website')
                        ->url()
                        ->placeholder('https://...')
                        ->prefixIcon('heroicon-o-globe-alt'),

                    Forms\Components\TextInput::make('linkedin')
                        ->label('LinkedIn')
                        ->url()
                        ->placeholder('https://linkedin.com/in/...')
                        ->prefixIcon('heroicon-o-link'),

                    Forms\Components\TextInput::make('twitter')
                        ->label('X / Twitter')
                        ->url()
                        ->placeholder('https://x.com/...')
                        ->prefixIcon('heroicon-o-at-symbol'),

                    Forms\Components\TextInput::make('facebook')
                        ->label('Facebook')
                        ->url()
                        ->placeholder('https://facebook.com/...')
                        ->prefixIcon('heroicon-o-link'),
                ])
                ->columns(2),

            // ── Account link ──────────────────────────────────────────────────
            Section::make('Admin Account')
                ->description('Optional — link this author profile to an admin user account. Guest authors (external contributors) do not need an account.')
                ->icon('heroicon-o-key')
                ->schema([
                    Forms\Components\Select::make('user_id')
                        ->label('Linked Account')
                        ->options(User::query()->pluck('name', 'id'))
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->placeholder('— Guest author (no account) —'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->helperText('Inactive authors are hidden from the storefront'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->label('')
                    ->disk('public')
                    ->circular()
                    ->size(40),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('title')
                    ->label('Job Title')
                    ->searchable()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('blog_posts_count')
                    ->label('Posts')
                    ->counts('blogPosts')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->color('gray'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAuthors::route('/'),
            'create' => Pages\CreateAuthor::route('/create'),
            'edit'   => Pages\EditAuthor::route('/{record}/edit'),
        ];
    }
}
