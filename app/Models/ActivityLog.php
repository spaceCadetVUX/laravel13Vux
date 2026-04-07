<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    protected $table = 'activity_log';

    protected $fillable = [
        'log_name',
        'description',
        'subject_type',
        'subject_id',
        'causer_type',
        'causer_id',
        'properties',
    ];

    protected function casts(): array
    {
        return [
            // jsonb — decoded to PHP array for easy old/new value inspection
            'properties' => 'array',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    /**
     * The model that was acted upon (Product, Order, User, etc.).
     * Uses model_type / model_id polymorphic pair.
     */
    public function subject(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'subject_type', 'subject_id');
    }

    /**
     * The model that caused the action (usually a User).
     * Uses causer_type / causer_id polymorphic pair.
     */
    public function causer(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'causer_type', 'causer_id');
    }
}
