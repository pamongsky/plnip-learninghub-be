<?php

// Script untuk restore super admin role
// Run: php artisan tinker < restore_superadmin.php

use App\Models\User;
use Spatie\Permission\Models\Role;

// Find super admin user (asumsi user pertama atau cari by email)
$superAdmin = User::where('email', 'superadmin@plnip.co.id')->first() 
    ?? User::where('name', 'Super Admin')->first() 
    ?? User::first();

if (!$superAdmin) {
    echo "❌ Super admin user tidak ditemukan\n";
    exit(1);
}

echo "🔍 Ditemukan user: {$superAdmin->name} ({$superAdmin->email})\n";
echo "Current role: {$superAdmin->roles->pluck('name')->join(', ')}\n";

// Ensure super-admin role exists
$superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);

// Assign super-admin role
$superAdmin->syncRoles(['super-admin']);

echo "✅ Super admin role restored!\n";
echo "Role sekarang: {$superAdmin->fresh()->roles->pluck('name')->join(', ')}\n";
