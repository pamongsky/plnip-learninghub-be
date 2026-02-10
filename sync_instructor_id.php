<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== SYNC INSTRUCTOR_ID FROM ENROLLMENTS ===\n\n";

// Get all courses without instructor_id
$coursesWithoutInstructor = DB::table('courses')
    ->whereNull('instructor_id')
    ->get();

echo "Found " . $coursesWithoutInstructor->count() . " course(s) without instructor_id\n\n";

foreach ($coursesWithoutInstructor as $course) {
    echo "Course ID {$course->id}: {$course->title}\n";
    
    // Find instructor enrollment (role 3 or 4, exclude super admin)
    $instructorEnrollment = DB::table('course_enrollments')
        ->where('course_id', $course->id)
        ->whereIn('moodle_role_id', [3, 4])
        ->where('user_id', '!=', 3) // Exclude super admin
        ->first();
    
    if (!$instructorEnrollment) {
        // If no non-admin instructor, check any role 3/4
        $instructorEnrollment = DB::table('course_enrollments')
            ->where('course_id', $course->id)
            ->whereIn('moodle_role_id', [3, 4])
            ->first();
    }
    
    if ($instructorEnrollment) {
        $instructor = DB::table('users')->where('id', $instructorEnrollment->user_id)->first();
        echo "  → Found instructor: {$instructor->name} (ID: {$instructorEnrollment->user_id})\n";
        
        // Update the course
        $updated = DB::table('courses')
            ->where('id', $course->id)
            ->update(['instructor_id' => $instructorEnrollment->user_id]);
        
        if ($updated) {
            echo "  ✅ Updated instructor_id to {$instructorEnrollment->user_id}\n\n";
        } else {
            echo "  ❌ Failed to update\n\n";
        }
    } else {
        echo "  ⚠️  No instructor enrollment found (skipped)\n\n";
    }
}

echo "=== VERIFICATION ===\n";
$kelasA = DB::table('courses')->where('id', 1)->first();
echo "Kelas A instructor_id: " . ($kelasA->instructor_id ?? 'NULL') . "\n";

if ($kelasA->instructor_id) {
    // Test the query
    $classIds = DB::table('courses')
        ->where('instructor_id', $kelasA->instructor_id)
        ->pluck('id');
    
    $unansweredCount = DB::table('class_messages')
        ->whereIn('class_id', $classIds)
        ->where('message_type', 'question')
        ->where('is_answered', false)
        ->count();
    
    echo "Classes taught: " . $classIds->implode(', ') . "\n";
    echo "Unanswered questions: {$unansweredCount}\n";
}

echo "\n=== DONE ===\n";
