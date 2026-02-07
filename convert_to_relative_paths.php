<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CONVERTING FULL URLs TO RELATIVE PATHS ===\n\n";

// 1. landing_page_settings
$settings = DB::table('landing_page_settings')->get();
foreach($settings as $setting) {
    if (preg_match('#^https?://[^/]+(/storage/.+)$#', $setting->value, $matches)) {
        $newValue = $matches[1];
        DB::table('landing_page_settings')
            ->where('id', $setting->id)
            ->update(['value' => $newValue]);
        echo "✓ Updated setting '{$setting->key}': {$newValue}\n";
    }
}

// 2. cms_hero_images
$heroes = DB::table('cms_hero_images')->get();
foreach($heroes as $hero) {
    if (preg_match('#^https?://[^/]+(/storage/.+)$#', $hero->image_path, $matches)) {
        $newValue = $matches[1];
        DB::table('cms_hero_images')
            ->where('id', $hero->id)
            ->update(['image_path' => $newValue]);
        echo "✓ Updated hero image: {$newValue}\n";
    }
}

// 3. cms_leaders
$leaders = DB::table('cms_leaders')->get();
foreach($leaders as $leader) {
    if (isset($leader->image_path) && preg_match('#^https?://[^/]+(/storage/.+)$#', $leader->image_path, $matches)) {
        $newValue = $matches[1];
        DB::table('cms_leaders')
            ->where('id', $leader->id)
            ->update(['image_path' => $newValue]);
        echo "✓ Updated leader '{$leader->name}': {$newValue}\n";
    }
}

// 4. cms_partners
$partners = DB::table('cms_partners')->get();
foreach($partners as $partner) {
    if (isset($partner->logo_path) && preg_match('#^https?://[^/]+(/storage/.+)$#', $partner->logo_path, $matches)) {
        $newValue = $matches[1];
        DB::table('cms_partners')
            ->where('id', $partner->id)
            ->update(['logo_path' => $newValue]);
        echo "✓ Updated partner '{$partner->name}': {$newValue}\n";
    }
}

// 5. cms_login_backgrounds
$backgrounds = DB::table('cms_login_backgrounds')->get();
foreach($backgrounds as $bg) {
    if (preg_match('#^https?://[^/]+(/storage/.+)$#', $bg->image_path, $matches)) {
        $newValue = $matches[1];
        DB::table('cms_login_backgrounds')
            ->where('id', $bg->id)
            ->update(['image_path' => $newValue]);
        echo "✓ Updated login background: {$newValue}\n";
    }
}

echo "\n✅ All URLs converted to relative paths!\n";
echo "Now frontend will construct full URL using NEXT_PUBLIC_BACKEND_URL\n";
