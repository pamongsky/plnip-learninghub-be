<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔍 DETAILED RECYCLE BIN ANALYSIS\n\n";

// Get all items in recycle bin with details
$recycleItems = DB::select("
    SELECT 
        OBJECT_NAME,
        ORIGINAL_NAME,
        TYPE,
        DROPTIME,
        DROPSCN,
        CAN_UNDROP,
        CAN_PURGE,
        RELATED,
        BASE_OBJECT,
        PURGE_OBJECT,
        SPACE
    FROM RECYCLEBIN
    WHERE ORIGINAL_NAME IN (
        'USERS', 'ROLES', 'PERMISSIONS', 'ANNOUNCEMENTS', 'COURSES',
        'SUPPORT_TICKETS', 'CHAT_SESSIONS', 'CHAT_MESSAGES'
    )
    ORDER BY ORIGINAL_NAME, DROPTIME DESC
");

echo "Found " . count($recycleItems) . " items in recycle bin\n";
echo str_repeat("=", 100) . "\n\n";

$grouped = [];
foreach ($recycleItems as $item) {
    $grouped[$item->original_name][] = $item;
}

foreach ($grouped as $tableName => $items) {
    echo "📋 Table: $tableName (" . count($items) . " versions)\n";
    echo str_repeat("-", 100) . "\n";
    
    foreach ($items as $idx => $item) {
        echo "  Version " . ($idx + 1) . ":\n";
        echo "    Recycle Name: {$item->object_name}\n";
        echo "    Drop Time: {$item->droptime}\n";
        echo "    Drop SCN: {$item->dropscn}\n";
        echo "    Can Undrop: {$item->can_undrop}\n";
        echo "    Space (blocks): {$item->space}\n";
        echo "\n";
    }
}

echo "\n";
echo "💡 ANALYSIS:\n";
echo "   If SPACE = 0, table was empty when dropped\n";
echo "   If multiple versions exist, we need to find the one with data\n";
echo "   Latest drop time = 11:01:xx (migrate:fresh)\n";
echo "   We need OLDER drop times (original data)\n\n";

// Check if there are older versions
$hasMultipleVersions = false;
foreach ($grouped as $tableName => $items) {
    if (count($items) > 1) {
        $hasMultipleVersions = true;
        echo "✓ $tableName has " . count($items) . " versions - we can try older ones!\n";
    }
}

if (!$hasMultipleVersions) {
    echo "⚠️  WARNING: Each table only has 1 version in recycle bin\n";
    echo "   This means the original data may be permanently lost\n";
    echo "   Check if backup exists: Oracle RMAN, exports, or snapshots\n";
}
