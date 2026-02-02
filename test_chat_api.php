<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Simulate authenticated user
$user = \App\Models\User::first();
if (!$user) {
    die("❌ No user found in database\n");
}

echo "Testing Chat API with User: {$user->name} (ID: {$user->id})\n";
echo str_repeat("=", 60) . "\n\n";

// Create test chat message
$controller = new \App\Http\Controllers\API\ChatController();

// Mock request
$request = new \Illuminate\Http\Request();
$request->merge([
    'message' => 'Halo! Apa itu PLN?'
]);

// Set authenticated user
\Illuminate\Support\Facades\Auth::setUser($user);

echo "Sending message: 'Halo! Apa itu PLN?'\n";
echo "Waiting for AI response...\n\n";

try {
    $response = $controller->chat($request);
    $data = $response->getData(true);

    echo "✅ SUCCESS!\n";
    echo str_repeat("-", 60) . "\n";
    echo "Session ID: " . ($data['session_id'] ?? 'null') . "\n";
    echo "AI Reply: " . ($data['reply'] ?? 'No reply') . "\n";
    echo str_repeat("-", 60) . "\n";

} catch (\Exception $e) {
    echo "❌ ERROR!\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\nCheck storage/logs/laravel.log for detailed logs\n";
