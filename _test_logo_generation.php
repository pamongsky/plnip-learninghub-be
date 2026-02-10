<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\CertificateTemplate;
use App\Services\CertificateGenerator;
use Illuminate\Support\Facades\Storage;

$template = CertificateTemplate::first();

if ($template) {
    echo "Testing Logo Path Generation\n";
    echo "============================\n\n";

    // Use reflection to call protected method
    $generator = new CertificateGenerator();
    $reflection = new ReflectionClass($generator);
    $method = $reflection->getMethod('getLogoPath');
    $method->setAccessible(true);

    $logoPath = $method->invoke($generator, $template);

    echo "Template Logo Field: " . ($template->logo_path ?? 'NULL') . "\n";
    echo "Generated Logo Path: " . ($logoPath ?? 'NULL') . "\n";

    if ($logoPath) {
        echo "\nLogo Path Type: " . (strpos($logoPath, 'data:') === 0 ? 'BASE64 DATA URI' : 'FILE PATH') . "\n";
        if (strpos($logoPath, 'data:') === 0) {
            echo "Data URI Length: " . strlen($logoPath) . " characters\n";
            echo "First 100 chars: " . substr($logoPath, 0, 100) . "...\n";
        }
    }
} else {
    echo "No template found\n";
}
