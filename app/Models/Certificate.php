<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'certificate_number',
        'pdf_path',
        'original_filename',
        'is_valid',
        'notes',
    ];

    protected $casts = [
        'is_valid' => 'boolean',
    ];

    protected $appends = ['pdf_url'];

    public function getPdfUrlAttribute(): ?string
    {
        if (!$this->pdf_path) return null;
        return asset('storage/' . $this->pdf_path);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
