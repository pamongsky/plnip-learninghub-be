<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get all migrations from database
$dbMigrations = DB::table('migrations')->orderBy('batch', 'desc')->get();

echo "Checking for missing migration files...\n\n";

$missing = [];
foreach ($dbMigrations as $migration) {
    $file = __DIR__ . '/database/migrations/' . $migration->migration . '.php';
    if (!file_exists($file)) {
        $missing[] = $migration->migration;
        echo "❌ MISSING: {$migration->migration} (batch {$migration->batch})\n";
    }
}

if (empty($missing)) {
    echo "✓ All migration files exist!\n";
} else {
    echo "\n" . count($missing) . " missing migration files found.\n";
    echo "\nDeleting records from migrations table...\n";

    foreach ($missing as $mig) {
        DB::delete("DELETE FROM migrations WHERE migration = ?", [$mig]);
        echo "  ✓ Deleted: $mig\n";
    }

    echo "\n✓ Migration table cleaned!\n";
}

// Show current state
echo "\n" . str_repeat("-", 50) . "\n";
echo "Current migrations status:\n";
$lastBatch = DB::table('migrations')->max('batch');
echo "Last batch: $lastBatch\n";
$count = DB::table('migrations')->count();
echo "Total migrations: $count\n";
