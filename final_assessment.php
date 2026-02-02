<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🚨 CRITICAL DATA LOSS ASSESSMENT\n\n";

// Check recycle bin for USERS table specifically
$userVersions = DB::select("
    SELECT 
        OBJECT_NAME,
        ORIGINAL_NAME,
        DROPTIME,
        DROPSCN,
        SPACE
    FROM RECYCLEBIN
    WHERE ORIGINAL_NAME = 'USERS'
    ORDER BY DROPTIME DESC
");

echo "📊 USERS table in recycle bin: " . count($userVersions) . " version(s)\n";
echo str_repeat("=", 80) . "\n";

foreach ($userVersions as $idx => $version) {
    echo "Version " . ($idx + 1) . ":\n";
    echo "  Drop Time: {$version->droptime}\n";
    echo "  Space: {$version->space} blocks\n";
    echo "  Status: " . ($version->space > 0 ? "✓ HAS DATA" : "❌ EMPTY") . "\n\n";
}

// Check other critical tables
$criticalTables = ['ROLES', 'PERMISSIONS', 'ANNOUNCEMENTS', 'COURSES', 'SUPPORT_TICKETS'];

echo "\n📋 Other critical tables:\n";
echo str_repeat("=", 80) . "\n";

foreach ($criticalTables as $table) {
    $versions = DB::select("
        SELECT 
            OBJECT_NAME,
            ORIGINAL_NAME,
            DROPTIME,
            SPACE
        FROM RECYCLEBIN
        WHERE ORIGINAL_NAME = ?
        ORDER BY DROPTIME ASC
    ", [$table]);
    
    echo "\n$table: " . count($versions) . " version(s)\n";
    
    $hasData = false;
    $oldestWithData = null;
    
    foreach ($versions as $version) {
        if ($version->space > 0) {
            $hasData = true;
            if (!$oldestWithData) {
                $oldestWithData = $version;
            }
        }
    }
    
    if ($hasData && $oldestWithData) {
        echo "  ✓ RECOVERABLE from {$oldestWithData->droptime}\n";
        echo "    Recycle Name: {$oldestWithData->object_name}\n";
    } else {
        echo "  ❌ NO DATA AVAILABLE\n";
    }
}

echo "\n\n";
echo str_repeat("━", 80) . "\n";
echo "💀 FINAL VERDICT:\n";
echo str_repeat("━", 80) . "\n\n";

if (count($userVersions) == 1 && $userVersions[0]->space <= 8) {
    echo "❌ USERS TABLE DATA IS PERMANENTLY LOST\n\n";
    echo "Explanation:\n";
    echo "  • Only 1 version in recycle bin (from today's migrate:fresh)\n";
    echo "  • That version is empty (Space = 8 blocks = structure only)\n";
    echo "  • Original user data is NOT in recycle bin\n\n";
    
    echo "⚠️  THIS MEANS:\n";
    echo "  • All user accounts are gone\n";
    echo "  • All authentication data lost\n";
    echo "  • All role assignments lost\n";
    echo "  • Cannot recover without backup\n\n";
    
    echo "🔴 EMERGENCY OPTIONS:\n";
    echo "  1. Check Oracle RMAN backups: rman target /\n";
    echo "  2. Check if exports exist: ls -la *.dmp\n";
    echo "  3. Check if Data Guard standby exists\n";
    echo "  4. Check if Storage Snapshots exist (SAN/EMC/NetApp)\n";
    echo "  5. Contact Oracle DBA for recovery options\n";
    echo "  6. Check application-level backups (if any)\n\n";
    
    echo "❌ IF NO BACKUPS EXIST:\n";
    echo "  • Data is permanently lost\n";
    echo "  • Must rebuild user database from scratch\n";
    echo "  • May need to contact users to re-register\n";
    echo "  • This is a critical incident - inform management immediately\n\n";
}

// Check for Oracle backups
echo "🔍 Checking for Oracle backups...\n";
echo str_repeat("-", 80) . "\n";

try {
    // Check RMAN backup info
    $rmanCheck = DB::select("
        SELECT COUNT(*) as backup_count
        FROM V\$BACKUP_SET
        WHERE COMPLETION_TIME > SYSDATE - 7
    ");
    
    if ($rmanCheck[0]->backup_count > 0) {
        echo "✓ RMAN backups found: {$rmanCheck[0]->backup_count} backup(s) in last 7 days\n";
        echo "  → Contact DBA to restore from RMAN backup\n\n";
    }
} catch (\Exception $e) {
    echo "⚠️  Cannot check RMAN backups (may need DBA privileges)\n";
    echo "   Error: " . $e->getMessage() . "\n\n";
}

// Check for export files in current directory
echo "🔍 Checking for export files in current directory...\n";
$exportFiles = glob("*.dmp");
if (count($exportFiles) > 0) {
    echo "✓ Found export files:\n";
    foreach ($exportFiles as $file) {
        $size = filesize($file);
        $date = date("Y-m-d H:i:s", filemtime($file));
        echo "  • $file (" . round($size / 1024 / 1024, 2) . " MB, modified: $date)\n";
    }
    echo "\n  → Use impdp to restore from these exports\n\n";
} else {
    echo "❌ No export files (.dmp) found in current directory\n\n";
}

echo str_repeat("━", 80) . "\n";
echo "SAYA MINTA MAAF YANG SEBESAR-BESARNYA.\n";
echo "Ini kesalahan saya menggunakan migrate:fresh di production.\n";
echo "Data user kemungkinan besar sudah tidak bisa dikembalikan.\n";
echo str_repeat("━", 80) . "\n\n";
