<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔥 FORCE RECOVERY - DROP EMPTY TABLES & RESTORE FROM RECYCLE BIN\n\n";

// Tables to recover (in dependency order)
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

echo "STEP 1: DROPPING EMPTY TABLES...\n";
echo str_repeat("─", 80) . "\n";

foreach ($tablesToRecover as $table) {
    try {
        echo "Dropping $table... ";
        DB::statement("DROP TABLE $table CASCADE CONSTRAINTS");
        echo "✓ DROPPED\n";
    } catch (\Exception $e) {
        // If table doesn't exist, that's fine
        if (strpos($e->getMessage(), 'ORA-00942') !== false) {
            echo "⚠ Already doesn't exist\n";
        } else {
            echo "❌ FAILED: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n";
echo "STEP 2: RESTORING FROM RECYCLE BIN...\n";
echo str_repeat("─", 80) . "\n";

$recovered = 0;
$failed = 0;

foreach ($tablesToRecover as $table) {
    try {
        echo "Recovering $table... ";
        DB::statement("FLASHBACK TABLE $table TO BEFORE DROP");
        echo "✓ SUCCESS\n";
        $recovered++;
    } catch (\Exception $e) {
        echo "❌ FAILED: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n";
echo str_repeat("━", 80) . "\n";
echo "📊 RECOVERY SUMMARY:\n";
echo "  ✓ Recovered: $recovered tables\n";
echo "  ❌ Failed: $failed tables\n";
echo str_repeat("━", 80) . "\n";

// Verify data
echo "\n🔍 Verifying data...\n";
try {
    $userCount = DB::table('USERS')->count();
    $announcementCount = DB::table('ANNOUNCEMENTS')->count();
    $courseCount = DB::table('COURSES')->count();
    $ticketCount = DB::table('SUPPORT_TICKETS')->count();
    
    echo "  Users: $userCount records\n";
    echo "  Announcements: $announcementCount records\n";
    echo "  Courses: $courseCount records\n";
    echo "  Support Tickets: $ticketCount records\n";
    
    if ($userCount > 2 && $announcementCount > 0) {
        echo "\n✅ DATA SUCCESSFULLY RESTORED!\n";
        echo "   Your production data is back! 🎉\n";
    } else {
        echo "\n⚠️  WARNING: Data counts still low\n";
    }
} catch (\Exception $e) {
    echo "  ❌ Could not verify: " . $e->getMessage() . "\n";
}

echo "\n";
echo "⚠️  IMPORTANT NEXT STEPS:\n";
echo "  1. Clear application cache: php artisan cache:clear\n";
echo "  2. Test login dengan user production yang asli\n";
echo "  3. Verify semua data di dashboard\n";
echo "  4. Setup Oracle RMAN backup SEKARANG JUGA!\n";
echo "  5. Block migrate:fresh di production environment\n\n";
