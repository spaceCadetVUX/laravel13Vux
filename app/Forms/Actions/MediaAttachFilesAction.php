<?php

namespace App\Forms\Actions;

use App\Services\Media\MediaUploadService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class MediaAttachFilesAction
{
    /**
     * Replace the built-in 'attachFiles' action on all RichEditor instances.
     *
     * Uses RichEditor::configureUsing + fileAttachmentsAction to REPLACE the
     * default action entirely. This avoids the silent-override problem where
     * Filament's own fluent chain (called after make()) overwrites anything
     * set via Action::configureUsing().
     *
     * Tab 1: Upload từ PC → MediaUploadService → hash dedup → insert permanent URL
     * Tab 2: Chèn bằng URL → insert any URL directly, no upload
     */
    public static function register(): void
    {
        RichEditor::configureUsing(function (RichEditor $editor): void {
            $editor
                ->resizableImages()
                ->registerActions([
                Action::make('attachFiles')
                    ->modalHeading(fn (array $arguments): string => filled($arguments['src'] ?? null)
                        ? 'Chỉnh sửa ảnh'
                        : 'Chèn ảnh'
                    )
                    ->modalWidth(Width::Large)
                    ->fillForm(fn (array $arguments): array => [
                        'alt'     => $arguments['alt'] ?? null,
                        'src_url' => $arguments['src'] ?? null,
                        'width'   => $arguments['width'] ?? null,
                    ])
                    ->schema(fn (array $arguments, RichEditor $component): array => [
                        // Current image preview — only shown when editing an existing image
                        Placeholder::make('_preview')
                            ->label('Ảnh hiện tại')
                            ->content(new HtmlString(
                                '<img src="' . e($arguments['src'] ?? '') . '"'
                                . ' style="max-height:180px;max-width:100%;border-radius:8px;border:1px solid #e5e7eb;display:block;" />'
                            ))
                            ->columnSpanFull()
                            ->visible(filled($arguments['src'] ?? null)),

                        Tabs::make('source')
                            ->tabs([
                                Tab::make('Upload từ PC')
                                    ->icon('heroicon-o-arrow-up-tray')
                                    ->schema([
                                        FileUpload::make('file')
                                            ->label(filled($arguments['src'] ?? null)
                                                ? 'Thay bằng file mới (để trống để giữ nguyên)'
                                                : 'Chọn file ảnh')
                                            ->acceptedFileTypes($component->getFileAttachmentsAcceptedFileTypes() ?? ['image/*'])
                                            ->maxSize($component->getFileAttachmentsMaxSize() ?? 20480)
                                            ->storeFiles(false)
                                            ->image(),
                                    ]),

                                Tab::make('Chèn bằng URL')
                                    ->icon('heroicon-o-link')
                                    ->schema([
                                        TextInput::make('src_url')
                                            ->label('URL ảnh')
                                            ->placeholder('https://...')
                                            ->url()
                                            ->helperText('Dán URL ảnh từ Media Library hoặc bất kỳ nguồn ngoài nào'),
                                    ]),
                            ])
                            ->columnSpanFull(),

                        TextInput::make('alt')
                            ->label('Văn bản thay thế (alt text)')
                            ->placeholder('Mô tả ảnh (tốt cho SEO)')
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        // Width control — only meaningful when editing an existing node
                        TextInput::make('width')
                            ->label('Chiều rộng')
                            ->placeholder('ví dụ: 300px hoặc 50%')
                            ->helperText('Để trống = giữ nguyên kích thước')
                            ->columnSpanFull()
                            ->visible(filled($arguments['src'] ?? null)),
                    ])
                    ->action(function (
                        array      $arguments,
                        array      $data,
                        RichEditor $component,
                        Component  $livewire,
                    ): void {
                        $id  = null;
                        $src = null;

                        // ── Tab 1: Upload từ PC ───────────────────────────────
                        if ($data['file'] instanceof TemporaryUploadedFile) {
                            $media = app(MediaUploadService::class)->upload(
                                $data['file'],
                                'rich_content',
                            );

                            $id  = (string) $media->id;
                            $src = $media->url;
                        }

                        // ── Tab 2: Chèn bằng URL ──────────────────────────────
                        elseif (filled($data['src_url'] ?? null)) {
                            $src = $data['src_url'];
                            // No $id — FileAttachmentProvider skips this node on
                            // save, keeping the URL intact as an external reference.
                        }

                        // When editing, keeping the existing src is valid (user only changed alt)
                        if (blank($src) && filled($arguments['src'] ?? null)) {
                            $src = $arguments['src'];
                            $id  = $arguments['id'] ?? null;
                        }

                        if (blank($src)) {
                            Notification::make()
                                ->warning()
                                ->title('Chưa có ảnh')
                                ->body('Vui lòng chọn file hoặc nhập URL ảnh.')
                                ->send();

                            return;
                        }

                        // ── Update existing image node ────────────────────────
                        if (filled($arguments['src'] ?? null)) {
                            if ($arguments['editorSelection']['type'] !== 'node') {
                                $arguments['editorSelection']['type'] = 'node';
                                $arguments['editorSelection']['anchor']--;
                                unset($arguments['editorSelection']['head']);
                            }

                            $id  ??= $arguments['id'] ?? null;
                            $src ??= $arguments['src'];

                            $attrs = [
                                'alt' => $data['alt'] ?? null,
                                'id'  => $id,
                                'src' => $src,
                            ];

                            if (filled($data['width'] ?? null)) {
                                $attrs['style'] = 'width:' . $data['width'] . ';max-width:100%;';
                            }

                            $component->runCommands(
                                [
                                    EditorCommand::make('updateAttributes', arguments: [
                                        'image',
                                        $attrs,
                                    ]),
                                ],
                                editorSelection: $arguments['editorSelection'],
                            );

                            return;
                        }

                        // ── Insert new image node ─────────────────────────────
                        $component->runCommands(
                            [
                                EditorCommand::make('insertContent', arguments: [[
                                    'type'  => 'image',
                                    'attrs' => [
                                        'alt' => $data['alt'] ?? null,
                                        'id'  => $id,
                                        'src' => $src,
                                    ],
                                ]]),
                            ],
                            editorSelection: $arguments['editorSelection'],
                        );
                    }),
            ]);
        });
    }
}
