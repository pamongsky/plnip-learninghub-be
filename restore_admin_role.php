#!/usr/bin/env php
<?php
/**
 * Restore admin role to admin user
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "\n═══════════════════════════════════════════════════════════════\n";
echo "  🔧 RESTORE ADMIN ROLE\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

try {
    $admin = User::find(2);
    echo "👤 User: {$admin->name}\n";
    echo "   Current role: " . $admin->roles->pluck('name')->join(', ') . "\n\n";
    
    // Get admin role ID
    $adminRole = \Spatie\Permission\Models\Role::where('name', 'admin')->first();
    
    if (!$adminRole) {
        echo "❌ Admin role not found!\n";
        exit(1);
    }
    
    echo "🔄 Restoring admin role...\n";
    
    // Clear old roles
    DB::table('model_has_roles')
        ->where('model_type', User::class)
        ->where('model_id', $admin->id)
        ->delete();
    
    // Assign admin role
    DB::table('model_has_roles')->insert([
        'role_id' => $adminRole->id,
        'model_type' => User::class,
        'model_id' => $admin->id,
    ]);
    
    echo "✅ Admin role restored!\n\n";
    
    $admin->refresh();
    echo "📍 New role: " . $admin->roles->pluck('name')->join(', ') . "\n";
    
} catch (\Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
}

echo "\n═══════════════════════════════════════════════════════════════\n";
