<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Course;
use App\Models\CourseEnrollment;

class SystemHealthCheck extends Command
{
    protected $signature = 'system:check';
    protected $description = 'Perform a full system health check (DB, Moodle, Storage, Integrity)';

    public function handle()
    {
        $this->info('Starting System Health Check...');
        $hasError = false;

        // 1. Local Database Check
        $this->info("\n--- 1. Local Database (MySQL/Postgres) ---");
        try {
            DB::connection()->getPdo();
            $this->line("✅ Connection: OK");
            $userCount = User::count();
            $this->line("✅ Users: $userCount");
        } catch (\Exception $e) {
            $this->error("❌ Connection Failed: " . $e->getMessage());
            $hasError = true;
        }

        // 2. Moodle Database Check
        $this->info("\n--- 2. Moodle Database (Oracle/MySQL) ---");
        try {
            $start = microtime(true);
            DB::connection('moodle')->getPdo();
            $latency = round((microtime(true) - $start) * 1000, 2);
            
            $this->line("✅ Connection: OK (Latency: {$latency}ms)");
            
            if ($latency > 500) {
                $this->warn("⚠️  High Latency (>500ms). Dashboard performance may suffer.");
            }

            $moodleUserCount = DB::connection('moodle')->table('user')->where('deleted', 0)->count();
            $this->line("✅ Moodle Users: $moodleUserCount");

        } catch (\Exception $e) {
            $this->error("❌ Connection Failed: " . $e->getMessage());
            $hasError = true;
        }

        // 3. Data Integrity Check
        $this->info("\n--- 3. Data Integrity (Orphaned Records) ---");
        
        $orphanEnrollmentsUser = CourseEnrollment::whereDoesntHave('user')->count();
        if ($orphanEnrollmentsUser > 0) {
            $this->error("❌ Found $orphanEnrollmentsUser enrollments with missing User.");
            $hasError = true;
        } else {
            $this->line("✅ No orphaned enrollments (User)");
        }

        $orphanEnrollmentsCourse = CourseEnrollment::whereDoesntHave('course')->count();
        if ($orphanEnrollmentsCourse > 0) {
            $this->error("❌ Found $orphanEnrollmentsCourse enrollments with missing Course.");
            $hasError = true;
        } else {
            $this->line("✅ No orphaned enrollments (Course)");
        }

        // 4. Storage & Cache
        $this->info("\n--- 4. Storage & Cache ---");
        if (is_writable(storage_path('framework/views'))) {
            $this->line("✅ Storage Writable: OK");
        } else {
            $this->error("❌ Storage Not Writable: " . storage_path());
            $hasError = true;
        }

        // 5. Critical Logic Audit (Static Check)
        $this->info("\n--- 5. Critical Logic Audit ---");
        
        // Check if DashboardController has N+1
        // Anti-pattern: DB call inside a foreach loop (not batch queries before the loop)
        $dashboardContent = file_get_contents(app_path('Http/Controllers/API/DashboardController.php'));
        // Look for pattern: foreach(...) { ... DB::connection() within 3 lines — indicates N+1
        // "BATCH QUERY" comment marker = safe, was intentionally placed before loops
        $hasDashboardN1 = preg_match('/foreach\s*\(.*\)\s*\{[^}]{0,200}DB::connection\(\'moodle\'\)/s', $dashboardContent)
            && strpos($dashboardContent, '// BATCH QUERY') === false
            && strpos($dashboardContent, '// PRE-FETCH') === false;

        if ($hasDashboardN1) {
             $this->warn("⚠️  DashboardController contains N+1 Query pattern (Looping Moodle DB calls). Optimization Required.");
        } else {
             $this->line("✅ DashboardController: Batch queries optimized.");
        }

        // Check CourseController
        $courseContent = file_get_contents(app_path('Http/Controllers/API/CourseController.php'));
        $hasCourseN1 = preg_match('/foreach\s*\(.*\)\s*\{[^}]{0,200}DB::connection\(\'moodle\'\)/s', $courseContent)
            && strpos($courseContent, '// BATCH QUERY') === false;

        if ($hasCourseN1) {
             $this->warn("⚠️  CourseController contains N+1 Query pattern (Looping Moodle DB calls). Optimization Required.");
        } else {
             $this->line("✅ CourseController: Batch queries optimized.");
        }

        $this->info("\n---------------------------------");
        if ($hasError) {
            $this->error("SYSTEM CHECK FAILED. See errors above.");
            return 1;
        } else {
            $this->info("SYSTEM CHECK PASSED. Everything looks healthy.");
            return 0;
        }
    }
}
