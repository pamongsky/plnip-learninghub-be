<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ActivityLogController extends Controller
{
    /**
     * Get all activity logs with filters
     * Accessible by super-admin and admin
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = AuditLog::with('user:id,name,email');

            // Filter by user
            if ($request->has('user_id') && $request->user_id) {
                $query->where('user_id', $request->user_id);
            }

            // Filter by action
            if ($request->has('action') && $request->action) {
                $query->where('action', $request->action);
            }

            // Filter by entity type
            if ($request->has('entity_type') && $request->entity_type) {
                $query->where('entity_type', $request->entity_type);
            }

            // Period shorthand filter (1, 7, 30, 90 days)
            if ($request->has('period') && $request->period !== 'all') {
                $days = (int) $request->period;
                if ($days > 0) {
                    $query->where('created_at', '>=', now()->subDays($days));
                }
            }

            // Manual date range (overrides period if both provided)
            if ($request->has('start_date') && $request->start_date) {
                $query->where('created_at', '>=', $request->start_date);
            }
            if ($request->has('end_date') && $request->end_date) {
                $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
            }

            // Search by action, reason, or user name/email
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('action', 'like', "%{$search}%")
                      ->orWhere('reason', 'like', "%{$search}%")
                      ->orWhere('ip_address', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($q) use ($search) {
                          $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }

            // Pagination
            $perPage = min((int) $request->get('per_page', 50), 200);
            $logs = $query->orderByDesc('created_at')->paginate($perPage);

            return ApiResponse::paginated($logs);
        } catch (\Exception $e) {
            return ApiResponse::serverError('Gagal mengambil activity logs', $e->getMessage());
        }
    }

    /**
     * Summary stats: total per action type in a given period
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $query = AuditLog::query();

            $period = $request->get('period', '7');
            if ($period !== 'all') {
                $query->where('created_at', '>=', now()->subDays((int) $period));
            }

            $stats = $query->selectRaw('action, COUNT(*) as total')
                ->groupBy('action')
                ->orderByDesc('total')
                ->get()
                ->keyBy('action');

            $totalLogs = AuditLog::when($period !== 'all', fn($q) => $q->where('created_at', '>=', now()->subDays((int) $period)))->count();
            $uniqueUsers = AuditLog::when($period !== 'all', fn($q) => $q->where('created_at', '>=', now()->subDays((int) $period)))->distinct('user_id')->count('user_id');

            return ApiResponse::success([
                'total_logs'   => $totalLogs,
                'unique_users' => $uniqueUsers,
                'by_action'    => $stats,
            ]);
        } catch (\Exception $e) {
            return ApiResponse::serverError('Gagal mengambil stats', $e->getMessage());
        }
    }

    /**
     * Get activity logs for specific user
     */
    public function userLogs(User $user, Request $request): JsonResponse
    {
        try {
            $limit = min((int) $request->get('limit', 100), 500);

            $logs = AuditLog::where('user_id', $user->id)
                ->with('user:id,name,email')
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();

            // Group by date
            $grouped = $logs->groupBy(function ($log) {
                return $log->created_at->format('Y-m-d');
            });

            return ApiResponse::success([
                'user'    => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
                'logs'    => $logs,
                'grouped' => $grouped,
                'total'   => $logs->count(),
            ]);
        } catch (\Exception $e) {
            return ApiResponse::serverError('Gagal mengambil activity logs user', $e->getMessage());
        }
    }

    /**
     * Export activity logs to CSV
     */
    public function export(Request $request): JsonResponse
    {
        try {
            // This would typically generate a CSV file
            // For now, return a message
            return ApiResponse::success(null, 'Fitur export akan segera hadir');
        } catch (\Exception $e) {
            return ApiResponse::serverError('Gagal export logs', $e->getMessage());
        }
    }
}
