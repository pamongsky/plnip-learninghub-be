<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoodleSyncLog extends Model
{
    protected $fillable = [
        'type',
        'status',
        'triggered_by',
        'started_at',
        'completed_at',
        'duration_seconds',
        'users_added',
        'users_updated',
        'users_errors',
        'courses_added',
        'courses_updated',
        'courses_errors',
        'enrollments_added',
        'enrollments_updated',
        'enrollments_errors',
        'error_message',
        'logs',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'logs' => 'array',
    ];

    /**
     * User who triggered the sync
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
