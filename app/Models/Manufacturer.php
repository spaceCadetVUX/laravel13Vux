<?php

namespace App\Models;

use App\Traits\HasActivityLog;
use App\Traits\HasGeoProfile;
use App\Traits\HasJsonldSchemas;
use App\Traits\HasLlmsEntry;
use App\Traits\HasSeoMeta;
use App\Traits\HasSitemapEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Manufacturer extends Model
{
    use HasSeoMeta;
    use HasGeoProfile;
    use HasJsonldSchemas;
    use HasSitemapEntry;
    use HasLlmsEntry;
    use HasActivityLog;

    protected $fillable = [
        'name',
        'slug',
        'logo',
        'description',
        'website',
        'country',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
