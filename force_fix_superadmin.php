#!/usr/bin/env php
<?php
/**
 * Force update super admin role di database
 * Usage: php force_fix_superadmin.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

echo "\n═══════════════════════════════════════════════════════════════\n";
echo "  🔐 FORCE FIX SUPER ADMIN ROLE - DIRECT DATABASE UPDATE\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

try {
    // 1. Find super admin user (ID 1)
    $superAdmin = User::find(1);
    if (!$superAdmin) {
        echo "❌ Super admin user ID 1 tidak ditemukan\n\n";
        exit(1);
    }
    
    echo "🔍 Found user: {$superAdmin->name} ({$superAdmin->email})\n";
    echo "Current roles: " . ($superAdmin->roles->count() > 0 ? $superAdmin->roles->pluck('name')->join(', ') : 'NONE') . "\n\n";
    
    // 2. Ensure super-admin role exists
    $role = Role::firstOrCreate(
        ['name' => 'super-admin'],
        ['guard_name' => 'api']
    );
    echo "✅ Super-admin role ID: {$role->id}\n\n";
    
    // 3. Clear existing roles
    DB::table('model_has_roles')
        ->where('model_type', 'App\\Models\\User')
        ->where('model_id', 1)
        ->delete();
    echo "🗑️  Cleared old roles\n";
    
    // 4. Assign super-admin role
    DB::table('model_has_roles')->insert([
        'role_id' => $role->id,
        'model_type' => 'App\\Models\\User',
        'model_id' => 1,
    ]);
    echo "✅ Assigned super-admin role\n";
    
    // 5. Update role_override column if it exists in schema
    try {
        $superAdmin->update(['role_override' => 'super-admin']);
        echo "✅ Set role_override to super-admin\n";
    } catch (\Exception $e) {
        echo "⚠️  role_override column tidak ada (optional)\n";
    }
    echo "\n";
    
    // 6. Verify
    $updated = User::find(1);
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "✅ SUCCESS! Super admin role updated:\n";
    echo "   Name: {$updated->name}\n";
    echo "   Email: {$updated->email}\n";
    echo "   Roles: " . $updated->roles->pluck('name')->join(', ') . "\n";
    if ($updated->role_override) {
        echo "   Role Override: {$updated->role_override}\n";
    }
    echo "═══════════════════════════════════════════════════════════════\n\n";
    
    echo "⏱️  Tunggu 5 detik untuk database sync...\n";
    sleep(5);
    
    echo "✅ Done! Refresh browser untuk lihat perubahan.\n\n";
    
} catch (\Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    echo "   Stack: {$e->getFile()}:{$e->getLine()}\n\n";
    exit(1);
}
