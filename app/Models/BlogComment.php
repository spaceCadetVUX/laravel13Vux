<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogComment extends Model
{
    // ── PK config (bigint auto-increment) ─────────────────────────────────────

    protected $keyType    = 'int';
    public    $incrementing = true;

    // ── Mass assignment ───────────────────────────────────────────────────────

    protected $fillable = [
        'blog_post_id',
        'user_id',
        'body',
        'is_approved',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'is_approved' => 'boolean',
        ];
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    /** Nullable — comment is preserved if the user account is deleted (SET NULL). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
