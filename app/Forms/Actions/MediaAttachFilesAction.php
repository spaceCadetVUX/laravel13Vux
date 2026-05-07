<?php

namespace App\Forms\Actions;

use App\Services\Media\MediaUploadService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class MediaAttachFilesAction
{
    /**
     * Override the built-in 'attachFiles' action on all RichEditor instances.
     *
     * Tab 1: Upload từ PC → MediaUploadService → hash dedup → insert permanent URL
     * Tab 2: URL từ Media Library → insert directly, no upload
     */
    public static function register(): void
    {
        Action::configureUsing(function (Action $action): void {
            if ($action->getName() !== 'attachFiles') {
                return;
            }

            $action
                ->modalHeading('Chèn ảnh')
                ->modalWidth(Width::Large)
                ->fillForm(fn (array $arguments): array => [
                    'alt'     => $arguments['alt'] ?? null,
                    'src_url' => $arguments['src'] ?? null,
                ])
                ->schema(fn (array $arguments, RichEditor $component): array => [
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
                                        ->required(
                                            blank($arguments['src'] ?? null)
                                            && blank(request()->input('data.src_url'))
                                        )
                                        ->image(),
                                ]),

                            Tab::make('URL từ Media Library')
                                ->icon('heroicon-o-link')
                                ->schema([
                                    TextInput::make('src_url')
                                        ->label('URL ảnh')
                                        ->placeholder('https://example.com/storage/media/...')
                                        ->url()
                                        ->helperText('Copy link từ Media Library và paste vào đây'),
                                ]),
                        ])
                        ->columnSpanFull(),

                    TextInput::make('alt')
                        ->label('Alt text')
                        ->placeholder('Mô tả ảnh (tốt cho SEO)')
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ])
                ->action(function (
                    array     $arguments,
                    array     $data,
                    RichEditor $component,
                    Component  $livewire,
                ): void {
                    $id  = null;
                    $src = null;

                    // ── Tab 1: Upload từ PC ───────────────────────────────────
                    if ($data['file'] instanceof TemporaryUploadedFile) {
                        $media = app(MediaUploadService::class)->upload(
                            $data['file'],
                            'rich_content',
                        );

                        $id  = (string) $media->id;
                        $src = $media->url;
                    }

                    // ── Tab 2: URL từ Media Library ───────────────────────────
                    elseif (filled($data['src_url'] ?? null)) {
                        $src = $data['src_url'];
                        // No $id → FileAttachmentProvider skips this node on save,
                        // keeping the URL intact as a plain external reference.
                    }

                    // Nothing provided — abort silently
                    if (blank($src)) {
                        return;
                    }

                    // ── Update existing image node ────────────────────────────
                    if (filled($arguments['src'] ?? null)) {
                        if ($arguments['editorSelection']['type'] !== 'node') {
                            $arguments['editorSelection']['type'] = 'node';
                            $arguments['editorSelection']['anchor']--;
                            unset($arguments['editorSelection']['head']);
                        }

                        $id  ??= $arguments['id'] ?? null;
                        $src ??= $arguments['src'];

                        $component->runCommands(
                            [
                                EditorCommand::make('updateAttributes', arguments: [
                                    'image',
                                    [
                                        'alt' => $data['alt'] ?? null,
                                        'id'  => $id,
                                        'src' => $src,
                                    ],
                                ]),
                            ],
                            editorSelection: $arguments['editorSelection'],
                        );

                        return;
                    }

                    // ── Insert new image node ─────────────────────────────────
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
                });
        });
    }
}
