<?php

namespace App\Models\Seo;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class GeoEntityProfile extends Model
{
    protected $fillable = [
        'model_type',
        'model_id',
        'locale',
        'ai_summary',
        'key_facts',
        'faq',
        'use_cases',
        'target_audience',
        'llm_context_hint',
    ];

    public function model(): MorphTo
    {
        return $this->morphTo('model', 'model_type', 'model_id');
    }

    protected function casts(): array
    {
        return [
            'key_facts' => 'array',
            'faq'       => 'array',
        ];
    }

    public function scopeForLocale(Builder $q, string $locale): Builder
    {
        return $q->where('locale', $locale);
    }
}
