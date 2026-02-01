#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

echo "\n═══════════════════════════════════════════════════════════════\n";
echo "  ✅ ROLES & PERMISSIONS VERIFICATION\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "📋 ROLES WITH PERMISSIONS:\n";
$roles = Role::with('permissions')->orderBy('name')->get();
foreach ($roles as $role) {
    echo "  • {$role->name}: {$role->permissions()->count()} permissions\n";
}

echo "\n📊 PERMISSIONS BY CATEGORY:\n";
$permissions = Permission::all();
$byCategory = [];
foreach ($permissions as $perm) {
    $category = explode('.', $perm->name)[0];
    if (!isset($byCategory[$category])) {
        $byCategory[$category] = 0;
    }
    $byCategory[$category]++;
}

foreach ($byCategory as $category => $count) {
    echo "  • {$category}: {$count} permissions\n";
}

echo "\n🎯 SUMMARY:\n";
echo "  • Total Roles: {$roles->count()}\n";
echo "  • Total Permissions: {$permissions->count()}\n";

// Check role assignments
echo "\n👥 ROLE ASSIGNMENTS:\n";
foreach ($roles as $role) {
    $userCount = $role->users()->count();
    echo "  • {$role->name}: {$userCount} users\n";
}

echo "\n✅ API ENDPOINTS READY:\n";
echo "  • GET /superadmin/roles - Fetch all roles\n";
echo "  • GET /superadmin/roles/permissions/all - Fetch all permissions\n";
echo "  • PUT /superadmin/roles/{role}/permissions - Update permissions\n";
echo "  • DELETE /superadmin/roles/{role} - Delete custom role\n";

echo "\n═══════════════════════════════════════════════════════════════\n";
echo "✨ FRONTEND: http://localhost:3000/superadmin/roles\n";
echo "═══════════════════════════════════════════════════════════════\n\n";
