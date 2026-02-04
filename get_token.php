<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

$user = User::where('email', 'superadmin@plnip.local')->first();
if ($user) {
    $token = $user->createToken('api_test')->plainTextToken;
    echo "Token: $token\n";
} else {
    echo "No superadmin user found\n";
    // Find any superadmin
    $admins = User::role('super-admin')->get();
    foreach ($admins as $admin) {
        echo "Found: {$admin->email}\n";
    }
}
