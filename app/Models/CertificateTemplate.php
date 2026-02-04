<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificateTemplate extends Model
{
    protected $fillable = [
        'name',
        'category',
        'file_path',
        'preview_path',
        'variables',
        'settings',
        'description',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'variables' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Get courses using this template
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'certificate_template_id');
    }

    /**
     * Get certificates generated with this template
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'template_id');
    }

    /**
     * Get default variables for certificate
     */
    public static function getDefaultVariables(): array
    {
        return [
            'nama' => 'Nama lengkap peserta',
            'employee_id' => 'ID Karyawan',
            'kelas' => 'Nama kelas/pelatihan',
            'tanggal_selesai' => 'Tanggal penyelesaian',
            'tanggal_terbit' => 'Tanggal penerbitan sertifikat',
            'nilai' => 'Nilai akhir',
            'grade' => 'Grade (A/B/C)',
            'jam' => 'Total jam pelatihan',
            'instructor' => 'Nama instruktur',
            'nomor_sertifikat' => 'Nomor sertifikat',
            'kode_verifikasi' => 'Kode verifikasi',
            'department' => 'Departemen peserta',
            'position' => 'Jabatan peserta',
        ];
    }

    /**
     * Get template by category or default
     */
    public static function getTemplateForCourse(?string $category = null): ?self
    {
        // Try to find template by category first
        if ($category) {
            $template = self::where('category', $category)
                ->where('is_active', true)
                ->first();

            if ($template) {
                return $template;
            }
        }

        // Fallback to default template
        return self::where('is_default', true)
            ->where('is_active', true)
            ->first();
    }
}
