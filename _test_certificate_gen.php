<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Course;
use App\Services\CertificateGenerator;

// Get first user and first course for testing
$user = User::first();
$course = Course::first();

if (!$user || !$course) {
    echo "No user or course found for testing\n";
    exit;
}

echo "Testing Certificate Generation\n";
echo "==============================\n";
echo "User: " . $user->name . " (ID: " . $user->id . ")\n";
echo "Course: " . $course->nama . " (ID: " . $course->id . ")\n\n";

$generator = new CertificateGenerator();

try {
    $certificate = $generator->generateCertificate($user, $course);

    if ($certificate) {
        echo "✓ Certificate generated successfully\n";
        echo "Certificate ID: " . $certificate->id . "\n";
        echo "Certificate Number: " . $certificate->certificate_number . "\n";
        echo "PDF Path: " . $certificate->pdf_path . "\n";
    } else {
        echo "✗ Certificate generation failed\n";
    }
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
