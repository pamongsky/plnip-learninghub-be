<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\CertificateTemplate;
use App\Services\CertificateGenerator;
use Illuminate\Support\Facades\Storage;

$template = CertificateTemplate::first();

if ($template) {
    echo "Testing Logo Generation\n";
    echo "======================\n\n";

    $generator = new CertificateGenerator();
    $reflection = new ReflectionClass($generator);
    $method = $reflection->getMethod('getLogoPath');
    $method->setAccessible(true);

    $logoPath = $method->invoke($generator, $template);

    echo "Template ID: " . $template->id . "\n";
    echo "Template Logo Field: " . ($template->logo_path ?? 'NULL') . "\n\n";

    if ($logoPath) {
        $isBase64 = strpos($logoPath, 'data:') === 0;
        echo "✓ Logo Path Generated\n";
        echo "Type: " . ($isBase64 ? 'BASE64 DATA URI' : 'FILE PATH') . "\n";

        if ($isBase64) {
            $length = strlen($logoPath);
            echo "Length: " . number_format($length) . " characters\n";
            echo "First 200 chars:\n" . substr($logoPath, 0, 200) . "...\n\n";

            // Test if it's valid base64 image
            if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $logoPath, $matches)) {
                echo "✓ Valid data URI format\n";
                echo "Image Type: " . $matches[1] . "\n";
                $base64Data = $matches[2];
                $decoded = base64_decode($base64Data, true);
                if ($decoded !== false) {
                    echo "✓ Valid base64 encoding\n";
                    echo "Decoded size: " . number_format(strlen($decoded)) . " bytes\n";
                } else {
                    echo "✗ Invalid base64 encoding\n";
                }
            } else {
                echo "✗ Invalid data URI format\n";
            }
        } else {
            echo "Path: " . $logoPath . "\n";
            echo "Exists: " . (file_exists($logoPath) ? 'YES' : 'NO') . "\n";
        }
    } else {
        echo "✗ Logo Path is NULL\n";
    }
} else {
    echo "No template found\n";
}
