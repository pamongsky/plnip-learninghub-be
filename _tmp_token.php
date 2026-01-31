<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$user = App\Models\User::find(4);
$token = $user->createToken('debug-token')->plainTextToken;
echo $token.PHP_EOL;
