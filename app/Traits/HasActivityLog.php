<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasActivityLog
{
    /**
     * All activity log entries where this model is the subject.
     * Uses the (subject_type, subject_id) polymorphic pair — not (model_type, model_id).
     */
    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject', 'subject_type', 'subject_id');
    }
}
