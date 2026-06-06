<?php

namespace App\Services\Media;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaUploadService
{
    private const THUMB_WIDTH   = 400;
    private const DISK          = 'public';
    private const IMAGE_TYPES   = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /**
     * Upload a file with hash-based deduplication.
     *
     * Same file content (identical bytes) → returns existing Media record, no new file saved.
     * Different content, same filename     → saves as new file, no conflict.
     */
    public function upload(UploadedFile $file, string $collection = 'default'): Media
    {
        $hash = hash_file('sha256', $file->getRealPath());

        $existing = Media::where('hash', $hash)->first();
        if ($existing) {
            return $existing;
        }

        // Snapshot all metadata BEFORE storeAs — it moves the temp file,
        // making getRealPath() invalid for any call that follows.
        $realPath     = $file->getRealPath();
        $originalName = $file->getClientOriginalName();
        $mimeType     = $file->getMimeType();
        $extension    = $file->getClientOriginalExtension();
        $fileSize     = (int) filesize($realPath);

        $directory = 'media/' . now()->format('Y/m');
        $slugBase  = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: substr($hash, 0, 16);
        $filename  = $slugBase . '.' . $extension;

        // Collision: same name, different content → append short hash suffix
        if (Storage::disk(self::DISK)->exists($directory . '/' . $filename)) {
            $filename = $slugBase . '-' . substr($hash, 0, 8) . '.' . $extension;
        }

        $path = $file->storeAs($directory, $filename, self::DISK);

        // Build thumbnail from the already-stored path, not the temp location.
        $thumbPath = null;
        if (in_array($mimeType, self::IMAGE_TYPES)) {
            $storedPath = Storage::disk(self::DISK)->path($path);
            $thumbPath  = $this->createThumbnail($storedPath, $directory, $hash, $mimeType);
        }

        return Media::create([
            'collection'    => $collection,
            'original_name' => $originalName,
            'hash'          => $hash,
            'title'         => pathinfo($originalName, PATHINFO_FILENAME),
            'path'          => $path,
            'thumb_path'    => $thumbPath,
            'disk'          => self::DISK,
            'mime_type'     => $mimeType,
            'size'          => $fileSize,
        ]);
    }

    /**
     * Create a thumbnail capped at THUMB_WIDTH using PHP GD.
     * Returns the relative storage path, or null if GD is unavailable.
     */
    private function createThumbnail(string $sourcePath, string $directory, string $hash, string $mimeType): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $source = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png'  => @imagecreatefrompng($sourcePath),
            'image/gif'  => @imagecreatefromgif($sourcePath),
            'image/webp' => @imagecreatefromwebp($sourcePath),
            default      => null,
        };

        if (! $source) {
            return null;
        }

        $origW = imagesx($source);
        $origH = imagesy($source);

        if ($origW <= self::THUMB_WIDTH) {
            imagedestroy($source);
            return null;
        }

        $thumbH = (int) round($origH * self::THUMB_WIDTH / $origW);
        $thumb  = imagecreatetruecolor(self::THUMB_WIDTH, $thumbH);

        if ($mimeType === 'image/png') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }

        imagecopyresampled($thumb, $source, 0, 0, 0, 0, self::THUMB_WIDTH, $thumbH, $origW, $origH);

        $thumbDir      = $directory . '/thumbs';
        $thumbFilename = $hash . '_thumb.webp';
        $thumbFullPath = Storage::disk(self::DISK)->path($thumbDir . '/' . $thumbFilename);

        if (! is_dir(dirname($thumbFullPath))) {
            mkdir(dirname($thumbFullPath), 0755, true);
        }

        imagewebp($thumb, $thumbFullPath, 85);
        imagedestroy($source);
        imagedestroy($thumb);

        return $thumbDir . '/' . $thumbFilename;
    }
}
