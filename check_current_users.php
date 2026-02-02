<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔍 CHECKING CURRENT USERS IN DATABASE\n\n";

try {
    $users = DB::table('USERS')->select('id', 'name', 'email', 'created_at')->get();
    
    echo "Total users: " . count($users) . "\n";
    echo str_repeat("=", 80) . "\n\n";
    
    if (count($users) > 0) {
        foreach ($users as $user) {
            echo "ID: {$user->id}\n";
            echo "Name: {$user->name}\n";
            echo "Email: {$user->email}\n";
            echo "Created: {$user->created_at}\n";
            echo str_repeat("-", 80) . "\n";
        }
    } else {
        echo "❌ NO USERS FOUND\n";
    }
    
    echo "\n";
    echo "🔍 Checking specific accounts:\n";
    echo str_repeat("=", 80) . "\n";
    
    $checkEmails = [
        'superadmin@plnip.local',
        'admin@plnip.local',
        'instructor@plnip.local',
        'admin@plnip.co.id',
        'demo@plnip.co.id'
    ];
    
    foreach ($checkEmails as $email) {
        $exists = DB::table('USERS')->where('email', $email)->exists();
        echo ($exists ? "✓" : "❌") . " $email: " . ($exists ? "EXISTS" : "NOT FOUND") . "\n";
    }
    
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\n";
echo str_repeat("━", 80) . "\n";
echo "CONCLUSION:\n";
echo "Semua user yang dibuat SEBELUM migrate:fresh (11:01 AM hari ini) HILANG.\n";
echo "Yang ada sekarang hanya user yang dibuat SETELAH incident dari recovery_data.php\n";
echo str_repeat("━", 80) . "\n";
