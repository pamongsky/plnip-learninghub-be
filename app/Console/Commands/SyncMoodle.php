<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MoodleSyncService;
use Illuminate\Support\Facades\Log;

class SyncMoodle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'moodle:sync {--full : Force full sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize data from Moodle LMS (Users, Courses, Enrollments)';

    /**
     * Execute the console command.
     */
    public function handle(MoodleSyncService $moodleSync)
    {
        $this->info('Starting Moodle Sync...');
        
        // 1. Check Connection
        $status = $moodleSync->getConnectionStatus();
        if ($status['status'] !== 'connected') {
            $this->error("Connection to Moodle Failed: " . ($status['error'] ?? 'Unknown error'));
            return 1;
        }
        $this->info("Connected to Moodle ({$status['moodle_version']}). Users: {$status['total_users']}, Courses: {$status['total_courses']}");

        if (!$this->confirm('Do you want to proceed with FULL SYNC? This may take time.', true)) {
            return 0;
        }

        // 2. Sync Users
        $this->info('Syncing Users...');
        try {
            $userStats = $moodleSync->syncUsers();
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Moodle Users', $userStats['total_moodle']],
                    ['Added (New)', $userStats['added']],
                    ['Updated (Synced)', $userStats['updated']],
                    ['Suspended (Deactivated)', $userStats['suspended_deactivated']],
                    ['Errors', $userStats['errors']],
                ]
            );
        } catch (\Exception $e) {
            $this->error("User Sync Failed: " . $e->getMessage());
        }

        // 3. Sync Courses
        $this->info('Syncing Courses...');
        try {
            $courseStats = $moodleSync->syncCourses();
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Moodle Courses', $courseStats['total_moodle']],
                    ['Added', $courseStats['added']],
                    ['Updated', $courseStats['updated']],
                    ['Errors', $courseStats['errors']],
                ]
            );
        } catch (\Exception $e) {
            $this->error("Course Sync Failed: " . $e->getMessage());
        }

        // 4. Sync Enrollments
        $this->info('Syncing Enrollments...');
        try {
            $enrollStats = $moodleSync->syncEnrollments();
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Moodle Enrollments', $enrollStats['total_moodle']],
                    ['Added', $enrollStats['added']],
                    ['Updated', $enrollStats['updated']],
                    ['Suspended (Local Fix)', $enrollStats['suspended_local']],
                    ['Errors', $enrollStats['errors']],
                ]
            );
        } catch (\Exception $e) {
            $this->error("Enrollment Sync Failed: " . $e->getMessage());
        }

        // 5. Sync Instructor Roles
        $this->info('Syncing Instructor Roles...');
        try {
            $instStats = $moodleSync->syncInstructorRoles();
             $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Assignments', $instStats['total_assignments']],
                    ['Updated', $instStats['updated']],
                    ['Cleared', $instStats['cleared']],
                    ['Errors', $instStats['errors']],
                ]
            );
        } catch (\Exception $e) {
            $this->error("Instructor Sync Failed: " . $e->getMessage());
        }

        $this->info('Moodle Sync Completed.');
        return 0;
    }
}
