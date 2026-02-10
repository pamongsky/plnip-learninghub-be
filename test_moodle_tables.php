<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CHECKING MOODLE TABLES ===\n\n";

$tables = ['grade_items', 'grade_grades', 'course_completions', 'user', 'course'];

foreach ($tables as $table) {
    try {
        $count = DB::connection('moodle')->table($table)->count();
        echo "✓ Table '{$table}' exists - {$count} rows\n";
    } catch (\Exception $e) {
        echo "✗ Table '{$table}' ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\n=== TESTING GRADE QUERY ===\n";
try {
    $grade = DB::connection('moodle')
        ->table('grade_grades as gg')
        ->join('grade_items as gi', 'gg.itemid', '=', 'gi.id')
        ->where('gi.courseid', 2)
        ->where('gi.itemtype', 'course')
        ->select('gg.userid', 'gg.finalgrade', 'gi.grademax')
        ->first();

    if ($grade) {
        echo "✓ Grade query successful\n";
        echo "  User ID: {$grade->userid}\n";
        echo "  Final Grade: {$grade->finalgrade}\n";
        echo "  Grade Max: {$grade->grademax}\n";
    } else {
        echo "No grade found for course 2\n";
    }
} catch (\Exception $e) {
    echo "✗ Grade query failed: " . $e->getMessage() . "\n";
}
