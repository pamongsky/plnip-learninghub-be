<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🚀 AUTO RECOVERY FROM ORACLE RECYCLE BIN\n\n";

// Tables to recover (in order of dependency)
$tablesToRecover = [
    'USERS',
    'ROLES',
    'PERMISSIONS',
    'MODEL_HAS_ROLES',
    'ROLE_HAS_PERMISSIONS',
    'MODEL_HAS_PERMISSIONS',
    'ANNOUNCEMENTS',
    'COURSES',
    'COURSE_ENROLLMENTS',
    'SUPPORT_TICKETS',
    'SUPPORT_REPLIES',
    'ESCALATION_TICKETS',
    'ESCALATION_REPLIES',
    'CLASS_MESSAGES',
    'CONVERSATIONS',
    'DIRECT_MESSAGES',
    'CHAT_SESSIONS',
    'CHAT_MESSAGES',
    'CHAT_ATTACHMENTS',
    'ACTIVITY_LOGS',
    'AUDIT_LOGS',
    'PERSONAL_ACCESS_TOKENS',
    'CMS_LEADERS',
    'CMS_PARTNERS',
    'LANDING_PAGE_SETTINGS',
];

$recovered = 0;
$failed = 0;

foreach ($tablesToRecover as $table) {
    try {
        echo "Recovering {$table}... ";
        DB::statement("FLASHBACK TABLE {$table} TO BEFORE DROP");
        echo "✓ SUCCESS\n";
        $recovered++;
    } catch (\Exception $e) {
        // Check if table already exists
        if (stripos($e->getMessage(), 'already exists') !== false || stripos($e->getMessage(), 'name is already used') !== false) {
            echo "⚠️  Already exists (skipping)\n";
        } else {
            echo "❌ FAILED: " . $e->getMessage() . "\n";
            $failed++;
        }
    }
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n📊 RECOVERY SUMMARY:\n";
echo "  ✓ Recovered: {$recovered} tables\n";
echo "  ❌ Failed: {$failed} tables\n\n";

// Verify data
echo "🔍 Verifying data...\n";
$tables = [
    'users' => 'Users',
    'announcements' => 'Announcements',
    'courses' => 'Courses',
    'support_tickets' => 'Support Tickets',
];

foreach ($tables as $table => $name) {
    try {
        $count = DB::table($table)->count();
        echo "  {$name}: {$count} records\n";
    } catch (\Exception $e) {
        echo "  {$name}: ⚠️  Cannot count\n";
    }
}

echo "\n✅ RECOVERY COMPLETE!\n\n";
echo "⚠️  IMPORTANT NEXT STEPS:\n";
echo "  1. Clear application cache: php artisan cache:clear\n";
echo "  2. Test login dengan user yang ada\n";
echo "  3. Verify data di dashboard\n";
echo "  4. Setup automatic backup going forward!\n\n";
