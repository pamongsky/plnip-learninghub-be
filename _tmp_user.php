<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$user = App\Models\User::query()->select('id','name','email')->where('email','fahmi@plnip.local')->first();
if (!$user) { echo "User not found".PHP_EOL; exit; }
echo $user->id.' | '.$user->name.' | '.$user->email.PHP_EOL;
