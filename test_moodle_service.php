<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Services\MoodleSyncService;

echo "=== Testing MoodleSyncService ===\n\n";

try {
    $service = app(MoodleSyncService::class);

    echo "1. getConnectionStatus():\n";
    $connection = $service->getConnectionStatus();
    print_r($connection);

    echo "\n2. getSyncStats():\n";
    $stats = $service->getSyncStats();
    print_r($stats);

} catch(Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
