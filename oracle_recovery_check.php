<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔍 Checking Oracle Recovery Options...\n\n";

// 1. Check Recycle Bin
echo "1. Checking Recycle Bin for dropped tables:\n";
try {
    $recycled = DB::select("SELECT object_name, original_name, droptime FROM recyclebin WHERE type = 'TABLE' ORDER BY droptime DESC");
    if (count($recycled) > 0) {
        echo "   ✓ Found tables in recycle bin:\n";
        foreach ($recycled as $obj) {
            echo "     • {$obj->original_name} (dropped: {$obj->droptime})\n";
        }
        echo "\n   🔧 RECOVERY COMMAND:\n";
        echo "   FLASHBACK TABLE table_name TO BEFORE DROP;\n\n";
    } else {
        echo "   ❌ No tables in recycle bin\n\n";
    }
} catch (\Exception $e) {
    echo "   ⚠️  Cannot access recycle bin: " . $e->getMessage() . "\n\n";
}

// 2. Check Flashback capability
echo "2. Checking Flashback Database capability:\n";
try {
    $flashback = DB::select("SELECT flashback_on FROM v\$database");
    if (count($flashback) > 0 && $flashback[0]->flashback_on === 'YES') {
        echo "   ✓ Flashback is ENABLED\n";
        echo "   🔧 You can use Flashback Database!\n\n";
        
        // Get oldest flashback time
        $oldest = DB::select("SELECT oldest_flashback_time FROM v\$flashback_database_log");
        if (count($oldest) > 0) {
            echo "   Oldest restore point: {$oldest[0]->oldest_flashback_time}\n\n";
        }
    } else {
        echo "   ❌ Flashback is NOT enabled\n\n";
    }
} catch (\Exception $e) {
    echo "   ⚠️  Cannot check flashback: " . $e->getMessage() . "\n\n";
}

// 3. Check for restore points
echo "3. Checking Restore Points:\n";
try {
    $restorePoints = DB::select("SELECT name, scn, time, guarantee_flashback_database FROM v\$restore_point ORDER BY time DESC");
    if (count($restorePoints) > 0) {
        echo "   ✓ Found restore points:\n";
        foreach ($restorePoints as $rp) {
            echo "     • {$rp->name} - {$rp->time} (SCN: {$rp->scn})\n";
        }
        echo "\n";
    } else {
        echo "   ❌ No restore points found\n\n";
    }
} catch (\Exception $e) {
    echo "   ⚠️  Cannot check restore points: " . $e->getMessage() . "\n\n";
}

// 4. Check undo retention
echo "4. Checking Undo Retention:\n";
try {
    $undo = DB::select("SELECT value FROM v\$parameter WHERE name = 'undo_retention'");
    if (count($undo) > 0) {
        $seconds = $undo[0]->value;
        $hours = round($seconds / 3600, 1);
        echo "   Undo retention: {$seconds} seconds ({$hours} hours)\n";
        echo "   ✓ Can potentially use FLASHBACK QUERY within this window\n\n";
    }
} catch (\Exception $e) {
    echo "   ⚠️  Cannot check undo: " . $e->getMessage() . "\n\n";
}

// 5. Get current SCN for reference
echo "5. Current Database State:\n";
try {
    $scn = DB::select("SELECT current_scn FROM v\$database");
    if (count($scn) > 0) {
        echo "   Current SCN: {$scn[0]->current_scn}\n";
    }
    
    $time = DB::select("SELECT sysdate FROM dual");
    echo "   Current Time: {$time[0]->sysdate}\n\n";
} catch (\Exception $e) {
    echo "   ⚠️  Error: " . $e->getMessage() . "\n\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
echo "📋 RECOVERY OPTIONS:\n\n";

echo "OPTION 1: Flashback Table (if in recycle bin)\n";
echo "  SQL> FLASHBACK TABLE users TO BEFORE DROP;\n";
echo "  SQL> FLASHBACK TABLE announcements TO BEFORE DROP;\n";
echo "  (repeat for each table)\n\n";

echo "OPTION 2: Flashback Database (if enabled)\n";
echo "  1. Shutdown database\n";
echo "  2. STARTUP MOUNT;\n";
echo "  3. FLASHBACK DATABASE TO TIMESTAMP (SYSTIMESTAMP - INTERVAL '2' HOUR);\n";
echo "  4. ALTER DATABASE OPEN RESETLOGS;\n\n";

echo "OPTION 3: Flashback Query (query old data)\n";
echo "  SELECT * FROM users AS OF TIMESTAMP (SYSTIMESTAMP - INTERVAL '1' HOUR);\n";
echo "  (then INSERT into current table)\n\n";

echo "OPTION 4: RMAN Restore (if you have RMAN backups)\n";
echo "  RMAN> RESTORE DATABASE;\n";
echo "  RMAN> RECOVER DATABASE;\n\n";

echo "⚠️  CHOOSE BASED ON WHAT'S AVAILABLE ABOVE!\n\n";
