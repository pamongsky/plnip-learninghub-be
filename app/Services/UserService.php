<?php

namespace App\Services;

use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UserService
{
    /**
     * Mapping access_group dari ERP ke role
     */
    public static function mapAccessGroupToRole(string $accessGroup): string
    {
        $mapping = [
            'SUPERADMIN' => 'super-admin',
            'ADMIN_UNIT' => 'admin',
            'INSTRUCTOR' => 'instructor',
            'USER' => 'user',
        ];

        return $mapping[strtoupper($accessGroup)] ?? 'user';
    }

    /**
     * Dapatkan effective role: gunakan override jika ada, else dari access_group
     */
    public static function getEffectiveRole(User $user): string
    {
        // 1. Check role_override first (for super-admin protection)
        if ($user->role_override) {
            return $user->role_override;
        }

        // 2. Check ERP access_group mapping
        if ($user->access_group) {
            return self::mapAccessGroupToRole($user->access_group);
        }

        // 3. Get role dari database relationships
        try {
            // Load roles jika belum loaded
            if (!$user->relationLoaded('roles')) {
                $user->load('roles');
            }

            if ($user->roles && $user->roles->count() > 0) {
                $roleName = $user->roles->first()?->name;
                if ($roleName) {
                    return $roleName;
                }
            }
        } catch (\Exception $e) {
            \Log::warning("Failed to get role for user {$user->id}: " . $e->getMessage());
        }

        // 4. Default fallback
        return 'user';
    }

    /**
     * Create user manual (untuk dev phase)
     */
    public static function createUserManual(array $data, User $createdBy): User
    {
        $data['source'] = 'manual';
        $data['password'] = Hash::make($data['password'] ?? 'TempPassword123!');
        $data['email_verified_at'] = now();
        $data['is_active'] = true;

        // Create user without using create() to avoid insertGetId issue
        $user = new User();
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = $data['password'];
        $user->phone = $data['phone'] ?? null;
        $user->employee_id = $data['employee_id'] ?? null;
        $user->department = $data['department'] ?? null;
        $user->position = $data['position'] ?? null;
        $user->source = $data['source'];
        $user->is_active = $data['is_active'];
        $user->email_verified_at = $data['email_verified_at'];
        $user->save();

        // Assign role
        $role = $data['role'] ?? 'user';
        $user->assignRole($role);

        // Log
        self::logAudit(
            $createdBy->id,
            'create',
            'User',
            $user->id,
            [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role,
            ],
            'User manual dibuat di dev phase'
        );

        return $user;
    }

    /**
     * Update user + role
     */
    public static function updateUser(User $user, array $data, User $updatedBy): User
    {
        $changes = [];

        // Track changes
        foreach ($data as $key => $value) {
            if ($key !== 'password' && $user->$key !== $value) {
                $changes[$key] = [
                    'old' => $user->$key,
                    'new' => $value,
                ];
            }
        }

        // Handle password change
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
            $changes['password'] = ['old' => '***', 'new' => '*** (changed)'];
        } else {
            // Remove password from data if empty/null
            unset($data['password']);
        }

        // Separate role dari data lain
        $roleData = $data['role'] ?? null;
        $updateData = array_diff_key($data, ['role' => '']);

        // Update user fields (excluding role)
        if (!empty($updateData)) {
            $user->update($updateData);
        }

        // Update role jika ada
        if ($roleData) {
            try {
                $user->syncRoles([$roleData]);
                \Log::info("Role updated for user {$user->id}: {$roleData}");
            } catch (\Exception $e) {
                \Log::error("Failed to sync role for user {$user->id}: " . $e->getMessage());
                throw $e;
            }
        }

        // Update role_changed_at jika column ada
        try {
            if ($roleData && \Schema::hasColumn('users', 'role_changed_at')) {
                $user->update(['role_changed_at' => now()]);
            }
        } catch (\Exception $e) {
            \Log::warning("Could not update role_changed_at: " . $e->getMessage());
        }

        // Log audit
        if (!empty($changes)) {
            self::logAudit(
                $updatedBy->id,
                'update',
                'User',
                $user->id,
                $changes,
                $data['reason'] ?? null
            );
        }

        return $user->fresh();
    }

    /**
     * Override role (super admin only)
     */
    public static function overrideRole(User $user, string $newRole, User $overriddenBy, string $reason = null): User
    {
        $oldRole = self::getEffectiveRole($user);

        $user->update([
            'role_override' => $newRole,
            'role_changed_at' => now(),
        ]);

        $user->syncRoles([$newRole]);

        self::logAudit(
            $overriddenBy->id,
            'update',
            'User',
            $user->id,
            [
                'role_override' => [
                    'old' => null,
                    'new' => $newRole,
                ],
                'effective_role' => [
                    'old' => $oldRole,
                    'new' => $newRole,
                ],
            ],
            $reason ?? 'Role override oleh super admin'
        );

        return $user;
    }

    /**
     * Sync user dari ERP (akan diimplementasi nanti)
     */
    public static function syncFromERP(array $erpUserData): User
    {
        $user = User::firstOrCreate(
            ['employee_id' => $erpUserData['employee_id']],
            [
                'name' => $erpUserData['name'],
                'email' => $erpUserData['email'],
                'source' => 'erp',
                'access_group' => $erpUserData['access_group'] ?? 'USER',
                'department' => $erpUserData['department'],
                'position' => $erpUserData['position'],
                'is_active' => $erpUserData['is_active'] ?? true,
                'synced_at' => now(),
            ]
        );

        // Update jika sudah ada
        if ($user->wasRecentlyCreated === false) {
            $user->update([
                'name' => $erpUserData['name'],
                'email' => $erpUserData['email'],
                'access_group' => $erpUserData['access_group'] ?? 'USER',
                'department' => $erpUserData['department'],
                'position' => $erpUserData['position'],
                'is_active' => $erpUserData['is_active'] ?? true,
                'synced_at' => now(),
            ]);
        }

        // Assign role berdasarkan access_group (jika belum override)
        if (!$user->role_override) {
            $role = self::mapAccessGroupToRole($user->access_group);
            $user->syncRoles([$role]);
        }

        return $user;
    }

    /**
     * Log audit
     */
    public static function logAudit(
        int $userId,
        string $action,
        string $entityType,
        int $entityId,
        array $changes = null,
        string $reason = null
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'changes' => $changes,
            'reason' => $reason,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
