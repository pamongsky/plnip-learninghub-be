<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

echo "🔥 CLEANING UP & CREATING SUPERADMIN ONLY\n\n";

// Step 1: Delete all existing users
echo "Step 1: Deleting all existing users...\n";
echo str_repeat("-", 80) . "\n";

try {
    $existingUsers = User::all();
    echo "Found " . count($existingUsers) . " users to delete\n";

    foreach ($existingUsers as $user) {
        echo "  Deleting: {$user->email}... ";
        $user->delete();
        echo "✓\n";
    }

    echo "✓ All users deleted\n\n";
} catch (\Exception $e) {
    echo "❌ Error deleting users: " . $e->getMessage() . "\n\n";
}

// Step 2: Ensure super-admin role exists
echo "Step 2: Checking super-admin role...\n";
echo str_repeat("-", 80) . "\n";

try {
    $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);
    echo "✓ Super-admin role ready\n\n";
} catch (\Exception $e) {
    echo "❌ Error with role: " . $e->getMessage() . "\n\n";
}

// Step 3: Create superadmin@plnip.local
echo "Step 3: Creating superadmin@plnip.local...\n";
echo str_repeat("-", 80) . "\n";

try {
    $superAdmin = User::create([
        'name' => 'Super Administrator',
        'email' => 'superadmin@plnip.local',
        'password' => Hash::make('superadmin123'),
        'email_verified_at' => now(),
    ]);

    echo "✓ User created\n";
    echo "  Email: superadmin@plnip.local\n";
    echo "  Password: superadmin123\n";
    echo "  ID: {$superAdmin->id}\n\n";

    // Assign super-admin role
    echo "Step 4: Assigning super-admin role...\n";
    echo str_repeat("-", 80) . "\n";

    $superAdmin->assignRole('super-admin');
    echo "✓ Role assigned\n\n";

    // Give all permissions
    echo "Step 5: Assigning all permissions...\n";
    echo str_repeat("-", 80) . "\n";

    $permissions = Permission::all();
    if (count($permissions) > 0) {
        $superAdminRole->syncPermissions($permissions);
        echo "✓ All " . count($permissions) . " permissions assigned to super-admin role\n";
    } else {
        echo "⚠️  No permissions found in database\n";
    }

} catch (\Exception $e) {
    echo "❌ Error creating superadmin: " . $e->getMessage() . "\n\n";
}

// Verify
echo "\n";
echo str_repeat("━", 80) . "\n";
echo "✅ FINAL STATUS\n";
echo str_repeat("━", 80) . "\n\n";

try {
    $totalUsers = User::count();
    $superAdmin = User::where('email', 'superadmin@plnip.local')->first();

    echo "Total Users: $totalUsers\n\n";

    if ($superAdmin) {
        echo "✓ Superadmin Account:\n";
        echo "  Email: {$superAdmin->email}\n";
        echo "  Name: {$superAdmin->name}\n";
        echo "  Password: superadmin123\n";
        echo "  Roles: " . implode(', ', $superAdmin->getRoleNames()->toArray()) . "\n";
        echo "  Permissions: " . $superAdmin->getAllPermissions()->count() . " permissions\n";
    } else {
        echo "❌ Superadmin not found!\n";
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";
echo "🔐 LOGIN CREDENTIALS:\n";
echo "   Email: superadmin@plnip.local\n";
echo "   Password: superadmin123\n";
echo str_repeat("━", 80) . "\n";
