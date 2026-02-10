<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

DB::delete("DELETE FROM migrations WHERE migration = ?", ['2026_02_07_232927_fix_announcements_target_role_constraint']);

echo "✓ Deleted problematic migration entry\n";

// Show remaining migrations in last batch
$lastBatch = DB::table('migrations')->max('batch');
$migrations = DB::table('migrations')->where('batch', $lastBatch)->get(['migration', 'batch']);

echo "\nMigrations in batch $lastBatch:\n";
foreach ($migrations as $mig) {
    echo "  - {$mig->migration}\n";
}
