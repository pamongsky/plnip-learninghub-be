<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Sample role_assignments:\n";
$sample = DB::connection('moodle')->table('role_assignments')->first();
if ($sample) {
    var_dump($sample);
} else {
    echo "No role assignments exist\n";
}
