<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatAttachment extends Model
{
    protected $fillable = [
        'chat_message_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size'
    ];

    public function message()
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }
}
