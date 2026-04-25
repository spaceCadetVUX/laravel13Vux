<?php

namespace App\Models\Seo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class GeoEntityProfile extends Model
{
    protected $fillable = [
        'model_type',
        'model_id',
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
            // jsonb columns — decoded to PHP arrays automatically
            'key_facts' => 'array',
            'faq'       => 'array',
        ];
    }
}
