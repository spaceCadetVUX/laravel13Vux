<?php

namespace App\Services\Media;

use App\Models\Media;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\Contracts\FileAttachmentProvider;
use Filament\Forms\Components\RichEditor\RichContentAttribute;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class MediaFileAttachmentProvider implements FileAttachmentProvider
{
    protected RichContentAttribute $attribute;

    public function attribute(RichContentAttribute $attribute): static
    {
        $this->attribute = $attribute;

        return $this;
    }

    /**
     * Upload file through MediaUploadService (hash dedup) and return media ID.
     * The ID is stored in the editor node — resolved back to URL on save.
     */
    public function saveUploadedFileAttachment(TemporaryUploadedFile $file): mixed
    {
        $media = app(MediaUploadService::class)->upload($file, 'rich_content');

        return (string) $media->id;
    }

    /**
     * Resolve stored media ID to public URL when the form saves.
     */
    public function getFileAttachmentUrl(mixed $file): ?string
    {
        if (! $file) {
            return null;
        }

        return Media::find($file)?->url;
    }

    public function getDefaultFileAttachmentVisibility(): ?string
    {
        return 'public';
    }

    /**
     * Never require an existing record — allow uploads on create forms too.
     */
    public function isExistingRecordRequiredToSaveNewFileAttachments(): bool
    {
        return false;
    }

    /**
     * No-op — media belongs to the shared library, not tied to a single record.
     * Deleting the record does not remove media files.
     */
    public function cleanUpFileAttachments(array $exceptIds): void
    {
        // Intentionally empty — media is shared across content.
    }
}
