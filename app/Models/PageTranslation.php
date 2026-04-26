<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PageTranslation extends Model
{
    // Standalone — không có parent model, không dùng FK
    protected $fillable = [
        'page_key',
        'locale',
        'title',
        'slug',
        'body',
        'meta_title',
        'meta_description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForLocale(Builder $query, string $locale): Builder
    {
        return $query->where('locale', $locale);
    }

    public function scopeByKey(Builder $query, string $key): Builder
    {
        return $query->where('page_key', $key);
    }
}
