<?php
/**
 * Test Instructor Dashboard API Response
 *
 * Run: php test_instructor_dashboard_api.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\API\DashboardController;
use App\Models\User;

echo "=== TEST INSTRUCTOR DASHBOARD API ===\n\n";

try {
    // Get instructor user
    $instructor = User::where('email', 'instructor@plnip.local')->first();

    if (!$instructor) {
        echo "❌ Instructor tidak ditemukan!\n";
        exit;
    }

    echo "✅ Instructor Found: {$instructor->name} ({$instructor->email})\n\n";

    // Create mock request
    $request = Request::create('/api/dashboard/instructor', 'GET');
    $request->setUserResolver(function() use ($instructor) {
        return $instructor;
    });

    // Call controller
    $controller = new DashboardController();

    echo "Calling instructorDashboard()...\n";
    $response = $controller->instructorDashboard($request);

    echo "Response received!\n\n";

    // Get response data
    $data = $response->getData(true);

    echo "--- API Response ---\n";
    echo "Status Code: " . $response->status() . "\n";
    echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n\n";

    if (isset($data['data'])) {
        $dashboardData = $data['data'];

        echo "--- Stats ---\n";
        echo "Active Classes: " . ($dashboardData['stats']['active_classes'] ?? 0) . "\n";
        echo "Total Participants: " . ($dashboardData['stats']['total_participants'] ?? 0) . "\n";
        echo "Completed Classes: " . ($dashboardData['stats']['completed_classes'] ?? 0) . "\n";
        echo "Average Attendance: " . ($dashboardData['stats']['average_attendance'] ?? 0) . "%\n\n";

        echo "--- Classes ---\n";
        $classes = $dashboardData['classes'] ?? [];

        // Debug: Show the actual structure
        echo "Classes Type: " . gettype($classes) . "\n";
        echo "Classes Count: " . (is_countable($classes) ? count($classes) : 'N/A') . "\n";

        if (empty($classes)) {
            echo "❌ Tidak ada kelas yang dikembalikan!\n";
            echo "\nDEBUGGING DIPERLUKAN:\n";
            echo "1. Check apakah query di DashboardController berhasil\n";
            echo "2. Check apakah $mapCourses->values() tidak kosong\n";
            echo "3. Run debug_instructor.php untuk verify role assignments\n";
        } else {
            // Convert to array if needed
            if (is_object($classes)) {
                $classes = json_decode(json_encode($classes), true);
            }

            echo "✅ Found " . count($classes) . " class(es):\n\n";
            foreach ($classes as $class) {
                if (!is_array($class)) {
                    echo "Warning: Class is not an array: " . gettype($class) . "\n";
                    continue;
                }
                echo "Class ID: " . ($class['id'] ?? 'N/A') . "\n";
                echo "  Title: " . ($class['title'] ?? 'N/A') . "\n";
                echo "  Short Name: " . ($class['short_name'] ?? 'N/A') . "\n";
                echo "  Status: " . ($class['status'] ?? 'N/A') . "\n";
                echo "  Participants: " . ($class['participants'] ?? 0) . "\n";
                echo "  Progress: " . ($class['progress'] ?? 0) . "%\n";
                echo "  Moodle URL: " . ($class['moodle_url'] ?? 'N/A') . "\n\n";
            }
        }

        echo "--- Announcements ---\n";
        echo "Total: " . count($dashboardData['announcements'] ?? []) . "\n";
    } else {
        echo "❌ Response tidak memiliki 'data' field!\n";
        echo "\nFull Response:\n";
        print_r($data);
    }

    echo "\n=== END TEST ===\n";

} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "\nStack Trace:\n";
    echo $e->getTraceAsString() . "\n";
}
