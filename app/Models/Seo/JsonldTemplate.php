<?php

namespace App\Models\Seo;

use App\Enums\JsonldSchemaType;
use Illuminate\Database\Eloquent\Model;

class JsonldTemplate extends Model
{
    protected $fillable = [
        'schema_type',
        'label',
        'template',
        'placeholders',
        'is_auto_generated',
    ];

    protected function casts(): array
    {
        return [
            'schema_type'      => JsonldSchemaType::class,
            // jsonb columns decoded to PHP arrays
            'template'         => 'array',
            'placeholders'     => 'array',
            'is_auto_generated' => 'boolean',
        ];
    }
}
