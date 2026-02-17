<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class HealthController extends Controller
{
    /**
     * Overall system health check
     */
    public function index(): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => [
                'database' => $this->checkDatabase(),
                'moodle' => $this->checkMoodle(),
                'cache' => $this->checkCache(),
                'storage' => $this->checkStorage(),
            ]
        ];

        // If any critical check fails, mark as unhealthy
        $criticalChecks = ['database', 'moodle'];
        foreach ($criticalChecks as $check) {
            if ($health['checks'][$check]['status'] !== 'up') {
                $health['status'] = 'unhealthy';
                break;
            }
        }

        $statusCode = $health['status'] === 'healthy' ? 200 : 503;

        return response()->json($health, $statusCode);
    }

    /**
     * Moodle-specific health check
     */
    public function moodle(): JsonResponse
    {
        $check = $this->checkMoodle();
        $statusCode = $check['status'] === 'up' ? 200 : 503;

        return response()->json([
            'service' => 'moodle',
            'timestamp' => now()->toIso8601String(),
            ...$check
        ], $statusCode);
    }

    /**
     * Check database connection
     */
    protected function checkDatabase(): array
    {
        try {
            $startTime = microtime(true);
            DB::connection()->getPdo();
            DB::connection()->select('SELECT 1 FROM DUAL');
            $latency = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => 'up',
                'latency_ms' => $latency,
                'connection' => config('database.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'down',
                'error' => $e->getMessage(),
                'connection' => config('database.default'),
            ];
        }
    }

    /**
     * Check Moodle database connection
     */
    protected function checkMoodle(): array
    {
        try {
            $startTime = microtime(true);

            // Try to connect and query Moodle DB
            $moodleConn = DB::connection('moodle');
            $moodleConn->getPdo();
            $result = $moodleConn->select('SELECT COUNT(*) as count FROM "user" WHERE id = 1');

            $latency = round((microtime(true) - $startTime) * 1000, 2);

            // Check if Moodle URL is configured
            $moodleUrl = config('services.moodle.url');
            $urlStatus = $moodleUrl ? 'configured' : 'not_configured';

            return [
                'status' => 'up',
                'latency_ms' => $latency,
                'database' => 'connected',
                'url' => $urlStatus,
                'moodle_url' => $moodleUrl,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'down',
                'error' => $e->getMessage(),
                'database' => 'disconnected',
                'url' => config('services.moodle.url') ? 'configured' : 'not_configured',
            ];
        }
    }

    /**
     * Check cache system
     */
    protected function checkCache(): array
    {
        try {
            $testKey = 'health_check_' . time();
            $testValue = 'test';

            Cache::put($testKey, $testValue, 10);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);

            if ($retrieved === $testValue) {
                return [
                    'status' => 'up',
                    'driver' => config('cache.default'),
                ];
            }

            return [
                'status' => 'degraded',
                'error' => 'Cache write/read mismatch',
                'driver' => config('cache.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'down',
                'error' => $e->getMessage(),
                'driver' => config('cache.default'),
            ];
        }
    }

    /**
     * Check storage writability
     */
    protected function checkStorage(): array
    {
        try {
            $testFile = storage_path('app/health_check_' . time() . '.txt');
            file_put_contents($testFile, 'test');

            if (file_exists($testFile)) {
                unlink($testFile);
                return [
                    'status' => 'up',
                    'writable' => true,
                    'path' => 'storage/app',
                ];
            }

            return [
                'status' => 'degraded',
                'writable' => false,
                'path' => 'storage/app',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'down',
                'error' => $e->getMessage(),
                'writable' => false,
                'path' => 'storage/app',
            ];
        }
    }
}
