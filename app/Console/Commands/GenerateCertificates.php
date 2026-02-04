<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\User;
use App\Services\CertificateGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateCertificates extends Command
{
    protected $signature = 'certificates:generate {--course_id=} {--user_id=}';
    protected $description = 'Generate certificates for users who passed courses';

    protected $generator;

    public function __construct(CertificateGenerator $generator)
    {
        parent::__construct();
        $this->generator = $generator;
    }

    public function handle()
    {
        $this->info('Starting certificate generation...');

        $coursesQuery = Course::where('is_active', true);

        // Filter by specific course if provided
        if ($this->option('course_id')) {
            $coursesQuery->where('id', $this->option('course_id'));
        }

        $courses = $coursesQuery->with('certificateTemplate')->get();

        if ($courses->isEmpty()) {
            $this->warn('No active courses found.');
            return 0;
        }

        $totalGenerated = 0;
        $totalSkipped = 0;

        foreach ($courses as $course) {
            $this->info("Processing course: {$course->title}");

            // Get enrolled students
            $studentsQuery = $course->students();

            // Filter by specific user if provided
            if ($this->option('user_id')) {
                $studentsQuery->where('users.id', $this->option('user_id'));
            }

            $students = $studentsQuery->get();

            foreach ($students as $student) {
                // Get final score from Moodle
                $finalScore = $this->generator->getUserFinalScore($student, $course);

                if ($finalScore === null) {
                    $this->line("  ⊘ {$student->name} - No grade found");
                    $totalSkipped++;
                    continue;
                }

                // Check if passed
                $passingGrade = $course->passing_grade ?? 70.00;
                if ($finalScore < $passingGrade) {
                    $this->line("  ✗ {$student->name} - Score: {$finalScore}% (Below {$passingGrade}%)");
                    $totalSkipped++;
                    continue;
                }

                // Generate certificate
                $certificate = $this->generator->generate($student, $course, $finalScore);

                if ($certificate) {
                    $this->info("  ✓ {$student->name} - Certificate generated: {$certificate->certificate_number}");
                    $totalGenerated++;
                } else {
                    $this->error("  ✗ {$student->name} - Failed to generate certificate");
                    $totalSkipped++;
                }
            }
        }

        $this->newLine();
        $this->info("Certificate generation completed!");
        $this->table(
            ['Status', 'Count'],
            [
                ['Generated', $totalGenerated],
                ['Skipped', $totalSkipped],
                ['Total Processed', $totalGenerated + $totalSkipped],
            ]
        );

        return 0;
    }
}
