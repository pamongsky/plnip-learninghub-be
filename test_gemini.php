<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Http;

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$apiKey = env('GEMINI_API_KEY');
echo "Testing API Key: " . substr($apiKey, 0, 5) . "...\n\n";

$models = [
    'gemini-2.5-flash', // Seen in list earlier
    'gemini-2.5-pro',   // Seen in list earlier
    'gemini-2.0-flash-exp', // User suggestion
    'gemini-2.0-flash',
    'gemini-1.5-flash-latest',
    'gemini-1.5-flash-001',
    'gemini-1.5-flash-002',
];

foreach ($models as $model) {
    echo "Testing Model: $model ... ";
    
    $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key={$apiKey}";
    $payload = [
        "contents" => [
            ["parts" => [["text" => "Hello"]]]
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        echo "✅ SUCCESS (200)\n";
        echo "Using this model is RECOMMENDED.\n";
        exit(0); // Stop at first success
    } else {
        echo "❌ FAILED ($httpCode)\n";
    }
}

echo "\nNo working models found in common list.\n";
