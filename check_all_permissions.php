<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

echo "🔍 CHECKING ALL PERMISSIONS IN DATABASE\n\n";

// Get all permissions
$permissions = Permission::orderBy('name')->get();

echo "Total Permissions: " . count($permissions) . "\n";
echo str_repeat("=", 80) . "\n\n";

if (count($permissions) > 0) {
    foreach ($permissions as $idx => $perm) {
        echo ($idx + 1) . ". " . $perm->name . "\n";
        echo "   Guard: " . $perm->guard_name . "\n";
        echo "   Created: " . $perm->created_at . "\n";
        
        // Check which roles have this permission
        $roles = DB::table('role_has_permissions')
            ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
            ->where('role_has_permissions.permission_id', $perm->id)
            ->pluck('roles.name')
            ->toArray();
        
        if (count($roles) > 0) {
            echo "   Assigned to: " . implode(', ', $roles) . "\n";
        } else {
            echo "   Assigned to: (none)\n";
        }
        echo "\n";
    }
} else {
    echo "❌ NO PERMISSIONS FOUND!\n\n";
    echo "This means the permissions table is empty.\n";
    echo "You need to seed permissions or create them manually.\n";
}

echo "\n";
echo str_repeat("=", 80) . "\n";
echo "ROLES & THEIR PERMISSIONS\n";
echo str_repeat("=", 80) . "\n\n";

$roles = Role::with('permissions')->get();

foreach ($roles as $role) {
    echo "📋 {$role->name}\n";
    echo "   Users: " . $role->users()->count() . "\n";
    echo "   Permissions: " . $role->permissions->count() . "\n";
    
    if ($role->permissions->count() > 0) {
        foreach ($role->permissions as $perm) {
            echo "      ✓ {$perm->name}\n";
        }
    } else {
        echo "      (no permissions assigned)\n";
    }
    echo "\n";
}

echo "\n";
echo "💡 ANALYSIS:\n";
echo str_repeat("-", 80) . "\n";

if (count($permissions) < 10) {
    echo "⚠️  WARNING: Only " . count($permissions) . " permissions exist\n";
    echo "   This seems too few for a complete system.\n\n";
    echo "   Typical permissions for a portal like this:\n";
    echo "   • User Management (view, create, edit, delete users)\n";
    echo "   • Role Management (view, create, edit, delete roles)\n";
    echo "   • Announcement Management (view, create, edit, delete, publish)\n";
    echo "   • Course Management (view, create, edit, delete, publish)\n";
    echo "   • Support Ticket Management (view, create, edit, delete, assign)\n";
    echo "   • Report Viewing (view reports, export reports)\n";
    echo "   • Settings Management (view, edit settings)\n";
    echo "   • Dashboard Access (view dashboard)\n\n";
    echo "   Recommended: Create more granular permissions\n";
} else {
    echo "✓ Permissions count looks reasonable\n";
}
