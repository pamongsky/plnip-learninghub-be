<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'content',
        'image',
        'priority',
        'published_at',
        'is_active',
        'created_by',
        'scope',           // 'global' or 'unit'
        'broadcast_to',    // null=all, or specific role/group
        'views_count',
        'expires_at',
        'target_classes',  // JSON array of class IDs for instructor
        'target_role',     // 'all', 'user', 'instructor' for admin
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'target_classes' => 'array', // Auto cast JSON to array
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope: Get active announcements
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            });
    }

    /**
     * Scope: Get by scope
     */
    public function scopeByScope($query, $scope)
    {
        return $query->where('scope', $scope);
    }

    /**
     * Scope: Get global announcements (untuk semua user)
     */
    public function scopeGlobal($query)
    {
        return $query->where('scope', 'global');
    }

    /**
     * Scope: Get unit-specific announcements
     */
    public function scopeUnit($query)
    {
        return $query->where('scope', 'unit');
    }
}
