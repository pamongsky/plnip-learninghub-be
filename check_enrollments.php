<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== CEK ENROLLMENTS KELAS A ===\n\n";

$enrollments = DB::table('course_enrollments')
    ->where('course_id', 1)
    ->get();

echo "Enrollments untuk Kelas A (Course ID: 1):\n\n";

foreach ($enrollments as $e) {
    $user = DB::table('users')->where('id', $e->user_id)->first();
    $userName = $user ? $user->name : 'Unknown';
    
    echo "User ID: {$e->user_id} | Name: {$userName}\n";
    echo "  Moodle Role ID: {$e->moodle_role_id}\n";
    echo "  Status: {$e->status}\n";
    echo "  Enrolled At: {$e->enrolled_at}\n\n";
}

echo "\nMoodle Role Reference:\n";
echo "  - Role 1: Manager (admin)\n";
echo "  - Role 3: Teacher/Instructor (editingteacher)\n";
echo "  - Role 4: Non-editing teacher (teacher)\n";
echo "  - Role 5: Student (student)\n\n";

echo "=== SOLUTION ===\n";
$instructorEnrollment = DB::table('course_enrollments')
    ->where('course_id', 1)
    ->whereIn('moodle_role_id', [3, 4]) // Teacher roles
    ->first();

if ($instructorEnrollment) {
    $instructor = DB::table('users')->where('id', $instructorEnrollment->user_id)->first();
    echo "✅ Found instructor enrollment:\n";
    echo "   User ID: {$instructorEnrollment->user_id}\n";
    echo "   Name: {$instructor->name}\n";
    echo "   Moodle Role: {$instructorEnrollment->moodle_role_id}\n\n";
    
    echo "💡 We should UPDATE courses SET instructor_id = {$instructorEnrollment->user_id} WHERE id = 1\n";
    echo "\nDo you want to update? (This is just a suggestion, not executed)\n";
} else {
    echo "❌ No instructor enrollment found (role 3 or 4)\n";
    echo "   Need to assign an instructor to this course\n";
}

echo "\n=== END ===\n";
