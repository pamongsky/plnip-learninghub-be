#!/usr/bin/env php
<?php
/**
 * Debug roles dan permissions
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

echo "\n═══════════════════════════════════════════════════════════════\n";
echo "  🔍 DEBUG ROLES & PERMISSIONS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// 1. Check roles table
echo "📋 Available Roles in Database:\n";
$roles = Role::all();
foreach ($roles as $role) {
    echo "  ID: {$role->id}, Name: {$role->name}, Guard: {$role->guard_name}\n";
}

if ($roles->count() === 0) {
    echo "  ⚠️  NO ROLES FOUND!\n";
}
echo "\n";

// 2. Check model_has_roles table
echo "👥 User Roles Assignments:\n";
$assignments = DB::table('model_has_roles')->get();
foreach ($assignments as $assign) {
    echo "  User ID: {$assign->model_id}, Role ID: {$assign->role_id}\n";
}

if ($assignments->count() === 0) {
    echo "  ⚠️  NO USER ROLE ASSIGNMENTS FOUND!\n";
}
echo "\n";

// 3. Check specific users
echo "🔎 User Role Details:\n";
$users = User::with('roles')->limit(5)->get();
foreach ($users as $user) {
    $roleNames = $user->roles->pluck('name')->join(', ') ?: 'NONE';
    echo "  {$user->name} ({$user->email}): {$roleNames}\n";
}
echo "\n";

// 4. Test syncRoles
echo "🧪 Testing syncRoles()...\n";
try {
    $testUser = User::find(2); // Admin PLN IP
    if ($testUser) {
        echo "  Testing with: {$testUser->name}\n";
        echo "  Current roles: " . $testUser->roles->pluck('name')->join(', ') . "\n";
        
        // Try to sync to instructor
        $instructorRole = Role::where('name', 'instructor')->first();
        if (!$instructorRole) {
            echo "  ❌ Instructor role not found!\n";
            echo "  Creating instructor role...\n";
            $instructorRole = Role::create(['name' => 'instructor', 'guard_name' => 'api']);
            echo "  ✅ Created instructor role with ID: {$instructorRole->id}\n";
        }
        
        echo "  Attempting syncRoles(['instructor'])...\n";
        $testUser->syncRoles(['instructor']);
        echo "  ✅ syncRoles succeeded\n";
        
        $testUser->refresh();
        echo "  After sync: " . $testUser->roles->pluck('name')->join(', ') . "\n";
    }
} catch (\Exception $e) {
    echo "  ❌ Error: {$e->getMessage()}\n";
}

echo "\n═══════════════════════════════════════════════════════════════\n";
