<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use App\Models\AuditLog;
use App\Services\UserService;
use App\Services\ERPSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class UserController extends \App\Http\Controllers\Controller
{
    /**
     * Get list of users (untuk selection dropdowns)
     */
    public function index(Request $request)
    {
        $query = User::select('id', 'name', 'email', 'employee_id');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('name')->limit(50)->get();

        return response()->json($users);
    }

    /**
     * Get all users dengan filter (untuk super admin panel)
     */
    public function getAllUsers(Request $request): JsonResponse
    {
        $query = User::query();

        // Filter by role
        if ($request->has('role')) {
            $role = $request->get('role');
            if ($role !== 'all') {
                $query->whereHas('roles', function ($q) use ($role) {
                    $q->where('name', $role);
                });
            }
        }

        // Filter by status
        if ($request->has('status')) {
            $status = $request->get('status');
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Filter by source
        if ($request->has('source')) {
            $source = $request->get('source');
            if ($source !== 'all') {
                $query->where('source', $source);
            }
        }

        // Filter by department
        if ($request->has('department')) {
            $department = $request->get('department');
            if ($department !== 'all') {
                $query->where('department', $department);
            }
        }

        // Search
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        // Load roles untuk efficient queries
        $query->with('roles');
        
        $users = $query->paginate($request->get('per_page', 15));

        // Add effective role to each user
        $users->getCollection()->transform(function ($user) {
            $user->effective_role = UserService::getEffectiveRole($user);
            return $user;
        });

        return response()->json($users);
    }

    /**
     * Get single user
     */
    public function show(User $user): JsonResponse
    {
        try {
            // Load roles relationship
            $user->load('roles');
            
            // Calculate effective role
            $user->effective_role = UserService::getEffectiveRole($user);
            
            return response()->json($user, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create user manual (super admin only)
     */
    public function store(Request $request): JsonResponse
    {
        // Check permission
        if (!$request->user() || !$request->user()->hasRole('super-admin')) {
            return response()->json([
                'message' => 'Hanya super admin yang bisa membuat user manual'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'employee_id' => 'nullable|unique:users',
            'phone' => 'nullable|string',
            'department' => 'nullable|string',
            'position' => 'nullable|string',
            'role' => 'required|string|in:super-admin,admin,instructor,employee,user',
            'password' => 'nullable|string|min:8',
        ]);

        try {
            DB::beginTransaction();

            $user = UserService::createUserManual($validated, $request->user());

            DB::commit();

            return response()->json([
                'message' => 'User berhasil dibuat',
                'user' => $user,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal membuat user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user
     */
    public function update(Request $request, User $user): JsonResponse
    {
        // Check permission: hanya super admin atau admin yang manage unit yang sama
        if (!$request->user() || !($request->user()->hasRole('super-admin') || 
            ($request->user()->hasRole('admin') && $request->user()->department === $user->department))) {
            return response()->json([
                'message' => 'Anda tidak memiliki izin untuk mengubah user ini'
            ], 403);
        }

        // PROTECT: Prevent changing super-admin role
        if ($user->hasRole('super-admin') && isset($request->role) && $request->role !== 'super-admin') {
            return response()->json([
                'message' => '⛔ Tidak bisa mengubah role super admin! Hubungi administrator sistem.',
                'error' => 'Protected role cannot be changed'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string',
            'department' => 'nullable|string',
            'position' => 'nullable|string',
            'role' => 'sometimes|string|in:super-admin,admin,instructor,employee,user',
            'is_active' => 'sometimes|boolean',
            'reason' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            UserService::updateUser($user, $validated, $request->user());

            DB::commit();

            // Reload user dengan roles
            $user->load('roles');
            $user->effective_role = UserService::getEffectiveRole($user);

            return response()->json([
                'message' => 'User berhasil diupdate',
                'user' => $user,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("User update failed: " . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Gagal update user',
                'error' => $e->getMessage(),
                'debug' => config('app.debug') ? $e->getFile() . ':' . $e->getLine() : null
            ], 500);
        }
    }

    /**
     * Override role (super admin only)
     */
    public function overrideRole(Request $request, User $user): JsonResponse
    {
        // Check permission
        if (!$request->user() || !$request->user()->hasRole('super-admin')) {
            return response()->json([
                'message' => 'Hanya super admin yang bisa override role'
            ], 403);
        }

        $validated = $request->validate([
            'role' => 'required|string|in:super-admin,admin,instructor,employee,user',
            'reason' => 'required|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            UserService::overrideRole(
                $user,
                $validated['role'],
                $request->user(),
                $validated['reason']
            );

            DB::commit();

            $user->effective_role = UserService::getEffectiveRole($user);

            return response()->json([
                'message' => 'Role berhasil di-override',
                'user' => $user,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal override role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete user (super admin only)
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        // Check permission
        if (!$request->user() || !$request->user()->hasRole('super-admin')) {
            return response()->json([
                'message' => 'Hanya super admin yang bisa menghapus user'
            ], 403);
        }

        try {
            DB::beginTransaction();

            UserService::logAudit(
                $request->user()->id,
                'delete',
                'User',
                $user->id,
                ['name' => $user->name, 'email' => $user->email],
                'User dihapus'
            );

            $user->delete();

            DB::commit();

            return response()->json([
                'message' => 'User berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get audit history untuk user
     */
    public function auditHistory(User $user, Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 50);
            $logs = AuditLog::where('entity_type', 'User')
                ->where('entity_id', $user->id)
                ->with('user:id,name,email')
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();

            return response()->json($logs);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch audit history',
                'error' => $e->getMessage(),
                'logs' => []
            ], 200); // Return 200 with empty logs on error
        }
    }

    /**
     * Trigger manual ERP sync (super-admin only)
     */
    public function triggerERPSync(Request $request): JsonResponse
    {
        try {
            if (!config('erp.enabled')) {
                return response()->json([
                    'message' => 'ERP integration is currently disabled',
                    'info' => 'Set ERP_ENABLED=true in .env to enable',
                ], 400);
            }

            $syncService = new ERPSyncService();
            $stats = $syncService->syncUsers();

            // Log the manual sync trigger
            AuditLog::create([
                'user_id' => auth()->user()->id,
                'action' => 'erp_sync_manual',
                'entity_type' => 'System',
                'entity_id' => 0,
                'changes' => $stats,
                'reason' => 'Manual ERP sync triggered by ' . auth()->user()->name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            return response()->json([
                'message' => 'ERP sync completed successfully',
                'stats' => $stats,
                'timestamp' => now()->toIso8601String(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'ERP sync failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
