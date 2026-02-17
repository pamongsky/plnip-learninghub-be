<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Consolidate 'employee' and 'user' roles into single 'learner' role
     */
    public function up(): void
    {
        // Get employee and user role IDs
        $employeeRole = DB::table('roles')->where('name', 'employee')->first();
        $userRole = DB::table('roles')->where('name', 'user')->first();

        // If both roles exist, we need to consolidate them
        if ($employeeRole && $userRole) {
            // 1. Move all 'user' role assignments to 'employee' role
            DB::table('model_has_roles')
                ->where('role_id', $userRole->id)
                ->update(['role_id' => $employeeRole->id]);

            // 2. Move all 'user' role permissions to 'employee' role (if different)
            $userPermissions = DB::table('role_has_permissions')
                ->where('role_id', $userRole->id)
                ->pluck('permission_id')
                ->toArray();

            foreach ($userPermissions as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $employeeRole->id,
                ]);
            }

            // 3. Delete 'user' role permissions and role itself
            DB::table('role_has_permissions')->where('role_id', $userRole->id)->delete();
            DB::table('roles')->where('id', $userRole->id)->delete();
        }

        // Now rename 'employee' role to 'learner'
        if ($employeeRole) {
            DB::table('roles')
                ->where('id', $employeeRole->id)
                ->update(['name' => 'learner', 'updated_at' => now()]);
        }

        // If only 'user' role exists (no 'employee'), rename it to 'learner'
        if (!$employeeRole && $userRole) {
            DB::table('roles')
                ->where('id', $userRole->id)
                ->update(['name' => 'learner', 'updated_at' => now()]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * NOTE: This cannot perfectly restore the original state
     * because we don't know which users were 'employee' vs 'user'
     */
    public function down(): void
    {
        // Rename 'learner' back to 'employee'
        $learnerRole = DB::table('roles')->where('name', 'learner')->first();

        if ($learnerRole) {
            DB::table('roles')
                ->where('id', $learnerRole->id)
                ->update(['name' => 'employee', 'updated_at' => now()]);
        }

        // Recreate 'user' role (without assignments - we don't know original split)
        $employeeRole = DB::table('roles')->where('name', 'employee')->first();
        if ($employeeRole) {
            DB::table('roles')->insert([
                'name' => 'user',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
