<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== MOODLE ROLES ===\n";
$moodleRoles = DB::connection('moodle')->table('role')->get();
foreach($moodleRoles as $r) {
    echo "  [{$r->id}] {$r->shortname} | {$r->name} | archetype:{$r->archetype}\n";
}

echo "\n=== MOODLE USER: Super Administrator ===\n";
$moodleUser = DB::connection('moodle')
    ->table('user')
    ->where('email', 'superadmin@plnip.local')
    ->where('deleted', 0)
    ->first();

if ($moodleUser) {
    echo "User ID: {$moodleUser->id}\n";
    echo "Username: {$moodleUser->username}\n";
    echo "Email: {$moodleUser->email}\n";
    echo "Name: {$moodleUser->firstname} {$moodleUser->lastname}\n";

    echo "\nRole Assignments:\n";
    $roleAssignments = DB::connection('moodle')
        ->table('role_assignments')
        ->join('role', 'role_assignments.roleid', '=', 'role.id')
        ->where('role_assignments.userid', $moodleUser->id)
        ->select('role.id', 'role.shortname', 'role.name', 'role_assignments.contextid')
        ->get();

    if ($roleAssignments->count() > 0) {
        foreach($roleAssignments as $ra) {
            echo "  - Role [{$ra->id}] {$ra->shortname} in context {$ra->contextid}\n";
        }
    } else {
        echo "  NO ROLE ASSIGNED!\n";
    }
} else {
    echo "User not found in Moodle\n";
}

echo "\n=== SYSTEM CONTEXT ===\n";
$systemContext = DB::connection('moodle')
    ->table('context')
    ->where('contextlevel', 10) // CONTEXT_SYSTEM
    ->first();
echo "System Context ID: {$systemContext->id}\n";
