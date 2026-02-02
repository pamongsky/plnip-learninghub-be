<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiFaq extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category',
        'question',
        'question_variations',
        'answer',
        'answer_short',
        'confidence_score',
        'usage_count',
        'success_count',
        'failure_count',
        'last_used_at',
        'is_active',
        'is_verified',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'question_variations' => 'array',
        'confidence_score' => 'integer',
        'usage_count' => 'integer',
        'success_count' => 'integer',
        'failure_count' => 'integer',
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function analytics()
    {
        return $this->hasMany(AiFaqAnalytic::class, 'faq_id');
    }

    /**
     * Search FAQ by keyword
     */
    public static function searchByKeyword($query)
    {
        return self::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('question', 'LIKE', "%{$query}%")
                  ->orWhere('answer', 'LIKE', "%{$query}%");
            })
            ->orderByDesc('confidence_score')
            ->orderByDesc('usage_count')
            ->first();
    }

    /**
     * Get success rate
     */
    public function getSuccessRateAttribute()
    {
        $total = $this->success_count + $this->failure_count;
        return $total > 0 ? round(($this->success_count / $total) * 100, 1) : 0;
    }
}
