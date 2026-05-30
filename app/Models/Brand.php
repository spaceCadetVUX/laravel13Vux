<?php

namespace App\Models;

use App\Traits\HasActivityLog;
use App\Traits\HasGeoProfile;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use App\Traits\HasJsonldSchemas;
use App\Traits\HasLlmsEntry;
use App\Traits\HasMedia;
use App\Traits\HasSeoMeta;
use App\Traits\HasSitemapEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use HasSeoMeta;
    use HasGeoProfile;
    use HasJsonldSchemas;
    use HasSitemapEntry;
    use HasLlmsEntry;
    use HasMedia;
    use HasActivityLog;
    use LogsActivity;

    // ── Activity log ──────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('brand')
            ->logOnly(['name', 'slug', 'logo', 'website', 'is_active', 'sort_order'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    // keyType must be 'string' so Eloquent binds the PK as a quoted string in
    // PostgreSQL polymorphic queries (seo_meta.model_id, geo_entity_profiles.model_id are varchar(36)).
    public $incrementing = true;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'logo',
        'description',
        'website',
        'is_active',
        'sort_order',
        'mcp_drafted_at',
        'mcp_token_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active'      => 'boolean',
            'sort_order'     => 'integer',
            'mcp_drafted_at' => 'datetime',
            'mcp_token_id'   => 'integer',
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
