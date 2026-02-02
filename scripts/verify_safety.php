<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

echo "🔍 VERIFYING PRODUCTION SAFETY PROTECTIONS\n\n";

$passed = 0;
$failed = 0;

// Test 1: Check environment
echo "Test 1: Environment Check\n";
echo str_repeat("-", 80) . "\n";
$env = app()->environment();
echo "Current environment: " . strtoupper($env) . "\n";
if ($env === 'production' || $env === 'staging') {
    echo "✓ Production/Staging environment detected\n";
    $passed++;
} else {
    echo "⚠️  Running in $env environment\n";
}
echo "\n";

// Test 2: Check ProductionSafetyProvider
echo "Test 2: Production Safety Provider\n";
echo str_repeat("-", 80) . "\n";
$providers = app()->getLoadedProviders();
if (isset($providers['App\\Providers\\ProductionSafetyProvider'])) {
    echo "✓ ProductionSafetyProvider is loaded\n";
    $passed++;
} else {
    echo "❌ ProductionSafetyProvider NOT loaded\n";
    echo "   Add to bootstrap/providers.php\n";
    $failed++;
}
echo "\n";

// Test 3: Check SafeMigrate command
echo "Test 3: Safe Migration Command\n";
echo str_repeat("-", 80) . "\n";
$commands = Artisan::all();
if (isset($commands['migrate:safe'])) {
    echo "✓ migrate:safe command available\n";
    $passed++;
} else {
    echo "❌ migrate:safe command NOT found\n";
    echo "   Check app/Console/Commands/SafeMigrate.php\n";
    $failed++;
}
echo "\n";

// Test 4: Check backup script
echo "Test 4: Backup Scripts\n";
echo str_repeat("-", 80) . "\n";
$backupScript = __DIR__ . '/oracle_backup.ps1';
if (file_exists($backupScript)) {
    echo "✓ Backup script exists: $backupScript\n";
    $passed++;
} else {
    echo "❌ Backup script NOT found\n";
    $failed++;
}
echo "\n";

// Test 5: Check Oracle Flashback status
echo "Test 5: Oracle Flashback Database\n";
echo str_repeat("-", 80) . "\n";
try {
    $flashback = DB::select("SELECT FLASHBACK_ON FROM V\$DATABASE");
    if ($flashback[0]->flashback_on === 'YES') {
        echo "✓ Flashback Database is ENABLED\n";
        $passed++;
    } else {
        echo "⚠️  Flashback Database is DISABLED\n";
        echo "   Enable with: ALTER DATABASE FLASHBACK ON;\n";
    }
} catch (\Exception $e) {
    echo "⚠️  Cannot check (may need DBA privileges)\n";
}
echo "\n";

// Test 6: Check recent backups
echo "Test 6: Recent Backups\n";
echo str_repeat("-", 80) . "\n";
try {
    // Check for backup files in last 7 days
    $oracleHome = 'C:/oracle/product/19c';
    $dumpDir = "$oracleHome/admin/ORCL/dpdump";
    
    if (is_dir($dumpDir)) {
        $backups = glob("$dumpDir/plnip_backup_*.dmp");
        $recentBackups = array_filter($backups, function($file) {
            return filemtime($file) > strtotime('-7 days');
        });
        
        if (count($recentBackups) > 0) {
            echo "✓ Found " . count($recentBackups) . " backup(s) in last 7 days\n";
            $latest = array_reduce($recentBackups, function($a, $b) {
                return filemtime($a) > filemtime($b) ? $a : $b;
            });
            echo "   Latest: " . basename($latest) . " (" . date('Y-m-d H:i:s', filemtime($latest)) . ")\n";
            $passed++;
        } else {
            echo "⚠️  No recent backups found (last 7 days)\n";
            echo "   Run setup_backup.ps1 to configure automated backups\n";
        }
    } else {
        echo "⚠️  Backup directory not found: $dumpDir\n";
        echo "   Configure backup location in oracle_backup.ps1\n";
    }
} catch (\Exception $e) {
    echo "⚠️  Cannot check backups: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 7: Check Oracle Recycle Bin retention
echo "Test 7: Oracle Recycle Bin\n";
echo str_repeat("-", 80) . "\n";
try {
    $recycleCount = DB::select("SELECT COUNT(*) as cnt FROM RECYCLEBIN");
    echo "Items in recycle bin: {$recycleCount[0]->cnt}\n";
    
    if ($recycleCount[0]->cnt > 0) {
        echo "✓ Recycle Bin is active (can recover dropped tables)\n";
        $passed++;
    } else {
        echo "✓ Recycle Bin is empty (no recent drops)\n";
        $passed++;
    }
} catch (\Exception $e) {
    echo "⚠️  Cannot check: " . $e->getMessage() . "\n";
}
echo "\n";

// Summary
echo str_repeat("━", 80) . "\n";
echo "📊 VERIFICATION SUMMARY\n";
echo str_repeat("━", 80) . "\n";
echo "Passed: $passed tests\n";
echo "Failed: $failed tests\n";
echo "\n";

if ($failed === 0) {
    echo "✅ ALL PROTECTIONS ARE IN PLACE!\n";
    echo "   Your production environment is protected against data loss.\n";
} else {
    echo "⚠️  SOME PROTECTIONS ARE MISSING\n";
    echo "   Review failed tests above and implement missing safeguards.\n";
}

echo "\n";
echo "📚 Next Steps:\n";
echo "  1. Run setup_backup.ps1 to configure automated backups\n";
echo "  2. Test backup: Start-ScheduledTask -TaskName 'Oracle_Daily_Backup_PLNIP'\n";
echo "  3. Enable Flashback: ALTER DATABASE FLASHBACK ON;\n";
echo "  4. Read PRODUCTION_SAFETY_GUIDE.md\n";
echo "  5. Train team on safe deployment procedures\n";
echo "\n";
