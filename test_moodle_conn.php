<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Testing Moodle Connection...\n";

try {
    $count = DB::connection('moodle')->table('course')->where('id', '!=', 1)->count();
    echo "✅ Moodle courses: " . $count . "\n";

    $userCount = DB::connection('moodle')->table('user')->where('deleted', 0)->where('suspended', 0)->count();
    echo "✅ Moodle users: " . $userCount . "\n";

} catch(Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
