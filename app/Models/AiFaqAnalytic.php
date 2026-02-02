<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiFaqAnalytic extends Model
{
    use HasFactory;

    protected $fillable = [
        'faq_id',
        'user_id',
        'user_query',
        'match_score',
        'was_helpful',
        'response_source',
        'response_time_ms',
    ];

    protected $casts = [
        'match_score' => 'decimal:4',
        'was_helpful' => 'boolean',
        'response_time_ms' => 'integer',
    ];

    public function faq()
    {
        return $this->belongsTo(AiFaq::class, 'faq_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
