<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'template_id',
        'certificate_number',
        'course_name',
        'student_name',
        'completion_date',
        'issue_date',
        'final_score',
        'grade',
        'total_hours',
        'instructor_name',
        'certificate_url',
        'verification_code',
        'is_valid',
        'notes',
    ];

    protected $casts = [
        'completion_date' => 'date',
        'issue_date' => 'date',
        'final_score' => 'decimal:2',
        'is_valid' => 'boolean',
    ];

    /**
     * Get the user who owns this certificate
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the course this certificate is for
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the template used for this certificate
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'template_id');
    }

    /**
     * Generate unique certificate number
     */
    public static function generateCertificateNumber(): string
    {
        $year = date('Y');
        $month = date('m');

        // Format: PLN-CERT-YYYY-MM-XXXX
        $prefix = "PLN-CERT-{$year}-{$month}";

        // Get last number for this month
        $lastCert = self::where('certificate_number', 'like', "{$prefix}-%")
            ->orderBy('certificate_number', 'desc')
            ->first();

        if ($lastCert) {
            // Extract last number and increment
            $lastNumber = (int) substr($lastCert->certificate_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . '-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate unique verification code
     */
    public static function generateVerificationCode(): string
    {
        return strtoupper(bin2hex(random_bytes(8))); // 16 character hex
    }

    /**
     * Get grade based on score
     */
    public static function calculateGrade(float $score): string
    {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        return 'E';
    }
}
