<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaResource\Pages;
use App\Models\Media;
use BackedEnum;
use App\Services\Media\MediaUploadService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Actions\BulkAction;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static BackedEnum|string|null $navigationIcon  = 'heroicon-o-photo';
    protected static ?string               $navigationLabel = 'Media Library';
    protected static \UnitEnum|string|null $navigationGroup = 'Content';
    protected static ?int                  $navigationSort  = 10;

    public static function table(Table $table): Table
    {
        return $table
            ->contentGrid(['sm' => 2, 'md' => 3, 'xl' => 4])
            ->defaultPaginationPageOption(30)
            ->paginationPageOptions([30, 60, 120])
            ->modifyQueryUsing(fn (Builder $query) => $query->latest())
            ->recordClasses('rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 shadow-sm bg-white dark:bg-gray-800')
            ->columns([
                Stack::make([
                    ImageColumn::make('thumb_path')
                        ->disk('public')
                        ->height(180)
                        ->extraAttributes(['style' => 'display:block;width:100%;overflow:hidden;line-height:0;'])
                        ->extraImgAttributes([
                            'loading' => 'lazy',
                            'style'   => 'width:100%;height:180px;object-fit:cover;display:block;',
                        ])
                        ->defaultImageUrl(fn (Media $record): ?string => $record->isImage()
                            ? Storage::disk('public')->url($record->path)
                            : null
                        ),

                    Panel::make([
                        TextColumn::make('display_name')
                            ->state(fn (Media $record): string => $record->title ?: $record->original_name)
                            ->limit(24)
                            ->weight(FontWeight::Medium)
                            ->alignment(\Filament\Support\Enums\Alignment::Center)
                            ->extraAttributes(['class' => 'truncate w-full text-center block'])
                            ->searchable(query: function (Builder $query, string $search): Builder {
                                return $query->where(function (Builder $q) use ($search) {
                                    $q->where('title', 'like', "%{$search}%")
                                      ->orWhere('original_name', 'like', "%{$search}%");
                                });
                            }),

                        TextColumn::make('size')
                            ->formatStateUsing(fn (?int $state): string => $state
                                ? ($state >= 1_048_576
                                    ? number_format($state / 1_048_576, 1) . ' MB'
                                    : number_format($state / 1024, 1) . ' KB')
                                : '—'
                            )
                            ->alignment(\Filament\Support\Enums\Alignment::Center)
                            ->color('gray'),
                    ])->extraAttributes(['class' => 'px-3 py-2 space-y-0.5 text-center overflow-hidden']),
                ])->extraAttributes(['class' => 'h-full']),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Loại file')
                    ->options([
                        'image'    => 'Hình ảnh',
                        'video'    => 'Video',
                        'document' => 'Tài liệu',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'image'    => $query->where('mime_type', 'like', 'image/%'),
                            'video'    => $query->where('mime_type', 'like', 'video/%'),
                            'document' => $query->where('mime_type', 'not like', 'image/%')
                                               ->where('mime_type', 'not like', 'video/%'),
                            default    => $query,
                        };
                    }),
            ])
            ->actions([
                // Copy URL — opens modal with readonly URL input for easy copy
                Action::make('copyLink')
                    ->label('Copy link')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->fillForm(fn (Media $record): array => ['url' => $record->url])
                    ->form([
                        TextInput::make('url')
                            ->label('URL')
                            ->readOnly()
                            ->extraInputAttributes([
                                'x-ref'         => 'urlInput',
                                'x-on:click'    => 'navigator.clipboard.writeText($el.value)',
                            ])
                            ->helperText('Click vào URL để copy'),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Đóng'),

                // Delete — check usage before removing from disk
                Action::make('delete')
                    ->label('Xóa')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Xóa file')
                    ->modalDescription(fn (Media $record): string =>
                        "Xóa \"{$record->original_name}\"? Hành động này không thể hoàn tác."
                    )
                    ->action(function (Media $record): void {
                        if (static::isInUse($record)) {
                            Notification::make()
                                ->danger()
                                ->title('Không thể xóa')
                                ->body('File đang được sử dụng trong nội dung. Vui lòng xóa khỏi nội dung trước.')
                                ->persistent()
                                ->send();

                            return;
                        }

                        Storage::disk($record->disk)->delete($record->path);

                        if ($record->thumb_path) {
                            Storage::disk($record->disk)->delete($record->thumb_path);
                        }

                        $record->delete();

                        Notification::make()
                            ->success()
                            ->title('Đã xóa file')
                            ->send();
                    }),
            ])
            ->headerActions([
                // Upload new files through MediaUploadService (hash dedup)
                Action::make('upload')
                    ->label('Upload')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        FileUpload::make('files')
                            ->label('Chọn file')
                            ->multiple()
                            ->storeFiles(false)
                            ->acceptedFileTypes(['image/*', 'video/*', 'application/pdf'])
                            ->maxSize(20480)
                            ->helperText('Tối đa 20MB/file. Chấp nhận: hình ảnh, video, PDF.')
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $service = app(MediaUploadService::class);
                        $count   = 0;

                        foreach (Arr::wrap($data['files']) as $file) {
                            if ($file instanceof TemporaryUploadedFile) {
                                $service->upload($file);
                                $count++;
                            }
                        }

                        Notification::make()
                            ->success()
                            ->title("Upload thành công {$count} file")
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('deleteSelected')
                    ->label('Xóa đã chọn')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (\Illuminate\Support\Collection $records): void {
                        $blocked = 0;
                        $deleted = 0;

                        foreach ($records as $record) {
                            if (static::isInUse($record)) {
                                $blocked++;
                                continue;
                            }

                            Storage::disk($record->disk)->delete($record->path);
                            if ($record->thumb_path) {
                                Storage::disk($record->disk)->delete($record->thumb_path);
                            }
                            $record->delete();
                            $deleted++;
                        }

                        if ($deleted > 0) {
                            Notification::make()->success()->title("Đã xóa {$deleted} file")->send();
                        }
                        if ($blocked > 0) {
                            Notification::make()->warning()->title("{$blocked} file đang được dùng, không thể xóa")->send();
                        }
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMedia::route('/'),
        ];
    }

    /**
     * Check if a media file's path is referenced in any rich content field.
     * Add new models/columns here as rich_content is expanded to other models.
     */
    private static function isInUse(Media $record): bool
    {
        $path = $record->path;

        return \App\Models\BlogPostTranslation::where('body', 'like', "%{$path}%")->exists()
            || \App\Models\CategoryTranslation::where('rich_content', 'like', "%{$path}%")->exists()
            || \App\Models\ProductTranslation::where('description', 'like', "%{$path}%")->exists();
    }
}
