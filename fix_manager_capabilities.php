<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== FIXING ROLE CAPABILITIES FOR MANAGER (role_id=1) ===\n\n";

// Critical Site Admin capabilities
$adminCapabilities = [
    'moodle/site:config',
    'moodle/site:configview',
    'moodle/site:doanything',
    'moodle/role:manage',
    'moodle/user:create',
    'moodle/user:delete',
    'moodle/user:update',
    'moodle/user:viewdetails',
    'moodle/course:create',
    'moodle/course:delete',
    'moodle/course:update',
    'moodle/course:view',
    'moodle/course:viewhiddencourses',
    'moodle/category:manage',
];

$systemContext = DB::connection('moodle')->table('context')->where('contextlevel', 10)->first();

foreach ($adminCapabilities as $capability) {
    // Check if exists
    $exists = DB::connection('moodle')
        ->table('role_capabilities')
        ->where('roleid', 1)
        ->where('capability', $capability)
        ->where('contextid', $systemContext->id)
        ->exists();

    if (!$exists) {
        DB::connection('moodle')->table('role_capabilities')->insert([
            'contextid' => $systemContext->id,
            'roleid' => 1,
            'capability' => $capability,
            'permission' => 1, // CAP_ALLOW
            'timemodified' => now()->timestamp,
            'modifierid' => 2,
        ]);
        echo "✓ Added capability: {$capability}\n";
    } else {
        // Update permission if exists
        DB::connection('moodle')
            ->table('role_capabilities')
            ->where('roleid', 1)
            ->where('capability', $capability)
            ->where('contextid', $systemContext->id)
            ->update(['permission' => 1]);
        echo "  Already exists: {$capability}\n";
    }
}

echo "\n=== DONE ===\n";
echo "Role Manager (id=1) now has all Site Administration capabilities\n";
