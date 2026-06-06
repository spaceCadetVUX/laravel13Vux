<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FilterGroup extends Model
{
    protected $fillable = [
        'name',
        'name_en',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(FilterValue::class)->orderBy('sort_order');
    }

    public function activeValues(): HasMany
    {
        return $this->hasMany(FilterValue::class)->where('is_active', true)->orderBy('sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
