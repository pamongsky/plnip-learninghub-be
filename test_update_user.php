#!/usr/bin/env php
<?php
/**
 * Test update user role
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Services\UserService;

echo "\n═══════════════════════════════════════════════════════════════\n";
echo "  🧪 TEST UPDATE USER ROLE\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

try {
    // Get Admin user
    $user = User::find(2);
    echo "👤 User: {$user->name} ({$user->email})\n";
    echo "📍 Current role: " . $user->roles->pluck('name')->join(', ') . "\n\n";

    // Test updateUser service
    echo "🔄 Testing UserService::updateUser()...\n";
    
    $adminUser = User::find(1); // Super admin as updater
    
    $data = [
        'role' => 'instructor'
    ];
    
    echo "   Attempting to set role to: instructor\n";
    
    UserService::updateUser($user, $data, $adminUser);
    
    echo "   ✅ Update succeeded!\n\n";
    
    // Reload and check
    $user->refresh();
    $user->load('roles');
    echo "📍 New role: " . $user->roles->pluck('name')->join(', ') . "\n";
    echo "✅ Effective role: " . UserService::getEffectiveRole($user) . "\n";
    
} catch (\Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    echo "📌 File: {$e->getFile()}:{$e->getLine()}\n";
    echo "\n📋 Stack trace:\n";
    echo $e->getTraceAsString();
}

echo "\n═══════════════════════════════════════════════════════════════\n";
