<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$email = 'superadmin@plnip.local';

echo "=== CHECKING SUPERADMIN MOODLE ACCESS ===\n\n";

// Get Moodle user
$moodleUser = DB::connection('moodle')
    ->table('user')
    ->where('email', $email)
    ->where('deleted', 0)
    ->first();

if (!$moodleUser) {
    echo "User not found in Moodle\n";
    exit;
}

echo "User ID: {$moodleUser->id}\n";
echo "Username: {$moodleUser->username}\n";
echo "Email: {$moodleUser->email}\n\n";

echo "=== ROLE ASSIGNMENTS ===\n";
$assignments = DB::connection('moodle')
    ->table('role_assignments as ra')
    ->join('role as r', 'ra.roleid', '=', 'r.id')
    ->join('context as ctx', 'ra.contextid', '=', 'ctx.id')
    ->where('ra.userid', $moodleUser->id)
    ->select('ra.id', 'ra.roleid', 'r.shortname', 'r.name', 'r.archetype', 'ra.contextid', 'ctx.contextlevel', 'ctx.instanceid')
    ->get();

if ($assignments->count() > 0) {
    foreach($assignments as $a) {
        $contextLevel = '';
        switch($a->contextlevel) {
            case 10: $contextLevel = 'SYSTEM'; break;
            case 40: $contextLevel = 'COURSE'; break;
            case 50: $contextLevel = 'BLOCK'; break;
            default: $contextLevel = "LEVEL_{$a->contextlevel}";
        }
        echo "  Assignment ID: {$a->id}\n";
        echo "  Role: [{$a->roleid}] {$a->shortname} (archetype: {$a->archetype})\n";
        echo "  Context: {$contextLevel} (id={$a->contextid}, level={$a->contextlevel}, instance={$a->instanceid})\n\n";
    }
} else {
    echo "  NO ROLE ASSIGNMENTS FOUND!\n";
}

echo "=== EXPECTED: Site Administrator ===\n";
echo "Should have: Role ID 1 (manager) in SYSTEM context (contextlevel=10)\n";

// Check if user has admin flag
echo "\n=== ADMIN USER TABLE ===\n";
$isAdmin = DB::connection('moodle')
    ->table('config')
    ->where('name', 'siteadmins')
    ->first();

if ($isAdmin) {
    $adminIds = explode(',', $isAdmin->value);
    if (in_array($moodleUser->id, $adminIds)) {
        echo "✓ User IS in siteadmins config\n";
    } else {
        echo "✗ User NOT in siteadmins config\n";
        echo "Current siteadmins: {$isAdmin->value}\n";
        echo "Need to add: {$moodleUser->id}\n";
    }
}
