<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

echo "🔧 Creating Additional Users...\n\n";

// Instructor User
$instructor = User::firstOrCreate(
    ['email' => 'instructor@plnip.co.id'],
    [
        'name' => 'Demo Instructor',
        'password' => bcrypt('instructor123'),
        'email_verified_at' => now(),
        'user_source' => 'local',
    ]
);
$instructor->assignRole('instructor');
echo "✓ Instructor created\n";
echo "   Email: instructor@plnip.co.id\n";
echo "   Password: instructor123\n\n";

// Employee/Student User
$employee = User::firstOrCreate(
    ['email' => 'employee@plnip.co.id'],
    [
        'name' => 'Demo Employee',
        'password' => bcrypt('employee123'),
        'email_verified_at' => now(),
        'user_source' => 'local',
    ]
);
$employee->assignRole('employee');
echo "✓ Employee created\n";
echo "   Email: employee@plnip.co.id\n";
echo "   Password: employee123\n\n";

echo "✅ Done!\n\n";
echo "All Login Credentials:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Super Admin:\n";
echo "  Email: admin@plnip.co.id\n";
echo "  Password: admin123\n\n";
echo "Admin:\n";
echo "  Email: demo@plnip.co.id\n";
echo "  Password: demo123\n\n";
echo "Instructor:\n";
echo "  Email: instructor@plnip.co.id\n";
echo "  Password: instructor123\n\n";
echo "Employee:\n";
echo "  Email: employee@plnip.co.id\n";
echo "  Password: employee123\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
