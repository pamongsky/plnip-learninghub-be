<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$rows = App\Models\SupportTicket::query()
    ->select('id','ticket_number','user_id','subject')
    ->orderBy('id')
    ->take(20)
    ->get();
foreach ($rows as $r) {
    echo $r->id.' | '.$r->ticket_number.' | '.$r->user_id.' | '.$r->subject.PHP_EOL;
}
