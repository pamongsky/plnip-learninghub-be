<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Certificate;
use App\Models\User;

echo "=== CERTIFICATE OWNERSHIP CHECK ===\n\n";

$cert = Certificate::with('user')->where('certificate_number', 'PLN-CERT-2026-02-0005')->first();

if ($cert) {
    echo "Certificate: {$cert->certificate_number}\n";
    echo "Owner: {$cert->user->name} ({$cert->user->email})\n";
    echo "User ID: {$cert->user_id}\n";
    echo "Is Valid: " . ($cert->is_valid ? 'Yes' : 'No') . "\n\n";

    // Check what user might be logged in (Faqih based on screenshot)
    $faqih = User::where('email', 'faqih@plnip.local')->first();
    if ($faqih) {
        echo "Logged in as: {$faqih->name} ({$faqih->email})\n";
        echo "User ID: {$faqih->id}\n\n";

        if ($cert->user_id === $faqih->id) {
            echo "✓ MATCH - User owns this certificate\n";
        } else {
            echo "✗ MISMATCH - User does NOT own this certificate\n";
            echo "Certificate belongs to user ID {$cert->user_id} but logged in user is ID {$faqih->id}\n";
        }
    }
}
