<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiConversation extends Model
{
    protected $fillable = [
        'conversation_id',
        'user_id',
        'message',
        'role',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get conversation history for a user
     */
    public static function getHistory(string $conversationId, int $limit = 20)
    {
        return self::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get(['message', 'role', 'created_at']);
    }
}
