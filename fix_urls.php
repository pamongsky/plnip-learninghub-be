<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== FIXING URLs ===\n\n";

// Update landing_page_settings
$updated = DB::table('landing_page_settings')
    ->where('value', 'LIKE', 'http://127.0.0.1:8000%')
    ->update([
        'value' => DB::raw("REPLACE(value, 'http://127.0.0.1:8000', 'http://192.168.4.177:8000')")
    ]);

echo "✓ Updated landing_page_settings: $updated rows\n";

// Update cms_hero_images
$updated = DB::table('cms_hero_images')
    ->where('image_path', 'LIKE', 'http://127.0.0.1:8000%')
    ->update([
        'image_path' => DB::raw("REPLACE(image_path, 'http://127.0.0.1:8000', 'http://192.168.4.177:8000')")
    ]);

echo "✓ Updated cms_hero_images: $updated rows\n";

// Update cms_leaders
$updated = DB::table('cms_leaders')
    ->where('photo', 'LIKE', 'http://127.0.0.1:8000%')
    ->update([
        'photo' => DB::raw("REPLACE(photo, 'http://127.0.0.1:8000', 'http://192.168.4.177:8000')")
    ]);

echo "✓ Updated cms_leaders: $updated rows\n";

// Update cms_partners
$updated = DB::table('cms_partners')
    ->where('logo', 'LIKE', 'http://127.0.0.1:8000%')
    ->update([
        'logo' => DB::raw("REPLACE(logo, 'http://127.0.0.1:8000', 'http://192.168.4.177:8000')")
    ]);

echo "✓ Updated cms_partners: $updated rows\n";

echo "\nDone! Now refresh browser.\n";
