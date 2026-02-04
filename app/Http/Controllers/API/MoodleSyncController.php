<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\MoodleSyncService;
use App\Models\MoodleSyncLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MoodleSyncController extends Controller
{
    protected $syncService;

    public function __construct(MoodleSyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * Get Moodle connection status
     * GET /api/moodle/sync/status
     */
    public function status(Request $request)
    {
        try {
            $connection = $this->syncService->getConnectionStatus();
            $stats = $this->syncService->getSyncStats();

            return response()->json([
                'connection' => $connection,
                'stats' => $stats,
                'last_sync' => $this->getLastSyncInfo(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get Moodle sync status: ' . $e->getMessage());
            return response()->json([
                'message' => 'Gagal mengambil status Moodle',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Full sync - Sync all data from Moodle
     * POST /api/moodle/sync/full
     */
    public function fullSync(Request $request)
    {
        // Permission check
        if (!$request->user() || !$request->user()->hasRole('super-admin')) {
            return response()->json([
                'message' => 'Hanya Super Admin yang dapat melakukan full sync'
            ], 403);
        }

        try {
            Log::info('Full Moodle Sync started by user: ' . $request->user()->email);

            $results = $this->syncService->fullSync();

            // Store sync log to database
            $this->storeSyncLog('full', $results, $request->user()->id);

            return response()->json([
                'message' => 'Full sync berhasil!',
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            Log::error('Full sync failed: ' . $e->getMessage());

            // Store failed sync log
            $this->storeSyncLog('full', [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ], $request->user()->id);

            return response()->json([
                'message' => 'Full sync gagal',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync users only
     * POST /api/moodle/sync/users
     */
    public function syncUsers(Request $request)
    {
        if (!$request->user() || !$request->user()->hasRole(['super-admin', 'admin'])) {
            return response()->json([
                'message' => 'Tidak memiliki akses untuk sync users'
            ], 403);
        }

        try {
            Log::info('User sync started by: ' . $request->user()->email);

            $results = $this->syncService->syncUsers();

            $this->storeSyncLog('users', $results, $request->user()->id);

            return response()->json([
                'message' => 'Sync users berhasil!',
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            Log::error('User sync failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Sync users gagal',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync courses only
     * POST /api/moodle/sync/courses
     */
    public function syncCourses(Request $request)
    {
        if (!$request->user() || !$request->user()->hasRole(['super-admin', 'admin'])) {
            return response()->json([
                'message' => 'Tidak memiliki akses untuk sync courses'
            ], 403);
        }

        try {
            Log::info('Course sync started by: ' . $request->user()->email);

            $results = $this->syncService->syncCourses();

            $this->storeSyncLog('courses', $results, $request->user()->id);

            return response()->json([
                'message' => 'Sync courses berhasil!',
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            Log::error('Course sync failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Sync courses gagal',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync enrollments only
     * POST /api/moodle/sync/enrollments
     */
    public function syncEnrollments(Request $request)
    {
        if (!$request->user() || !$request->user()->hasRole(['super-admin', 'admin'])) {
            return response()->json([
                'message' => 'Tidak memiliki akses untuk sync enrollments'
            ], 403);
        }

        try {
            Log::info('Enrollment sync started by: ' . $request->user()->email);

            $results = $this->syncService->syncEnrollments();

            $this->storeSyncLog('enrollments', $results, $request->user()->id);

            return response()->json([
                'message' => 'Sync enrollments berhasil!',
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            Log::error('Enrollment sync failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Sync enrollments gagal',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync categories only
     * POST /api/moodle/sync/categories
     */
    public function syncCategories(Request $request)
    {
        if (!$request->user() || !$request->user()->hasRole(['super-admin', 'admin'])) {
            return response()->json([
                'message' => 'Tidak memiliki akses untuk sync categories'
            ], 403);
        }

        try {
            Log::info('Category sync started by: ' . $request->user()->email);

            $results = $this->syncService->syncCategories();

            $this->storeSyncLog('categories', $results, $request->user()->id);

            return response()->json([
                'message' => 'Sync categories berhasil!',
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            Log::error('Category sync failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Sync categories gagal',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get sync history
     * GET /api/moodle/sync/history
     */
    public function history(Request $request)
    {
        try {
            $history = MoodleSyncLog::with('triggeredBy:id,name,email')
                ->orderBy('started_at', 'desc')
                ->limit(20)
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'type' => ucfirst($log->type),
                        'started_at' => $log->started_at->toDateTimeString(),
                        'completed_at' => $log->completed_at?->toDateTimeString(),
                        'status' => $log->status,
                        'users_added' => $log->users_added,
                        'users_updated' => $log->users_updated,
                        'courses_added' => $log->courses_added,
                        'courses_updated' => $log->courses_updated,
                        'enrollments_added' => $log->enrollments_added,
                        'enrollments_updated' => $log->enrollments_updated,
                        'duration' => $log->duration_seconds,
                        'triggered_by' => $log->triggeredBy?->name,
                    ];
                });

            return response()->json([
                'history' => $history,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get sync history: ' . $e->getMessage());
            return response()->json([
                'message' => 'Gagal mengambil history',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store sync log to database
     */
    private function storeSyncLog(string $type, array $results, int $userId): void
    {
        try {
            $data = [
                'type' => $type,
                'triggered_by' => $userId,
                'started_at' => $results['started_at'] ?? now(),
                'completed_at' => $results['completed_at'] ?? now(),
                'duration_seconds' => $results['duration'] ?? $results['duration_seconds'] ?? 0,
                'status' => isset($results['error']) ? 'error' : 'success',
            ];

            // Extract results based on type
            if ($type === 'full') {
                $data['users_added'] = $results['users']['added'] ?? 0;
                $data['users_updated'] = $results['users']['updated'] ?? 0;
                $data['users_errors'] = $results['users']['errors'] ?? 0;
                $data['courses_added'] = $results['courses']['added'] ?? 0;
                $data['courses_updated'] = $results['courses']['updated'] ?? 0;
                $data['courses_errors'] = $results['courses']['errors'] ?? 0;
                $data['enrollments_added'] = $results['enrollments']['added'] ?? 0;
                $data['enrollments_updated'] = $results['enrollments']['updated'] ?? 0;
                $data['enrollments_errors'] = $results['enrollments']['errors'] ?? 0;
                $data['logs'] = $results['logs'] ?? null;
            } elseif ($type === 'users') {
                $data['users_added'] = $results['added'] ?? 0;
                $data['users_updated'] = $results['updated'] ?? 0;
                $data['users_errors'] = $results['errors'] ?? 0;
            } elseif ($type === 'courses') {
                $data['courses_added'] = $results['added'] ?? 0;
                $data['courses_updated'] = $results['updated'] ?? 0;
                $data['courses_errors'] = $results['errors'] ?? 0;
            } elseif ($type === 'enrollments') {
                $data['enrollments_added'] = $results['added'] ?? 0;
                $data['enrollments_updated'] = $results['updated'] ?? 0;
                $data['enrollments_errors'] = $results['errors'] ?? 0;
            }

            if (isset($results['error'])) {
                $data['error_message'] = $results['error'];
            }

            MoodleSyncLog::create($data);

            Log::info("Sync log stored: Type=$type, User=$userId");

        } catch (\Exception $e) {
            Log::error('Failed to store sync log: ' . $e->getMessage());
        }
    }

    /**
     * Get last sync info
     */
    private function getLastSyncInfo(): ?array
    {
        $lastSync = MoodleSyncLog::orderBy('started_at', 'desc')->first();

        if (!$lastSync) {
            return null;
        }

        return [
            'type' => $lastSync->type,
            'started_at' => $lastSync->started_at->toDateTimeString(),
            'completed_at' => $lastSync->completed_at?->toDateTimeString(),
            'duration' => $lastSync->duration_seconds,
            'status' => $lastSync->status,
        ];
    }
}
