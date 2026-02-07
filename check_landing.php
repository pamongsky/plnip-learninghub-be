<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== LANDING PAGE DATA ===\n\n";

try {
    // 1. Settings (logo, teks)
    $settings = DB::table('landing_page_settings')->get();
    echo "1. Landing Page Settings: {$settings->count()}\n";
    foreach($settings as $s) {
        echo "   - {$s->key}: {$s->value}\n";
    }
} catch(\Exception $e) {
    echo "❌ landing_page_settings: Table tidak ada\n";
}

try {
    // 2. Hero Images
    $heroes = DB::table('cms_hero_images')->get();
    echo "\n2. Hero Images: {$heroes->count()}\n";
} catch(\Exception $e) {
    echo "\n❌ cms_hero_images: Table tidak ada\n";
}

try {
    // 3. Leaders
    $leaders = DB::table('cms_leaders')->get();
    echo "\n3. Leaders: {$leaders->count()}\n";
} catch(\Exception $e) {
    echo "\n❌ cms_leaders: Table tidak ada\n";
}

try {
    // 4. Partners
    $partners = DB::table('cms_partners')->get();
    echo "\n4. Partners: {$partners->count()}\n";
} catch(\Exception $e) {
    echo "\n❌ cms_partners: Table tidak ada\n";
}

try {
    // 5. Login Backgrounds
    $bg = DB::table('cms_login_backgrounds')->get();
    echo "\n5. Login Backgrounds: {$bg->count()}\n";
} catch(\Exception $e) {
    echo "\n❌ cms_login_backgrounds: Table tidak ada\n";
}
