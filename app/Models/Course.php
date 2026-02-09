<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'moodle_course_id',
        'title',
        'short_name',
        'description',
        'start_date',
        'end_date',
        'is_active',
        'instructor_id',
        'category_id',
        'image',
        'nomor_diklat',
        'unit_penyelenggara',
        'lokasi',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    protected $appends = ['moodle_url'];

    public function getMoodleUrlAttribute()
    {
        if (!$this->moodle_course_id) {
            return null;
        }

        $moodleBase = config('services.moodle.url', env('MOODLE_URL'));
        return "{$moodleBase}/course/view.php?id={$this->moodle_course_id}";
    }

    public function enrollments()
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'course_enrollments')
            ->withPivot('status', 'enrolled_at', 'moodle_role_id')
            ->withTimestamps();
    }

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

}
