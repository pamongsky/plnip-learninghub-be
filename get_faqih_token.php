<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

// Get token for faqih user
$user = User::where('email', 'faqih@plnip.local')->first();
if ($user) {
    // Delete old tokens
    $user->tokens()->delete();

    // Create fresh token
    $token = $user->createToken('test_download')->plainTextToken;
    echo "User: {$user->name}\n";
    echo "ID: {$user->id}\n";
    echo "Token: {$token}\n";
} else {
    echo "User not found\n";
}
