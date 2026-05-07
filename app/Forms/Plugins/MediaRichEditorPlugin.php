<?php

namespace App\Forms\Plugins;

use App\Services\Media\MediaFileAttachmentProvider;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\Contracts\FileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\HasFileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;

class MediaRichEditorPlugin implements RichContentPlugin, HasFileAttachmentProvider
{
    public static function make(): static
    {
        return new static();
    }

    public function getFileAttachmentProvider(): ?FileAttachmentProvider
    {
        return new MediaFileAttachmentProvider();
    }

    public function getTipTapPhpExtensions(): array
    {
        return [];
    }

    public function getTipTapJsExtensions(): array
    {
        return [];
    }

    public function getEditorTools(): array
    {
        return [];
    }

    public function getEditorActions(): array
    {
        return [];
    }
}
