#!/usr/bin/env php
<?php
/**
 * Script untuk restore super admin role
 * Usage: php restore_superadmin_cli.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

echo "\n═══════════════════════════════════════\n";
echo "  🔐 RESTORE SUPER ADMIN ROLE SCRIPT\n";
echo "═══════════════════════════════════════\n\n";

// Cari super admin user
$superAdmins = User::whereHas('roles', function($query) {
    $query->where('name', 'super-admin');
})->get();

if ($superAdmins->count() > 0) {
    echo "✅ Super admin users ditemukan:\n";
    foreach ($superAdmins as $admin) {
        echo "   • {$admin->name} ({$admin->email}) - ID: {$admin->id}\n";
    }
    echo "\n";
    exit(0);
}

// Cari by email pattern atau first user
$candidates = User::where('email', 'like', '%superadmin%')
    ->orWhere('email', 'like', '%admin@%')
    ->orWhere('name', 'like', '%Super Admin%')
    ->get();

if ($candidates->count() === 0) {
    echo "❌ Super admin user tidak ditemukan.\n";
    echo "   Cek database atau gunakan tinker untuk manual restore.\n\n";
    exit(1);
}

echo "🔍 Kandidat user untuk restore:\n\n";
foreach ($candidates as $i => $user) {
    echo ($i + 1) . ". {$user->name} ({$user->email}) - Current Role: " . 
        ($user->roles->count() > 0 ? $user->roles->pluck('name')->join(', ') : 'NONE') . "\n";
}

echo "\nPilih nomor (1-" . $candidates->count() . "): ";
$choice = trim(fgets(STDIN));

if (!is_numeric($choice) || $choice < 1 || $choice > $candidates->count()) {
    echo "❌ Pilihan tidak valid\n\n";
    exit(1);
}

$superAdmin = $candidates[$choice - 1];

echo "\n🔄 Restoring super admin role untuk: {$superAdmin->name}\n";

try {
    DB::beginTransaction();

    // Create role if not exists
    Role::firstOrCreate(['name' => 'super-admin']);

    // Assign role
    $superAdmin->syncRoles(['super-admin']);
    
    // Also set role_override to super-admin as backup
    $superAdmin->update(['role_override' => 'super-admin']);

    DB::commit();

    echo "✅ Super admin role restored!\n";
    echo "   User: {$superAdmin->name}\n";
    echo "   Email: {$superAdmin->email}\n";
    echo "   Roles: " . $superAdmin->fresh()->roles->pluck('name')->join(', ') . "\n";
    echo "\n";
    
    exit(0);
} catch (\Exception $e) {
    DB::rollBack();
    echo "❌ Error: {$e->getMessage()}\n\n";
    exit(1);
}
