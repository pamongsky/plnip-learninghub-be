<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;

echo "=== CERTIFICATES IN DATABASE ===\n\n";

$certificates = Certificate::with('user:id,name,email')->get();

if ($certificates->count() === 0) {
    echo "No certificates found in database\n";
} else {
    foreach ($certificates as $cert) {
        echo "[{$cert->id}] {$cert->certificate_number}\n";
        echo "  User: {$cert->user->name} ({$cert->user->email})\n";
        echo "  Course ID: {$cert->course_id}\n";
        echo "  URL: {$cert->certificate_url}\n";

        // Check if file exists
        $filePath = str_replace('/storage/', '', parse_url($cert->certificate_url, PHP_URL_PATH));
        $exists = Storage::disk('public')->exists($filePath);
        echo "  File exists: " . ($exists ? 'YES ✓' : 'NO ✗') . "\n";
        if ($exists) {
            $size = Storage::disk('public')->size($filePath);
            echo "  File size: " . round($size / 1024, 2) . " KB\n";
        }
        echo "\n";
    }

    echo "Total certificates: {$certificates->count()}\n";
}
