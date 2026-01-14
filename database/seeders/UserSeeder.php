<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@plnip.local'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('Admin123!'),
                'employee_id' => 'SA001',
                'department' => 'IT',
                'position' => 'System Administrator',
                'is_active' => true,
            ]
        );
        $superAdmin->assignRole('super-admin');

        // Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@plnip.local'],
            [
                'name' => 'Admin PLN',
                'password' => Hash::make('Admin123!'),
                'employee_id' => 'AD001',
                'department' => 'HCIS',
                'position' => 'Administrator',
                'is_active' => true,
            ]
        );
        $admin->assignRole('admin');

        // Instructor
        $instructor = User::firstOrCreate(
            ['email' => 'instructor@plnip.local'],
            [
                'name' => 'instructor',
                'password' => Hash::make('Instructor123!'),
                'employee_id' => 'IN001',
                'department' => 'Training',
                'position' => 'Senior Instructor',
                'is_active' => true,
            ]
        );
        $instructor->assignRole('instructor');

        // Employee
        $employee = User::firstOrCreate(
            ['email' => 'employee@plnip.local'],
            [
                'name' => 'Muhammad Fahmi',
                'password' => Hash::make('Employee123!'),
                'employee_id' => 'EM001',
                'department' => 'HCIS',
                'position' => 'Staff',
                'is_active' => true,
            ]
        );
        $employee->assignRole('employee');
    }
}