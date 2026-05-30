<?php

namespace App\Models\Seo;

use App\Enums\OgType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SeoMeta extends Model
{
    protected $table = 'seo_meta';

    protected $fillable = [
        'model_type',
        'model_id',
        'locale',
        'is_mcp_protected',
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
            'og_type'          => OgType::class,
            'is_mcp_protected' => 'boolean',
        ];
    }

    public function scopeForLocale(Builder $q, string $locale): Builder
    {
        return $q->where('locale', $locale);
    }

    /**
     * Filament's nested Tabs cause saveRelationships() to fire more than once for
     * the same Group relationship. The second attempt hits the unique constraint on
     * (model_type, model_id, locale). Overriding performInsert() catches this at
     * the last moment — after all FK attributes have been set — and converts the
     * duplicate insert into an UPDATE.
     */
    protected function performInsert(\Illuminate\Database\Eloquent\Builder $query): bool
    {
        if (filled($this->model_type) && filled($this->model_id)) {
            // locale may be null when Filament creates a new model via the relationship
            // without including the WHERE-clause attribute; fall back to the DB default.
            $locale = $this->locale ?: config('app.fallback_locale', 'vi');

            $existing = static::query()
                ->where('model_type', $this->model_type)
                ->where('model_id',   $this->model_id)
                ->where('locale',     $locale)
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
