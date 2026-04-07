<?php

namespace App\Models\Seo;

use App\Enums\RedirectType;
use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    protected $fillable = [
        'from_path',
        'to_path',
        'type',
        'hits',
        'cache_version',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type'      => RedirectType::class,
            'is_active' => 'boolean',
        ];
    }
}
