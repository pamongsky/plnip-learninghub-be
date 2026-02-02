<?php

namespace App\Http\Controllers\API;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RoleController extends \App\Http\Controllers\Controller
{
    /**
     * Get all roles with permissions
     */
    public function getAllRoles(): JsonResponse
    {
        try {
            $roles = Role::with('permissions')
                ->orderBy('name')
                ->get()
                ->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'display_name' => $this->getDisplayName($role->name),
                        'description' => $this->getDescription($role->name),
                        'user_count' => $role->users()->count(),
                        'permissions' => $role->permissions->pluck('name')->toArray(),
                        'guard_name' => $role->guard_name,
                    ];
                });

            return response()->json($roles);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal fetch roles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all available permissions
     */
    public function getAllPermissions(): JsonResponse
    {
        try {
            $permissions = Permission::orderBy('name')
                ->get()
                ->map(function ($perm) {
                    return [
                        'id' => $perm->id,
                        'name' => $perm->name,
                        'display_name' => $this->getPermissionDisplayName($perm->name),
                        'category' => $this->getPermissionCategory($perm->name),
                    ];
                });

            return response()->json($permissions);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal fetch permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single role with permissions
     */
    public function showRole(Role $role): JsonResponse
    {
        try {
            $role->load('permissions');

            return response()->json([
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $this->getDisplayName($role->name),
                'description' => $this->getDescription($role->name),
                'user_count' => $role->users()->count(),
                'permissions' => $role->permissions->pluck('name')->toArray(),
                'all_permissions' => Permission::orderBy('name')->get(['id', 'name'])->toArray(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal fetch role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new role
     */
    public function createRole(Request $request): JsonResponse
    {
        if (!$request->user() || !$request->user()->hasRole('super-admin')) {
            return response()->json([
                'message' => 'Hanya super admin yang bisa create role'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|unique:roles|max:255',
            'display_name' => 'required|string|max:255',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        // Only allow creating "admin" type roles (contain "admin" in name)
        // e.g., "admin", "admin-unit", "admin-divisi", "content-admin"
        if (!str_contains(strtolower($validated['name']), 'admin')) {
            return response()->json([
                'message' => 'Hanya bisa membuat role dengan tipe "admin". Contoh: admin-unit, admin-divisi, content-admin'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $role = Role::create([
                'name' => $validated['name'],
                'guard_name' => 'web',
            ]);

            // Assign permissions
            if (!empty($validated['permissions'])) {
                $role->syncPermissions($validated['permissions']);
            }

            DB::commit();

            return response()->json([
                'message' => 'Role berhasil dibuat',
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $validated['display_name'],
                    'permissions' => $role->permissions->pluck('name')->toArray(),
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal create role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update role permissions
     */
    public function updateRolePermissions(Request $request, Role $role): JsonResponse
    {
        if (!$request->user() || !$request->user()->hasRole('super-admin')) {
            return response()->json([
                'message' => 'Hanya super admin yang bisa update role'
            ], 403);
        }

        // Protect built-in roles
        if (in_array($role->name, ['super-admin', 'admin', 'instructor', 'employee', 'user'])) {
            // Allow modification tapi dengan caution
        }

        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        try {
            DB::beginTransaction();

            // Sync permissions
            $role->syncPermissions($validated['permissions']);

            DB::commit();

            return response()->json([
                'message' => 'Permissions berhasil diupdate',
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => $role->permissions->pluck('name')->toArray(),
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal update role permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete role
     */
    public function deleteRole(Request $request, Role $role): JsonResponse
    {
        if (!$request->user() || !$request->user()->hasRole('super-admin')) {
            return response()->json([
                'message' => 'Hanya super admin yang bisa delete role'
            ], 403);
        }

        // Protect built-in roles
        if (in_array($role->name, ['super-admin', 'admin', 'instructor', 'employee', 'user'])) {
            return response()->json([
                'message' => 'Tidak bisa delete built-in roles'
            ], 403);
        }

        // Check if role has users
        if ($role->users()->count() > 0) {
            return response()->json([
                'message' => 'Tidak bisa delete role yang masih digunakan users'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $role->delete();

            DB::commit();

            return response()->json([
                'message' => 'Role berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal delete role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get display name for role
     */
    private function getDisplayName(string $roleName): string
    {
        $names = [
            'super-admin' => 'Super Admin',
            'admin' => 'Admin',
            'instructor' => 'Instructor',
            'employee' => 'Employee',
            'user' => 'User',
        ];
        return $names[$roleName] ?? ucfirst($roleName);
    }

    /**
     * Get description for role
     */
    private function getDescription(string $roleName): string
    {
        $descriptions = [
            'super-admin' => 'Akses penuh ke seluruh sistem',
            'admin' => 'Kelola user dan pengumuman unit',
            'instructor' => 'Kelola kelas dan lihat peserta',
            'employee' => 'Akses dasar untuk pembelajaran',
            'user' => 'Akses dasar untuk pembelajaran',
        ];
        return $descriptions[$roleName] ?? 'Role ini tidak memiliki deskripsi';
    }

    /**
     * Get permission display name
     */
    private function getPermissionDisplayName(string $permName): string
    {
        $names = [
            // Users
            'users.view' => 'Lihat User',
            'users.create' => 'Tambah User',
            'users.edit' => 'Edit User',
            'users.delete' => 'Hapus User',
            'users.override-role' => 'Override Role User',
            'users.audit' => 'Lihat Audit Log User',

            // Announcements
            'announcements.view' => 'Lihat Pengumuman',
            'announcements.create' => 'Buat Pengumuman',
            'announcements.edit' => 'Edit Pengumuman',
            'announcements.delete' => 'Hapus Pengumuman',

            // Reports
            'reports.view' => 'Lihat Laporan',
            'reports.export' => 'Export Laporan',
            'reports.generate' => 'Generate Laporan',

            // Courses
            'courses.view' => 'Lihat Kursus',
            'courses.create' => 'Buat Kursus',
            'courses.edit' => 'Edit Kursus',
            'courses.delete' => 'Hapus Kursus',
            'courses.enroll' => 'Enroll Peserta',

            // Messages
            'messages.view' => 'Lihat Pesan',
            'messages.send' => 'Kirim Pesan',
            'messages.delete' => 'Hapus Pesan',

            // Support Tickets
            'tickets.view' => 'Lihat Tiket Support',
            'tickets.create' => 'Buat Tiket',
            'tickets.resolve' => 'Resolve Tiket',
            'tickets.close' => 'Close Tiket',

            // Escalations
            'escalations.view' => 'Lihat Eskalasi',
            'escalations.manage' => 'Manage Eskalasi',

            // Settings
            'settings.company' => 'Edit Company Profile',
            'settings.partners' => 'Kelola Partner',
            'settings.moodle' => 'Sync Moodle',
            'settings.roles' => 'Kelola Roles & Permissions',
            'settings.email' => 'Konfigurasi Email',
            'settings.appearance' => 'Konfigurasi Tampilan',
        ];

        return $names[$permName] ?? ucfirst(str_replace('.', ' - ', $permName));
    }

    /**
     * Get permission category
     */
    private function getPermissionCategory(string $permName): string
    {
        if (str_starts_with($permName, 'users')) return 'Users';
        if (str_starts_with($permName, 'announcements')) return 'Announcements';
        if (str_starts_with($permName, 'reports')) return 'Reports';
        if (str_starts_with($permName, 'courses')) return 'Courses';
        if (str_starts_with($permName, 'messages')) return 'Messages';
        if (str_starts_with($permName, 'tickets')) return 'Support';
        if (str_starts_with($permName, 'escalations')) return 'Escalations';
        if (str_starts_with($permName, 'settings')) return 'Settings';

        return 'Other';
    }
}
