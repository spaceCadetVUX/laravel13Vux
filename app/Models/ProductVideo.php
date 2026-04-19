<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductVideo extends Model
{
    // ── Model events ──────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::deleted(function (ProductVideo $video): void {
            if ($video->path && Storage::disk('public')->exists($video->path)) {
                Storage::disk('public')->delete($video->path);
            }

            if ($video->thumbnail_path && Storage::disk('public')->exists($video->thumbnail_path)) {
                Storage::disk('public')->delete($video->thumbnail_path);
            }
        });
    }

    protected $fillable = [
        'product_id',
        'path',
        'thumbnail_path',
        'title',
        'description',
        'duration',
        'sort_order',
    ];

    // ── Computed attributes ───────────────────────────────────────────────────

    /** Full public URL for the video file (read-only). */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => Storage::url($this->path),
        );
    }

    /** Full public URL for the video thumbnail — null if no thumbnail set. */
    protected function thumbnailUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->thumbnail_path
                ? Storage::url($this->thumbnail_path)
                : null,
        );
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
