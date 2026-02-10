<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== DEBUG QUESTION STATS ===\n\n";

// 1. Cek semua class messages
echo "1. ALL CLASS MESSAGES:\n";
$allMessages = DB::table('class_messages')
    ->orderBy('created_at', 'desc')
    ->limit(20)
    ->get();

foreach ($allMessages as $msg) {
    echo "  - ID: {$msg->id} | Class: {$msg->class_id} | User: {$msg->user_id} | Type: {$msg->message_type} | Answered: " . ($msg->is_answered ? 'Yes' : 'No') . " | Message: " . substr($msg->message, 0, 50) . "\n";
}

// 2. Cek pertanyaan yang belum dijawab
echo "\n2. UNANSWERED QUESTIONS:\n";
$unanswered = DB::table('class_messages')
    ->where('message_type', 'question')
    ->where('is_answered', false)
    ->get();

echo "Total unanswered questions: " . $unanswered->count() . "\n";
foreach ($unanswered as $q) {
    echo "  - ID: {$q->id} | Class: {$q->class_id} | User: {$q->user_id} | Created: {$q->created_at}\n";
}

// 3. Cek courses dan instructor_id
echo "\n3. COURSES WITH INSTRUCTORS:\n";
$courses = DB::table('courses')
    ->select('id', 'title', 'instructor_id')
    ->get();

foreach ($courses as $course) {
    echo "  - Course ID: {$course->id} | Instructor ID: " . ($course->instructor_id ?? 'NULL') . " | Title: {$course->title}\n";
}

// 4. Cek columns di users table
echo "\n4. CHECK USERS TABLE COLUMNS:\n";
$sampleUser = DB::table('users')->first();
if ($sampleUser) {
    echo "Available columns: " . implode(', ', array_keys((array)$sampleUser)) . "\n";
}

// 5. Cek semua users (first 5)
echo "\n5. SAMPLE USERS:\n";
$users = DB::table('users')
    ->select('id', 'name', 'email')
    ->limit(5)
    ->get();

foreach ($users as $user) {
    echo "  - ID: {$user->id} | Name: {$user->name} | Email: {$user->email}\n";
}

// 6. Test query untuk user tertentu
echo "\n6. TEST: Cari user 'Faqih':\n";
$faqih = DB::table('users')
    ->where('name', 'like', '%Faqih%')
    ->orWhere('name', 'like', '%faqih%')
    ->first();

if ($faqih) {
    echo "Found: ID {$faqih->id} | Name: {$faqih->name}\n";
    
    $classIds = DB::table('courses')
        ->where('instructor_id', $faqih->id)
        ->pluck('id');
    
    echo "Classes taught: " . ($classIds->isEmpty() ? 'NONE (instructor_id not set!)' : $classIds->implode(', ')) . "\n";
    
    $unansweredCount = DB::table('class_messages')
        ->whereIn('class_id', $classIds)
        ->where('message_type', 'question')
        ->where('is_answered', false)
        ->count();
    
    echo "Unanswered questions in instructor's classes: {$unansweredCount}\n";
} else {
    echo "User 'Faqih' not found\n";
}

echo "\n7. PROBLEM IDENTIFIED:\n";
$classesWithoutInstructor = DB::table('courses')
    ->whereNull('instructor_id')
    ->get(['id', 'title']);
    
if ($classesWithoutInstructor->isNotEmpty()) {
    echo "⚠️  Courses WITHOUT instructor assigned:\n";
    foreach ($classesWithoutInstructor as $c) {
        echo "  - Course ID {$c->id}: {$c->title}\n";
    }
    echo "\n💡 SOLUTION: Assign instructor_id to courses!\n";
}

echo "\n=== END DEBUG ===\n";
