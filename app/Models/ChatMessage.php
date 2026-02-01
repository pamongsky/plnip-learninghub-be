<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $fillable = ['chat_session_id', 'role', 'content'];

    public function session()
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }

    public function attachments()
    {
        return $this->hasMany(ChatAttachment::class, 'chat_message_id');
    }
}
