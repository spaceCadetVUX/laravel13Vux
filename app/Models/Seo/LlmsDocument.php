<?php

namespace App\Models\Seo;

use App\Enums\LlmsScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LlmsDocument extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'title',
        'description',
        'scope',
        'model_type',
        'entry_count',
        'last_generated_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'scope'             => LlmsScope::class,
            'last_generated_at' => 'datetime',
            'is_active'         => 'boolean',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LlmsEntry::class);
    }
}
