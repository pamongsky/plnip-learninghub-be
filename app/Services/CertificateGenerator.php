<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use setasign\Fpdi\Fpdi;

class CertificateGenerator
{
    /**
     * Generate certificate for a user who completed a course
     */
    public function generate(User $user, Course $course, float $finalScore): ?Certificate
    {
        // Check if auto-issue is enabled
        if (!$course->auto_issue_certificate) {
            \Log::info("Auto-issue disabled for course {$course->id}, skipping generation");
            return null;
        }

        // Check if certificate already exists
        $existing = Certificate::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if ($existing) {
            return $existing; // Already generated
        }

        // Get template
        $template = $course->certificateTemplate;
        if (!$template) {
            // Try to get default template
            $template = CertificateTemplate::where('is_default', true)
                ->where('is_active', true)
                ->first();
        }

        if (!$template) {
            \Log::error("No certificate template found for course {$course->id}");
            return null;
        }

        // Calculate issue date (completion date + delay)
        $completionDate = now();
        $delayDays = $course->certificate_issue_delay_days ?? 0;
        $issueDate = now()->addDays($delayDays);

        // Generate certificate data
        $certificateNumber = Certificate::generateCertificateNumber();
        $verificationCode = Certificate::generateVerificationCode();
        $grade = Certificate::calculateGrade($finalScore);

        // Prepare variables for replacement
        $variables = [
            '{{nama}}' => $user->name,
            '{{employee_id}}' => $user->employee_id ?? 'N/A',
            '{{kelas}}' => $course->title,
            '{{tanggal_selesai}}' => $completionDate->format('d F Y'),
            '{{tanggal_terbit}}' => $issueDate->format('d F Y'),
            '{{nilai}}' => number_format($finalScore, 2),
            '{{grade}}' => $grade,
            '{{jam}}' => $course->total_hours ?? 40, // Default 40 jam
            '{{instructor}}' => $course->instructor->name ?? 'N/A',
            '{{nomor_sertifikat}}' => $certificateNumber,
            '{{kode_verifikasi}}' => $verificationCode,
            '{{department}}' => $user->department ?? 'N/A',
            '{{position}}' => $user->position ?? 'N/A',
        ];

        // Generate PDF
        $pdfPath = $this->generatePDF($template, $variables, $certificateNumber);

        if (!$pdfPath) {
            \Log::error("Failed to generate PDF for user {$user->id}, course {$course->id}");
            return null;
        }

        // Create certificate record
        $certificate = Certificate::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'template_id' => $template->id,
            'certificate_number' => $certificateNumber,
            'course_name' => $course->title,
            'student_name' => $user->name,
            'completion_date' => $completionDate,
            'issue_date' => $issueDate,
            'final_score' => $finalScore,
            'grade' => $grade,
            'total_hours' => $course->total_hours ?? 40,
            'instructor_name' => $course->instructor->name ?? null,
            'certificate_url' => Storage::url($pdfPath),
            'verification_code' => $verificationCode,
            'is_valid' => true,
        ]);

        return $certificate;
    }

    /**
     * Generate PDF from template with variable replacement
     */
    protected function generatePDF(CertificateTemplate $template, array $variables, string $certificateNumber): ?string
    {
        try {
            $templatePath = Storage::disk('public')->path($template->file_path);

            // Check if template is PDF or image
            $extension = pathinfo($templatePath, PATHINFO_EXTENSION);

            if (strtolower($extension) === 'pdf') {
                return $this->generateFromPDFTemplate($templatePath, $variables, $certificateNumber);
            } else {
                // For image templates (JPG, PNG), use image processing
                return $this->generateFromImageTemplate($templatePath, $variables, $certificateNumber);
            }
        } catch (\Exception $e) {
            \Log::error("Error generating PDF: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate from PDF template using FPDI
     */
    protected function generateFromPDFTemplate(string $templatePath, array $variables, string $certificateNumber): ?string
    {
        try {
            $pdf = new Fpdi();
            $pdf->AddPage();
            $pdf->setSourceFile($templatePath);
            $tplId = $pdf->importPage(1);
            $pdf->useTemplate($tplId);

            // Set font
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->SetTextColor(0, 0, 0);

            // Add text overlays (positions should be configurable in template settings)
            // For now, use default positions
            $settings = $template->settings ?? [];

            // Nama (center, 40% from top)
            if (isset($variables['{{nama}}'])) {
                $pdf->SetXY(0, 100);
                $pdf->Cell(210, 10, $variables['{{nama}}'], 0, 0, 'C');
            }

            // Kelas (center, 50% from top)
            if (isset($variables['{{kelas}}'])) {
                $pdf->SetFont('Arial', '', 12);
                $pdf->SetXY(0, 120);
                $pdf->Cell(210, 10, $variables['{{kelas}}'], 0, 0, 'C');
            }

            // Nilai & Grade (center, 60% from top)
            if (isset($variables['{{nilai}}']) && isset($variables['{{grade}}'])) {
                $pdf->SetXY(0, 140);
                $pdf->Cell(210, 10, "Nilai: {$variables['{{nilai}}']} ({$variables['{{grade}}']})", 0, 0, 'C');
            }

            // Tanggal (bottom right)
            if (isset($variables['{{tanggal_terbit}}'])) {
                $pdf->SetFont('Arial', '', 10);
                $pdf->SetXY(140, 260);
                $pdf->Cell(60, 10, $variables['{{tanggal_terbit}}'], 0, 0, 'C');
            }

            // Nomor Sertifikat (bottom left)
            $pdf->SetXY(10, 260);
            $pdf->Cell(60, 10, $certificateNumber, 0, 0, 'L');

            // Save PDF
            $filename = "certificates/{$certificateNumber}.pdf";
            $fullPath = Storage::disk('public')->path($filename);

            // Ensure directory exists
            $directory = dirname($fullPath);
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            $pdf->Output('F', $fullPath);

            return $filename;
        } catch (\Exception $e) {
            \Log::error("FPDI Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate from image template using GD
     */
    protected function generateFromImageTemplate(string $templatePath, array $variables, string $certificateNumber): ?string
    {
        try {
            // Load template image
            $extension = pathinfo($templatePath, PATHINFO_EXTENSION);

            if ($extension === 'jpg' || $extension === 'jpeg') {
                $image = imagecreatefromjpeg($templatePath);
            } elseif ($extension === 'png') {
                $image = imagecreatefrompng($templatePath);
            } else {
                throw new \Exception("Unsupported image format: {$extension}");
            }

            // Get image dimensions
            $width = imagesx($image);
            $height = imagesy($image);

            // Set up font and color
            $fontPath = public_path('fonts/Arial.ttf'); // Need to add font file
            $black = imagecolorallocate($image, 0, 0, 0);
            $fontSize = 24;

            // Add text overlays
            // Nama (center, 40% from top)
            if (isset($variables['{{nama}}'])) {
                $this->addCenteredText($image, $variables['{{nama}}'], $fontSize, $height * 0.4, $fontPath, $black);
            }

            // Kelas (center, 50% from top)
            if (isset($variables['{{kelas}}'])) {
                $this->addCenteredText($image, $variables['{{kelas}}'], 18, $height * 0.5, $fontPath, $black);
            }

            // Save as JPEG
            $filename = "certificates/{$certificateNumber}.jpg";
            $fullPath = Storage::disk('public')->path($filename);

            $directory = dirname($fullPath);
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            imagejpeg($image, $fullPath, 95);
            imagedestroy($image);

            return $filename;
        } catch (\Exception $e) {
            \Log::error("Image Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Add centered text to image
     */
    protected function addCenteredText($image, string $text, int $fontSize, float $y, string $fontPath, $color)
    {
        $width = imagesx($image);
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
        $textWidth = $bbox[2] - $bbox[0];
        $x = ($width - $textWidth) / 2;

        imagettftext($image, $fontSize, 0, $x, $y, $color, $fontPath, $text);
    }

    /**
     * Get user's score based on course certificate criteria
     */
    public function getUserFinalScore(User $user, Course $course): ?float
    {
        try {
            // Connect to Moodle database
            $moodleDb = DB::connection('moodle');

            // Get user's Moodle ID
            $moodleUserId = $user->moodle_user_id;
            if (!$moodleUserId) {
                return null;
            }

            // Get course's Moodle ID
            $moodleCourseId = $course->moodle_course_id;
            if (!$moodleCourseId) {
                return null;
            }

            $criteria = $course->certificate_criteria ?? 'final_grade';

            switch ($criteria) {
                case 'specific_quiz':
                    return $this->getSpecificQuizScore($moodleDb, $moodleUserId, $course);

                case 'completion_and_grade':
                    return $this->getCompletionAndGradeScore($moodleDb, $moodleUserId, $moodleCourseId);

                case 'final_grade':
                default:
                    return $this->getFinalGradeScore($moodleDb, $moodleUserId, $moodleCourseId);
            }
        } catch (\Exception $e) {
            \Log::error("Error getting final score: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get final grade (weighted average dari semua quiz/assignment)
     */
    protected function getFinalGradeScore($moodleDb, int $moodleUserId, int $moodleCourseId): ?float
    {
        $grade = $moodleDb->table('mdl_grade_grades as gg')
            ->join('mdl_grade_items as gi', 'gg.itemid', '=', 'gi.id')
            ->where('gg.userid', $moodleUserId)
            ->where('gi.courseid', $moodleCourseId)
            ->where('gi.itemtype', 'course') // Course total
            ->select('gg.finalgrade', 'gi.grademax')
            ->first();

        if (!$grade || !$grade->finalgrade) {
            return null;
        }

        // Convert to percentage (0-100)
        $percentage = ($grade->finalgrade / $grade->grademax) * 100;
        return round($percentage, 2);
    }

    /**
     * Get specific quiz/exam score
     */
    protected function getSpecificQuizScore($moodleDb, int $moodleUserId, Course $course): ?float
    {
        if (!$course->certificate_quiz_id) {
            return null;
        }

        // Get quiz grade
        $grade = $moodleDb->table('mdl_grade_grades as gg')
            ->join('mdl_grade_items as gi', 'gg.itemid', '=', 'gi.id')
            ->where('gg.userid', $moodleUserId)
            ->where('gi.iteminstance', $course->certificate_quiz_id)
            ->where('gi.itemtype', 'mod')
            ->where('gi.itemmodule', 'quiz')
            ->select('gg.finalgrade', 'gi.grademax')
            ->first();

        if (!$grade || !$grade->finalgrade) {
            return null;
        }

        $percentage = ($grade->finalgrade / $grade->grademax) * 100;
        return round($percentage, 2);
    }

    /**
     * Check completion AND grade
     */
    protected function getCompletionAndGradeScore($moodleDb, int $moodleUserId, int $moodleCourseId): ?float
    {
        // Check course completion
        $completion = $moodleDb->table('mdl_course_completions')
            ->where('userid', $moodleUserId)
            ->where('course', $moodleCourseId)
            ->where('timecompleted', '>', 0) // Has completed
            ->first();

        if (!$completion) {
            return null; // Belum selesai semua materi
        }

        // Get final grade
        return $this->getFinalGradeScore($moodleDb, $moodleUserId, $moodleCourseId);
    }
}
