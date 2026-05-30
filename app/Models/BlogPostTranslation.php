<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogPostTranslation extends Model
{
    protected $fillable = [
        'blog_post_id',
        'locale',
        'is_mcp_protected',
        'title',
        'slug',
        'excerpt',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'is_mcp_protected' => 'boolean',
        ];
    }

    public function blogPost(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class);
    }
}
