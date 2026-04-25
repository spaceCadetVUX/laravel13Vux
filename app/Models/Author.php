<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Author extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'title',
        'bio',
        'avatar',
        'website',
        'twitter',
        'linkedin',
        'facebook',
        'expertise',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'expertise' => 'array',
            'is_active' => 'boolean',
        ];
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    /**
     * Full public URL for the avatar image.
     * Returns null when no avatar is set.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar
            ? Storage::disk('public')->url($this->avatar)
            : null;
    }

    /**
     * Collect all non-empty social/web URLs into an array for JSON-LD sameAs.
     *
     * @return string[]
     */
    public function getSameAsAttribute(): array
    {
        return collect([
            $this->website,
            $this->twitter,
            $this->linkedin,
            $this->facebook,
        ])->filter()->values()->all();
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    /** Linked admin account — null for guest authors. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** All blog posts written by this author. */
    public function blogPosts(): HasMany
    {
        return $this->hasMany(BlogPost::class);
    }
}
