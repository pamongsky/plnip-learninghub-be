<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PermissionController extends Controller
{
    /**
     * Get all permissions (grouped by category)
     */
    public function index(): JsonResponse
    {
        try {
            $permissions = Permission::orderBy('name')->get();

            // Group by category
            $grouped = [];
            foreach ($permissions as $perm) {
                $parts = explode('.', $perm->name);
                $category = $parts[0] ?? 'other';

                if (!isset($grouped[$category])) {
                    $grouped[$category] = [];
                }

                $grouped[$category][] = [
                    'id' => $perm->id,
                    'name' => $perm->name,
                    'guard_name' => $perm->guard_name,
                    'created_at' => $perm->created_at,
                    'updated_at' => $perm->updated_at,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'permissions' => $permissions,
                    'grouped' => $grouped,
                    'total' => count($permissions),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new permission
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:permissions,name|regex:/^[a-z]+\.[a-z]+$/',
            'guard_name' => 'sometimes|string',
        ], [
            'name.regex' => 'Permission name must be in format: category.action (e.g. users.create)',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $permission = Permission::create([
                'name' => $request->name,
                'guard_name' => $request->guard_name ?? 'web',
            ]);

            // Clear permission cache
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            return response()->json([
                'success' => true,
                'message' => 'Permission created successfully',
                'data' => $permission
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create permission',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single permission with roles using it
     */
    public function show($id): JsonResponse
    {
        try {
            $permission = Permission::findOrFail($id);

            // Get roles that have this permission
            $roles = $permission->roles()->get(['id', 'name']);

            return response()->json([
                'success' => true,
                'data' => [
                    'permission' => $permission,
                    'roles' => $roles,
                    'roles_count' => count($roles),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Permission not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update permission
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|regex:/^[a-z]+\.[a-z]+$/|unique:permissions,name,' . $id,
        ], [
            'name.regex' => 'Permission name must be in format: category.action (e.g. users.create)',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $permission = Permission::findOrFail($id);
            $permission->update([
                'name' => $request->name,
            ]);

            // Clear permission cache
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            return response()->json([
                'success' => true,
                'message' => 'Permission updated successfully',
                'data' => $permission
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update permission',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete permission
     */
    public function destroy($id): JsonResponse
    {
        try {
            $permission = Permission::findOrFail($id);

            // Check if permission is assigned to any role
            $rolesCount = $permission->roles()->count();

            if ($rolesCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete permission. It is assigned to {$rolesCount} role(s). Remove from roles first.",
                ], 409); // 409 Conflict
            }

            $permission->delete();

            // Clear permission cache
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            return response()->json([
                'success' => true,
                'message' => 'Permission deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete permission',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk create permissions
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'permissions' => 'required|array|min:1',
            'permissions.*.name' => 'required|string|unique:permissions,name|regex:/^[a-z]+\.[a-z]+$/',
            'permissions.*.guard_name' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $created = [];
            $errors = [];

            DB::beginTransaction();

            foreach ($request->permissions as $permData) {
                try {
                    $permission = Permission::create([
                        'name' => $permData['name'],
                        'guard_name' => $permData['guard_name'] ?? 'web',
                    ]);
                    $created[] = $permission;
                } catch (\Exception $e) {
                    $errors[] = [
                        'name' => $permData['name'],
                        'error' => $e->getMessage()
                    ];
                }
            }

            if (count($errors) > 0 && count($created) === 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create any permissions',
                    'errors' => $errors
                ], 500);
            }

            DB::commit();

            // Clear permission cache
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            return response()->json([
                'success' => true,
                'message' => count($created) . ' permission(s) created successfully',
                'data' => [
                    'created' => $created,
                    'errors' => $errors,
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync permissions (add missing standard permissions)
     */
    public function syncStandard(): JsonResponse
    {
        try {
            $standardPermissions = [
                // Users
                'users.view', 'users.create', 'users.edit', 'users.delete', 'users.override-role', 'users.audit',
                // Announcements
                'announcements.view', 'announcements.create', 'announcements.edit', 'announcements.delete',
                // Reports
                'reports.view', 'reports.export', 'reports.generate',
                // Courses
                'courses.view', 'courses.create', 'courses.edit', 'courses.delete', 'courses.enroll',
                // Messages
                'messages.view', 'messages.send', 'messages.delete',
                // Support Tickets
                'tickets.view', 'tickets.create', 'tickets.resolve', 'tickets.close',
                // Escalations
                'escalations.view', 'escalations.manage',
                // Settings
                'settings.company', 'settings.partners', 'settings.moodle', 'settings.roles', 'settings.email', 'settings.appearance',
            ];

            $created = [];
            $skipped = [];

            foreach ($standardPermissions as $permName) {
                $exists = Permission::where('name', $permName)->exists();
                if (!$exists) {
                    $permission = Permission::create([
                        'name' => $permName,
                        'guard_name' => 'web',
                    ]);
                    $created[] = $permName;
                } else {
                    $skipped[] = $permName;
                }
            }

            // Clear permission cache
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            return response()->json([
                'success' => true,
                'message' => count($created) . ' permission(s) added',
                'data' => [
                    'created' => $created,
                    'skipped' => $skipped,
                    'total_standard' => count($standardPermissions),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get permission usage stats
     */
    public function stats(): JsonResponse
    {
        try {
            $totalPermissions = Permission::count();
            $totalRoles = Role::count();

            // Permissions not assigned to any role
            $unassigned = Permission::doesntHave('roles')->count();

            // Most used permissions
            $mostUsed = Permission::withCount('roles')
                ->orderBy('roles_count', 'desc')
                ->take(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_permissions' => $totalPermissions,
                    'total_roles' => $totalRoles,
                    'unassigned_permissions' => $unassigned,
                    'most_used_permissions' => $mostUsed,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
