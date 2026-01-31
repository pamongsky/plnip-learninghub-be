<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EscalationTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'admin_id',
        'superadmin_id',
        'support_ticket_id',
        'type',
        'subject',
        'description',
        'priority',
        'status',
        'category',
        'escalated_at',
        'resolved_at',
    ];

    protected $casts = [
        'escalated_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticket) {
            if (empty($ticket->ticket_number)) {
                $prefix = $ticket->type === 'escalation' ? 'ESC' : 'ADM';
                $ticket->ticket_number = $prefix . '-' . str_pad(static::max('id') + 1, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function superadmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'superadmin_id');
    }

    public function supportTicket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(EscalationReply::class)->orderBy('created_at', 'asc');
    }

    public function isEscalation(): bool
    {
        return $this->type === 'escalation';
    }

    public function isStandalone(): bool
    {
        return $this->type === 'standalone';
    }
}
