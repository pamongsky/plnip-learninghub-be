<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_one_id',
        'user_two_id',
        'type',
        'last_message',
        'last_message_at',
        'last_message_by',
        'user_one_unread',
        'user_two_unread',
        'is_active',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'is_active' => 'boolean',
        'user_one_unread' => 'integer',
        'user_two_unread' => 'integer',
    ];

    /**
     * Conversation types
     */
    const TYPE_ADMIN_USER = 'admin_user';
    const TYPE_INSTRUCTOR_ADMIN = 'instructor_admin';
    const TYPE_SUPERADMIN_ADMIN = 'superadmin_admin';

    /**
     * Get user one (initiator)
     */
    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    /**
     * Get user two (recipient)
     */
    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    /**
     * Get the user who sent last message
     */
    public function lastMessageSender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_message_by');
    }

    /**
     * Get all messages in this conversation
     */
    public function messages(): HasMany
    {
        return $this->hasMany(DirectMessage::class)->orderBy('created_at', 'asc');
    }

    /**
     * Get the other participant in conversation
     */
    public function getOtherParticipant(int $userId): ?User
    {
        if ($this->user_one_id === $userId) {
            return $this->userTwo;
        }
        return $this->userOne;
    }

    /**
     * Check if user is participant
     */
    public function isParticipant(int $userId): bool
    {
        return $this->user_one_id === $userId || $this->user_two_id === $userId;
    }

    /**
     * Get unread count for a user
     */
    public function getUnreadCountFor(int $userId): int
    {
        if ($this->user_one_id === $userId) {
            return $this->user_one_unread;
        }
        return $this->user_two_unread;
    }

    /**
     * Increment unread for recipient
     */
    public function incrementUnreadFor(int $recipientId): void
    {
        if ($this->user_one_id === $recipientId) {
            $this->increment('user_one_unread');
        } else {
            $this->increment('user_two_unread');
        }
    }

    /**
     * Mark as read for a user
     */
    public function markAsReadFor(int $userId): void
    {
        if ($this->user_one_id === $userId) {
            $this->update(['user_one_unread' => 0]);
        } else {
            $this->update(['user_two_unread' => 0]);
        }

        // Mark all messages as read
        $this->messages()
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Update last message info
     */
    public function updateLastMessage(string $message, int $senderId): void
    {
        $this->update([
            'last_message' => $message,
            'last_message_at' => now(),
            'last_message_by' => $senderId,
        ]);
    }

    /**
     * Scope: Get conversations for a user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_one_id', $userId)
            ->orWhere('user_two_id', $userId);
    }

    /**
     * Scope: Filter by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Active conversations only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: With unread messages
     */
    public function scopeWithUnread($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where(function ($q2) use ($userId) {
                $q2->where('user_one_id', $userId)->where('user_one_unread', '>', 0);
            })->orWhere(function ($q2) use ($userId) {
                $q2->where('user_two_id', $userId)->where('user_two_unread', '>', 0);
            });
        });
    }

    /**
     * Find or create conversation between two users
     */
    public static function findOrCreateBetween(int $userOneId, int $userTwoId, string $type): self
    {
        // Normalize order - smaller ID first
        $minId = min($userOneId, $userTwoId);
        $maxId = max($userOneId, $userTwoId);

        return self::firstOrCreate(
            [
                'user_one_id' => $minId,
                'user_two_id' => $maxId,
            ],
            [
                'type' => $type,
                'is_active' => true,
            ]
        );
    }

    /**
     * Determine conversation type based on roles
     */
    public static function determineType(User $userOne, User $userTwo): ?string
    {
        $roles = [$userOne->role, $userTwo->role];
        sort($roles);

        // Admin <-> User (Peserta)
        if (in_array('admin', $roles) && in_array('user', $roles)) {
            return self::TYPE_ADMIN_USER;
        }

        // Instructor <-> Admin
        if (in_array('instructor', $roles) && in_array('admin', $roles)) {
            return self::TYPE_INSTRUCTOR_ADMIN;
        }

        // Super Admin <-> Admin
        if (in_array('superadmin', $roles) && in_array('admin', $roles)) {
            return self::TYPE_SUPERADMIN_ADMIN;
        }

        return null; // Invalid combination
    }
}
