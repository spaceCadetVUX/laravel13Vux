<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogCategoryTranslation extends Model
{
    protected $fillable = [
        'blog_category_id',
        'locale',
        'is_mcp_protected',
        'name',
        'slug',
        'description',
        'rich_content',
    ];

    protected function casts(): array
    {
        return [
            'is_mcp_protected' => 'boolean',
        ];
    }

    public function blogCategory(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class);
    }
}
