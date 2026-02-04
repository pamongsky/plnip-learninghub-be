<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Testing Moodle Oracle Connection...\n";

try {
    $result = DB::connection('moodle')->select('SELECT 1 FROM DUAL');
    echo "✓ Moodle Connection OK\n";

    // Get user count
    $userCount = DB::connection('moodle')
        ->table('user')
        ->where('deleted', 0)
        ->where('suspended', 0)
        ->count();
    echo "✓ Moodle Users: $userCount\n";

    // Get course count
    $courseCount = DB::connection('moodle')
        ->table('course')
        ->where('id', '!=', 1)
        ->count();
    echo "✓ Moodle Courses: $courseCount\n";

} catch(Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
