<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== VERIFY MOODLE SITE ADMIN STATUS ===\n\n";

$email = 'superadmin@plnip.local';

// 1. Get Moodle user
$user = DB::connection('moodle')->table('user')->where('email', $email)->where('deleted', 0)->first();
echo "User: {$user->firstname} {$user->lastname} ({$user->email})\n";
echo "User ID: {$user->id}\n";
echo "Username: {$user->username}\n\n";

// 2. Check siteadmins config
$config = DB::connection('moodle')->table('config')->where('name', 'siteadmins')->first();
echo "Siteadmins config: {$config->value}\n";
$adminIds = explode(',', $config->value);
echo "Is user in siteadmins? " . (in_array($user->id, $adminIds) ? "YES ✓" : "NO ✗") . "\n\n";

// 3. Check role assignment
$roleAssignment = DB::connection('moodle')
    ->table('role_assignments as ra')
    ->join('role as r', 'ra.roleid', '=', 'r.id')
    ->join('context as ctx', 'ra.contextid', '=', 'ctx.id')
    ->where('ra.userid', $user->id)
    ->where('ctx.contextlevel', 10) // System context
    ->select('ra.roleid', 'r.shortname', 'r.archetype', 'ctx.id as contextid')
    ->first();

if ($roleAssignment) {
    echo "System Role: [{$roleAssignment->roleid}] {$roleAssignment->shortname} (archetype: {$roleAssignment->archetype})\n";
    echo "Context ID: {$roleAssignment->contextid}\n\n";
} else {
    echo "NO SYSTEM ROLE ASSIGNED! ✗\n\n";
}

// 4. Check capabilities
echo "=== CHECKING MOODLE CAPABILITIES ===\n";
$adminRole = DB::connection('moodle')
    ->table('role_capabilities')
    ->where('roleid', 1)
    ->where('capability', 'moodle/site:config')
    ->first();

if ($adminRole) {
    echo "Role 1 has moodle/site:config: permission={$adminRole->permission}\n";
} else {
    echo "Role 1 does NOT have moodle/site:config capability!\n";
}

// 5. Generate fresh SSO URL
echo "\n=== GENERATING FRESH SSO URL ===\n";
$key = \Illuminate\Support\Str::random(32);
$validUntil = now()->addMinutes(10)->timestamp;

DB::connection('moodle')->table('user_private_key')->insert([
    'script' => 'auth/userkey',
    'value' => $key,
    'userid' => $user->id,
    'instance' => null,
    'iprestriction' => null,
    'validuntil' => $validUntil,
    'timecreated' => now()->timestamp
]);

$moodleUrl = env('MOODLE_URL');
$loginUrl = $moodleUrl . '/auth/userkey/login.php?key=' . $key;

echo "\nFRESH SSO URL:\n";
echo $loginUrl . "\n\n";
echo "INSTRUCTIONS:\n";
echo "1. Logout dari Moodle (jika sudah login)\n";
echo "2. Copy URL di atas\n";
echo "3. Paste di browser dan Enter\n";
echo "4. Setelah login, klik nama 'SA' atau 'Super Administrator' di kanan atas\n";
echo "5. Harus ada menu 'Site administration'\n";
