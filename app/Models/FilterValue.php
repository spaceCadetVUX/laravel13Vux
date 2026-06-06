<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FilterValue extends Model
{
    protected $fillable = [
        'filter_group_id',
        'name',
        'name_en',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(FilterGroup::class, 'filter_group_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_filter_values');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
