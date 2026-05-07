<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $fillable = [
        'model_type',
        'model_id',
        'collection',
        'original_name',
        'hash',
        'title',
        'path',
        'thumb_path',
        'disk',
        'mime_type',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    // ── Computed attributes ───────────────────────────────────────────────────

    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => Storage::disk($this->disk)->url($this->path),
        );
    }

    protected function thumbUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->thumb_path
                ? Storage::disk($this->disk)->url($this->thumb_path)
                : Storage::disk($this->disk)->url($this->path),
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'video/');
    }
}
