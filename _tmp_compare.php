<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$user = App\Models\User::find(4);
$ticket = App\Models\SupportTicket::find(4);
var_dump($user->id, $ticket->user_id, $ticket->user_id === $user->id, $ticket->user_id == $user->id);
