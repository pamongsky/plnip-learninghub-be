<?php
/**
 * Debug script untuk check instructor role di Moodle
 *
 * Run: php debug_instructor.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== DEBUG INSTRUCTOR ROLE ASSIGNMENTS ===\n\n";

// Get instructor from portal
$instructorEmail = 'instructor@plnip.local';  // Sesuaikan dengan email instructor yang di-test
echo "Checking instructor: {$instructorEmail}\n\n";

try {
    // Check Moodle user
    $moodleUser = DB::connection('moodle')
        ->table('user')
        ->where('email', $instructorEmail)
        ->first();

    if (!$moodleUser) {
        echo "❌ User tidak ditemukan di Moodle!\n";
        exit;
    }

    echo "✅ Moodle User Found:\n";
    echo "   ID: {$moodleUser->id}\n";
    echo "   Username: {$moodleUser->username}\n";
    echo "   Email: {$moodleUser->email}\n\n";

    // Check role assignments
    echo "--- Role Assignments ---\n";
    $roleAssignments = DB::connection('moodle')
        ->table('role_assignments as ra')
        ->join('role as r', 'ra.roleid', '=', 'r.id')
        ->join('context as ctx', 'ra.contextid', '=', 'ctx.id')
        ->leftJoin('course as c', function($join) {
            $join->on('ctx.instanceid', '=', 'c.id')
                 ->where('ctx.contextlevel', '=', 50);
        })
        ->where('ra.userid', $moodleUser->id)
        ->select(
            'r.shortname as role',
            'ctx.contextlevel',
            'ctx.instanceid',
            'c.fullname as course_name',
            'c.visible'
        )
        ->get();

    if ($roleAssignments->isEmpty()) {
        echo "❌ Tidak ada role assignment!\n\n";
    } else {
        foreach ($roleAssignments as $ra) {
            echo "Role: {$ra->role}\n";
            echo "  Context Level: {$ra->contextlevel}";
            if ($ra->contextlevel == 50) {
                echo " (COURSE)";
            } elseif ($ra->contextlevel == 10) {
                echo " (SYSTEM)";
            }
            echo "\n";
            echo "  Instance ID: {$ra->instanceid}\n";
            if ($ra->course_name) {
                echo "  Course: {$ra->course_name} (Visible: {$ra->visible})\n";
            }
            echo "\n";
        }
    }

    // Check specifically for teacher roles
    echo "--- Teacher Role Check ---\n";
    $teacherRoles = DB::connection('moodle')
        ->table('role_assignments as ra')
        ->join('role as r', 'ra.roleid', '=', 'r.id')
        ->where('ra.userid', $moodleUser->id)
        ->whereIn('r.shortname', ['editingteacher', 'teacher'])
        ->count();

    if ($teacherRoles > 0) {
        echo "✅ User memiliki {$teacherRoles} teacher role assignment(s)\n\n";
    } else {
        echo "❌ User TIDAK memiliki teacher role!\n";
        echo "   Perlu di-assign sebagai Teacher/Editing Teacher di Moodle\n\n";
    }

    // Run the actual query from DashboardController
    echo "--- Query Result (Dashboard Controller) ---\n";
    $courses = DB::connection('moodle')
        ->table('course as c')
        ->join('context as ctx', function($join) {
            $join->on('ctx.instanceid', '=', 'c.id')
                 ->where('ctx.contextlevel', '=', 50);
        })
        ->join('role_assignments as ra', 'ra.contextid', '=', 'ctx.id')
        ->join('role as r', 'ra.roleid', '=', 'r.id')
        ->where('ra.userid', $moodleUser->id)
        ->whereIn('r.shortname', ['editingteacher', 'teacher'])
        ->where('c.id', '!=', 1)
        ->where('c.visible', 1)
        ->select(
            'c.id',
            'c.fullname as title',
            'c.shortname',
            'c.visible'
        )
        ->distinct()
        ->get();

    if ($courses->isEmpty()) {
        echo "❌ Query tidak mengembalikan kelas apapun\n";
        echo "\nKEMUNGKINAN PENYEBAB:\n";
        echo "1. User belum di-assign sebagai Teacher/Editing Teacher\n";
        echo "2. Course belum visible (published)\n";
        echo "3. Role assignment tidak di-set di course context\n";
    } else {
        echo "✅ Found {$courses->count()} course(s):\n";
        foreach ($courses as $course) {
            echo "   - [{$course->id}] {$course->title} ({$course->shortname})\n";
        }
    }

    echo "\n=== END DEBUG ===\n";

} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
