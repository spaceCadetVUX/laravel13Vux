<?php

namespace App\Models\Seo;

use App\Enums\JsonldSchemaType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class JsonldSchema extends Model
{
    protected $fillable = [
        'model_type',
        'model_id',
        'locale',
        'schema_type',
        'label',
        'payload',
        'is_active',
        'is_auto_generated',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'schema_type'       => JsonldSchemaType::class,
            'payload'           => 'array',
            'is_active'         => 'boolean',
            'is_auto_generated' => 'boolean',
        ];
    }

    public function scopeForLocale(Builder $q, string $locale): Builder
    {
        return $q->where('locale', $locale);
    }
}
