<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

echo "🔄 FIXING PERMISSIONS - RESTORE FROM SEEDER\n\n";

echo "Step 1: Backup current permissions (just in case)...\n";
echo str_repeat("-", 80) . "\n";
$currentPermissions = Permission::pluck('name')->toArray();
echo "Current permissions: " . implode(', ', $currentPermissions) . "\n";
echo "Total: " . count($currentPermissions) . " permissions\n\n";

echo "Step 2: Clear all existing permissions...\n";
echo str_repeat("-", 80) . "\n";

// Clear role-permission assignments
DB::table('role_has_permissions')->truncate();
echo "✓ Cleared role-permission assignments\n";

// Clear model-permission assignments
DB::table('model_has_permissions')->truncate();
echo "✓ Cleared model-permission assignments\n";

// Delete all permissions
Permission::truncate();
echo "✓ Deleted all old permissions\n\n";

echo "Step 3: Run RolePermissionSeeder...\n";
echo str_repeat("-", 80) . "\n";

// Run the seeder
$seeder = new \Database\Seeders\RolePermissionSeeder();
$seeder->run();

echo "\nStep 4: Verify new permissions...\n";
echo str_repeat("-", 80) . "\n";

$newPermissions = Permission::orderBy('name')->get();
echo "New permissions count: " . count($newPermissions) . "\n\n";

// Group by category
$grouped = [];
foreach ($newPermissions as $perm) {
    $parts = explode('.', $perm->name);
    $category = $parts[0] ?? 'other';
    
    if (!isset($grouped[$category])) {
        $grouped[$category] = [];
    }
    $grouped[$category][] = $perm->name;
}

foreach ($grouped as $category => $perms) {
    echo "📁 " . strtoupper($category) . " (" . count($perms) . "):\n";
    foreach ($perms as $perm) {
        echo "   ✓ $perm\n";
    }
    echo "\n";
}

echo "Step 5: Verify role assignments...\n";
echo str_repeat("-", 80) . "\n";

$roles = Role::with('permissions')->get();
foreach ($roles as $role) {
    echo "📋 {$role->name}: {$role->permissions->count()} permissions\n";
}

echo "\n";
echo str_repeat("━", 80) . "\n";
echo "✅ PERMISSIONS RESTORED SUCCESSFULLY!\n";
echo str_repeat("━", 80) . "\n\n";

echo "Summary:\n";
echo "  Before: " . count($currentPermissions) . " permissions (basic/incomplete)\n";
echo "  After:  " . count($newPermissions) . " permissions (complete/granular)\n\n";

echo "💡 Next Steps:\n";
echo "  1. Clear cache: php artisan cache:clear\n";
echo "  2. Test login dan check permissions di UI\n";
echo "  3. Verify role access di frontend\n\n";
