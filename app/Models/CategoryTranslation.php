<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryTranslation extends Model
{
    protected $fillable = [
        'category_id',
        'locale',
        'name',
        'slug',
        'description',
        'rich_content',
        'meta_title',
        'meta_description',
        'og_title',
        'og_description',
        'twitter_title',
        'twitter_description',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
