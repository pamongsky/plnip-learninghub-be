<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AiFaq;
use App\Models\AiFaqAnalytic;
use App\Models\AiFaqSuggestion;
use Illuminate\Support\Facades\DB;

echo "🔍 CHECKING AI FAQ SYSTEM STATUS\n\n";

// Check tables exist
echo "Step 1: Checking tables...\n";
echo str_repeat("-", 80) . "\n";

$tables = ['ai_faqs', 'ai_faq_analytics', 'ai_faq_suggestions'];
foreach ($tables as $table) {
    try {
        $exists = DB::select("SELECT table_name FROM user_tables WHERE table_name = UPPER(?)", [$table]);
        if (count($exists) > 0) {
            echo "✓ $table exists\n";
        } else {
            echo "❌ $table NOT found\n";
        }
    } catch (\Exception $e) {
        echo "❌ Error checking $table: " . $e->getMessage() . "\n";
    }
}
echo "\n";

// Check data
echo "Step 2: Checking data in tables...\n";
echo str_repeat("-", 80) . "\n";

try {
    $faqCount = AiFaq::count();
    echo "FAQs: $faqCount records\n";
    
    if ($faqCount > 0) {
        $categories = AiFaq::select('category', DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->get();
        
        echo "\nFAQ Categories:\n";
        foreach ($categories as $cat) {
            echo "  • {$cat->category}: {$cat->count} FAQs\n";
        }
        
        echo "\nSample FAQs:\n";
        $samples = AiFaq::where('is_active', 1)->take(5)->get(['id', 'question', 'category']);
        foreach ($samples as $faq) {
            echo "  {$faq->id}. [{$faq->category}] " . substr($faq->question, 0, 60) . "...\n";
        }
    } else {
        echo "⚠️  No FAQs found in database\n";
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Check analytics
echo "Step 3: Checking analytics...\n";
echo str_repeat("-", 80) . "\n";
try {
    $analyticsCount = AiFaqAnalytic::count();
    echo "Analytics records: $analyticsCount\n";
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Check suggestions
echo "Step 4: Checking suggestions...\n";
echo str_repeat("-", 80) . "\n";
try {
    $suggestionsCount = AiFaqSuggestion::count();
    echo "Suggestions: $suggestionsCount\n";
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

echo str_repeat("=", 80) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 80) . "\n";

if ($faqCount > 0) {
    echo "✅ AI FAQ System is INSTALLED and READY\n\n";
    echo "Next Steps:\n";
    echo "  1. Backend API untuk AI Chat sudah ready\n";
    echo "  2. Perlu buat Frontend UI untuk chat widget\n";
    echo "  3. Perlu buat Admin UI untuk manage FAQs\n";
} else {
    echo "⚠️  AI FAQ System TABLES exist but NO DATA\n\n";
    echo "Tables were created but default FAQs were not inserted.\n";
    echo "This might be because migration ran after the incident.\n\n";
    echo "Fix: Run migration again or insert default FAQs manually.\n";
}

echo "\n";
