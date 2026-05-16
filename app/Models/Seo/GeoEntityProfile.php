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

    protected function performInsert(\Illuminate\Database\Eloquent\Builder $query): bool
    {
        if (filled($this->model_type) && filled($this->model_id) && filled($this->locale)) {
            $existing = static::query()
                ->where('model_type', $this->model_type)
                ->where('model_id',   $this->model_id)
                ->where('locale',     $this->locale)
                ->first();

            if ($existing) {
                $attrs = $this->getAttributes();
                unset($attrs[$this->getKeyName()], $attrs['created_at']);

                static::where($this->getKeyName(), $existing->getKey())->update($attrs);

                $this->setAttribute($this->getKeyName(), $existing->getKey());
                $this->exists             = true;
                $this->wasRecentlyCreated = false;
                $this->syncOriginal();

                return true;
            }
        }

        return parent::performInsert($query);
    }
}
