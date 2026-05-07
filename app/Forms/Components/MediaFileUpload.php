<?php

namespace App\Forms\Components;

use App\Services\Media\MediaUploadService;
use Filament\Forms\Components\FileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class MediaFileUpload extends FileUpload
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->saveUploadedFileUsing(function (TemporaryUploadedFile $file): ?string {
            $media = app(MediaUploadService::class)->upload($file, $this->getCollection());

            return $media->path;
        });

        $this->disk('public');
    }

    /**
     * Collection name defaults to the component field name (e.g. 'featured_image').
     */
    private function getCollection(): string
    {
        return $this->getName() ?? 'default';
    }
}
