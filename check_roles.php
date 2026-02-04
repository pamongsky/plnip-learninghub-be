<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== PORTAL ORACLE - ROLES & USERS ===\n\n";

echo "ROLES:\n";
$roles = DB::table('roles')->get();
foreach($roles as $r) {
    echo "  [{$r->id}] {$r->name}\n";
}

echo "\nUSER ROLES:\n";
$ur = DB::table('model_has_roles')
    ->join('users', 'model_has_roles.model_id', '=', 'users.id')
    ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
    ->select('users.id', 'users.name', 'users.email', 'users.moodle_user_id', 'roles.name as role_name', 'roles.id as role_id')
    ->get();

foreach($ur as $u) {
    $moodle = $u->moodle_user_id ?: 'NULL';
    echo "  [{$u->id}] {$u->name} | {$u->email} | role:{$u->role_name}({$u->role_id}) | moodle_id:{$moodle}\n";
}

echo "\n=== SUPERADMIN (role_id=1) ===\n";
$superadmins = DB::table('model_has_roles')
    ->join('users', 'model_has_roles.model_id', '=', 'users.id')
    ->where('model_has_roles.role_id', 1)
    ->select('users.*')
    ->get();

foreach($superadmins as $sa) {
    echo "  [{$sa->id}] {$sa->name} | {$sa->email} | moodle_id:{$sa->moodle_user_id}\n";
}
