#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

$superAdmin = Role::where('name', 'super-admin')->first();
if ($superAdmin) {
    $allPerms = Permission::all();
    $superAdmin->syncPermissions($allPerms);
    echo "✅ All {$allPerms->count()} permissions assigned to super-admin\n";
} else {
    echo "❌ Super admin role not found\n";
}

$user = Role::where('name', 'user')->first();
if ($user) {
    echo "✅ User role has {$user->permissions()->count()} permissions\n";
}
