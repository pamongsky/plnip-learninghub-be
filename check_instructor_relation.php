<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== CEK RELASI INSTRUCTOR - COURSE ===\n\n";

// 1. Cek detail Kelas A
echo "1. DETAIL KELAS A:\n";
$kelasA = DB::table('courses')->where('id', 1)->first();
if ($kelasA) {
    echo "Course ID: {$kelasA->id}\n";
    echo "Title: {$kelasA->title}\n";
    echo "Instructor ID: " . ($kelasA->instructor_id ?? 'NULL') . "\n";
    echo "Moodle Course ID: " . ($kelasA->moodle_course_id ?? 'NULL') . "\n";
    echo "Short Name: {$kelasA->short_name}\n";
    echo "\nAll columns:\n";
    foreach ((array)$kelasA as $key => $value) {
        echo "  - {$key}: " . ($value ?? 'NULL') . "\n";
    }
}

// 2. Cek apakah ada tabel enrollments atau course_enrollments
echo "\n2. CEK ENROLLMENTS:\n";
try {
    $enrollments = DB::table('course_enrollments')
        ->where('course_id', 1)
        ->get();
    echo "Found " . $enrollments->count() . " enrollments untuk Kelas A:\n";
    foreach ($enrollments as $e) {
        $user = DB::table('users')->where('id', $e->user_id)->first();
        $roleName = $user ? $user->name : 'Unknown';
        echo "  - User ID: {$e->user_id} | Name: {$roleName}\n";
    }
} catch (\Exception $e) {
    echo "Table course_enrollments: " . $e->getMessage() . "\n";
}

// 3. Cek tabel lain yang mungkin ada relasi
echo "\n3. CEK TABLES:\n";
$tables = DB::select("SELECT table_name FROM user_tables WHERE table_name LIKE '%COURSE%' OR table_name LIKE '%INSTRUCTOR%'");
echo "Tables containing 'course' or 'instructor':\n";
foreach ($tables as $t) {
    echo "  - {$t->table_name}\n";
}

// 4. Siapa yang sudah kirim message di Kelas A?
echo "\n4. USERS YANG AKTIF DI KELAS A:\n";
$activeUsers = DB::table('class_messages')
    ->where('class_id', 1)
    ->select('user_id')
    ->distinct()
    ->get();

foreach ($activeUsers as $au) {
    $user = DB::table('users')->where('id', $au->user_id)->first();
    if ($user) {
        echo "  - User ID: {$user->id} | Name: {$user->name} | Email: {$user->email}\n";
        
        // Cek apakah user ini ada role_override atau access_group
        if (!empty($user->role_override)) {
            echo "    └─ Role Override: {$user->role_override}\n";
        }
        if (!empty($user->access_group)) {
            echo "    └─ Access Group: {$user->access_group}\n";
        }
    }
}

echo "\n=== END ===\n";
