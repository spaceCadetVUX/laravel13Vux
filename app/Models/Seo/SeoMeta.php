<?php

namespace App\Models\Seo;

use App\Enums\OgType;
use Illuminate\Database\Eloquent\Model;

class SeoMeta extends Model
{
    protected $table = 'seo_meta';

    protected $fillable = [
        'model_type',
        'model_id',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'og_title',
        'og_description',
        'og_image',
        'og_type',
        'twitter_card',
        'twitter_title',
        'twitter_description',
        'canonical_url',
        'robots',
    ];

    protected function casts(): array
    {
        return [
            'og_type' => OgType::class,
        ];
    }
}
