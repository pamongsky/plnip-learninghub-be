<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassMessage extends Model
{
    protected $fillable = [
        'class_id',
        'user_id',
        'message',
        'message_type',
        'is_answered',
        'answered_by',
        'answered_at',
        'image_path',
    ];

    protected $casts = [
        'is_answered' => 'boolean',
        'answered_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who sent the message
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the instructor who answered the question
     */
    public function answeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    /**
     * Scope for questions only
     */
    public function scopeQuestions($query)
    {
        return $query->where('message_type', 'question');
    }

    /**
     * Scope for unanswered questions
     */
    public function scopeUnanswered($query)
    {
        return $query->where('message_type', 'question')->where('is_answered', false);
    }

    /**
     * Scope for today's messages
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope for specific class
     */
    public function scopeForClass($query, $classId)
    {
        return $query->where('class_id', $classId);
    }
}
