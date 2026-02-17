<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // =====================
        // CREATE PERMISSIONS
        // =====================

        // Users
        Permission::firstOrCreate(['name' => 'users.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'users.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'users.edit', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'users.delete', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'users.override-role', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'users.audit', 'guard_name' => 'web']);

        // Announcements
        Permission::firstOrCreate(['name' => 'announcements.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'announcements.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'announcements.edit', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'announcements.delete', 'guard_name' => 'web']);

        // Reports
        Permission::firstOrCreate(['name' => 'reports.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'reports.export', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'reports.generate', 'guard_name' => 'web']);

        // Courses
        Permission::firstOrCreate(['name' => 'courses.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'courses.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'courses.edit', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'courses.delete', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'courses.enroll', 'guard_name' => 'web']);

        // Messages
        Permission::firstOrCreate(['name' => 'messages.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'messages.send', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'messages.delete', 'guard_name' => 'web']);

        // Support Tickets
        Permission::firstOrCreate(['name' => 'tickets.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'tickets.create', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'tickets.resolve', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'tickets.close', 'guard_name' => 'web']);

        // Escalations
        Permission::firstOrCreate(['name' => 'escalations.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'escalations.manage', 'guard_name' => 'web']);

        // Settings
        Permission::firstOrCreate(['name' => 'settings.company', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'settings.partners', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'settings.moodle', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'settings.roles', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'settings.email', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'settings.appearance', 'guard_name' => 'web']);

        // =====================
        // ASSIGN PERMISSIONS TO ROLES
        // =====================

        // Super Admin - all permissions
        /** @var Role $superAdminRole */
        $superAdminRole = Role::where('name', 'super-admin')->first();
        if ($superAdminRole) {
            $superAdminRole->syncPermissions(Permission::all());
        }

        // Admin - manage users, announcements, reports, tickets
        /** @var Role $adminRole */
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminRole->syncPermissions([
                'users.view',
                'users.create',
                'users.edit',
                'users.audit',
                'announcements.view',
                'announcements.create',
                'announcements.edit',
                'announcements.delete',
                'reports.view',
                'reports.export',
                'messages.view',
                'messages.send',
                'tickets.view',
                'tickets.create',
                'tickets.resolve',
            ]);
        }

        // Instructor - view announcements, courses, messages, can enroll
        /** @var Role $instructorRole */
        $instructorRole = Role::where('name', 'instructor')->first();
        if ($instructorRole) {
            $instructorRole->syncPermissions([
                'announcements.view',
                'courses.view',
                'courses.create',
                'courses.edit',
                'courses.enroll',
                'messages.view',
                'messages.send',
                'tickets.view',
                'tickets.create',
            ]);
        }

        // Learner role (merged from employee + user) - view announcements, courses, messages
        /** @var Role $learnerRole */
        $learnerRole = Role::where('name', 'learner')->first();
        if ($learnerRole) {
            $learnerRole->syncPermissions([
                'announcements.view',
                'courses.view',
                'messages.view',
                'messages.send',
                'tickets.view',
                'tickets.create',
            ]);
        }

        echo "✅ Roles & Permissions seeded successfully!\n";
    }
}
