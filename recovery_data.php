<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

echo "🔧 Starting Data Recovery...\n\n";

// 1. Create Roles
echo "Creating Roles...\n";
$roles = [
    'super-admin' => 'Super Administrator with full access',
    'admin' => 'Administrator with management access',
    'instructor' => 'Instructor/Teacher role',
    'employee' => 'Regular employee/student role',
];

foreach ($roles as $roleName => $description) {
    $role = Role::firstOrCreate(
        ['name' => $roleName, 'guard_name' => 'web']
    );
    echo "  ✓ Role: {$roleName}\n";
}

// 2. Create Essential Permissions
echo "\nCreating Permissions...\n";
$permissions = [
    'manage users',
    'manage roles',
    'manage announcements',
    'manage courses',
    'manage support tickets',
    'view dashboard',
    'manage settings',
];

foreach ($permissions as $permissionName) {
    Permission::firstOrCreate(
        ['name' => $permissionName, 'guard_name' => 'web']
    );
    echo "  ✓ Permission: {$permissionName}\n";
}

// 3. Assign all permissions to super-admin
$superAdminRole = Role::where('name', 'super-admin')->first();
$superAdminRole->givePermissionTo(Permission::all());
echo "\n  ✓ All permissions assigned to super-admin\n";

// 4. Create Super Admin User
echo "\nCreating Super Admin User...\n";
$superAdmin = User::firstOrCreate(
    ['email' => 'admin@plnip.co.id'],
    [
        'name' => 'Super Admin',
        'password' => bcrypt('admin123'),
        'email_verified_at' => now(),
        'user_source' => 'local',
    ]
);
$superAdmin->assignRole('super-admin');
echo "  ✓ Super Admin created\n";
echo "     Email: admin@plnip.co.id\n";
echo "     Password: admin123\n";

// 5. Create Demo Admin
echo "\nCreating Demo Admin...\n";
$admin = User::firstOrCreate(
    ['email' => 'demo@plnip.co.id'],
    [
        'name' => 'Demo Admin',
        'password' => bcrypt('demo123'),
        'email_verified_at' => now(),
        'user_source' => 'local',
    ]
);
$admin->assignRole('admin');
echo "  ✓ Demo Admin created\n";
echo "     Email: demo@plnip.co.id\n";
echo "     Password: demo123\n";

// 6. Summary
echo "\n✅ Recovery Complete!\n\n";
echo "Login Credentials:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Super Admin:\n";
echo "  Email: admin@plnip.co.id\n";
echo "  Password: admin123\n\n";
echo "Demo Admin:\n";
echo "  Email: demo@plnip.co.id\n";
echo "  Password: demo123\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
echo "⚠️  IMPORTANT: Change passwords immediately after login!\n\n";
