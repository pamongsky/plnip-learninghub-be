<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Http;

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$apiKey = env('GEMINI_API_KEY');
$url = "https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}";

echo "Querying Models from: $url\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Fix for Localhost
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if (isset($data['models'])) {
    foreach ($data['models'] as $model) {
        echo " - " . $model['name'] . "\n";
    }
} else {
    echo "Error: " . $response . "\n";
}
