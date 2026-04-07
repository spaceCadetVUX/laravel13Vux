<?php

namespace App\Models\Seo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LlmsEntry extends Model
{
    // No created_at — only updated_at (matches ERD + migration)
    const CREATED_AT = null;

    protected $fillable = [
        'llms_document_id',
        'model_type',
        'model_id',
        'title',
        'url',
        'summary',
        'key_facts_text',
        'faq_text',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(LlmsDocument::class, 'llms_document_id');
    }
}
