<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BlogTag extends Model
{
    use HasFactory;
    // ── PK config (bigint auto-increment) ─────────────────────────────────────

    protected $keyType    = 'int';
    public    $incrementing = true;

    // ── Mass assignment ───────────────────────────────────────────────────────

    protected $fillable = [
        'name',
        'slug',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(
            BlogPost::class,
            'blog_post_tag',      // pivot table
            'blog_tag_id',        // FK on pivot pointing to this model
            'blog_post_id'        // FK on pivot pointing to the related model
        );
    }
}
