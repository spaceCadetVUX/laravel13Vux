<?php

namespace App\Filament\Resources;

use App\Enums\BlogPostStatus;
use App\Filament\Resources\BlogPostResource\Pages;
use App\Models\BlogPost;
use App\Models\User;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
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
                                ->options(
                                    User::role('admin')->pluck('name', 'id')
                                )
                                ->searchable()
                                ->nullable(),
                        ])
                        ->columns(2),

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
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
