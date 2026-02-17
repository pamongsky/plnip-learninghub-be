<?php

namespace App\Services;

use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ERPSyncService
{
    private string $erpApiUrl;
    private string $erpApiKey;
    private bool $erpEnabled;

    public function __construct()
    {
        $this->erpApiUrl = config('erp.api_url');
        $this->erpApiKey = config('erp.api_key');
        $this->erpEnabled = config('erp.enabled', false);
    }

    /**
     * Sync all users dari ERP
     */
    public function syncUsers(): array
    {
        if (!$this->erpEnabled) {
            Log::warning('ERP sync attempted but ERP is disabled');
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'error' => 'ERP sync is disabled',
            ];
        }

        try {
            Log::channel('audit')->info('ERP sync started');

            // Fetch data dari ERP
            $employees = $this->fetchEmployees();

            if (empty($employees)) {
                Log::warning('No employees data from ERP');
                return [
                    'created' => 0,
                    'updated' => 0,
                    'skipped' => 0,
                    'error' => 'No data received from ERP',
                ];
            }

            $stats = [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => 0,
            ];

            foreach ($employees as $empData) {
                try {
                    // Validasi data minimum
                    if (!isset($empData['employee_id']) || !isset($empData['email'])) {
                        Log::warning('Invalid employee data from ERP', ['data' => $empData]);
                        $stats['errors']++;
                        continue;
                    }

                    // Cek user berdasarkan employee_id (PRIMARY KEY)
                    $user = User::where('employee_id', $empData['employee_id'])->first();

                    if (!$user) {
                        // CREATE user baru
                        $user = $this->createUserFromERP($empData);
                        $stats['created']++;
                    } else {
                        // UPDATE user yang sudah ada
                        if ($user->source === 'erp') {
                            $this->updateUserFromERP($user, $empData);
                            $stats['updated']++;
                        } else {
                            // User manual (dev phase) - skip
                            $stats['skipped']++;
                        }
                    }
                } catch (\Exception $e) {
                    Log::channel('security')->error('Error syncing employee', [
                        'employee_id' => $empData['employee_id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                    $stats['errors']++;
                    continue;
                }
            }

            Log::channel('audit')->info('ERP sync completed', $stats);

            return $stats;
        } catch (\Exception $e) {
            Log::channel('security')->error('ERP sync failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch employees dari ERP API
     */
    private function fetchEmployees(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->erpApiKey,
                'Accept' => 'application/json',
            ])
            ->timeout(config('erp.timeout', 30))
            ->get($this->erpApiUrl);

            if (!$response->successful()) {
                throw new \Exception('ERP API error: ' . $response->status() . ' ' . $response->body());
            }

            return $response->json('employees', []);
        } catch (\Exception $e) {
            Log::channel('security')->error('Failed to fetch ERP employees', [
                'error' => $e->getMessage(),
                'url' => $this->erpApiUrl,
            ]);
            throw $e;
        }
    }

    /**
     * Create user dari ERP data
     */
    private function createUserFromERP(array $empData): User
    {
        // Use employee_id (NIP) as default password for ERP users
        $defaultPassword = $empData['employee_id'] ?? 'TempPassword123!';

        $user = User::create([
            'employee_id' => $empData['employee_id'],
            'email' => $empData['email'],
            'name' => $empData['name'],
            'phone' => $empData['phone'] ?? null,
            'department' => $empData['department'] ?? null,
            'position' => $empData['position'] ?? null,
            'source' => 'erp',
            'access_group' => $empData['access_group'] ?? 'USER',
            'is_active' => $empData['is_active'] ?? true,
            'synced_at' => now(),
            'password' => Hash::make($defaultPassword), // Use NIP as default password
            'must_change_password' => true, // Force password change on first login
            'account_source' => 'erp',
        ]);

        // Assign role berdasarkan access_group
        $role = UserService::mapAccessGroupToRole($user->access_group);
        $user->assignRole($role);

        // Log audit
        AuditLog::create([
            'user_id' => null, // System sync
            'action' => 'create',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'changes' => [
                'source' => 'erp',
                'email' => $user->email,
                'name' => $user->name,
                'access_group' => $user->access_group,
            ],
            'reason' => 'User created from ERP sync',
            'ip_address' => null,
        ]);

        Log::channel('audit')->info('User created from ERP', [
            'employee_id' => $user->employee_id,
            'name' => $user->name,
            'email' => $user->email,
        ]);

        return $user;
    }

    /**
     * Update user dari ERP data
     */
    private function updateUserFromERP(User $user, array $empData): void
    {
        $changes = [];

        // Track changes
        $fields = ['name', 'email', 'phone', 'department', 'position', 'access_group', 'is_active'];

        foreach ($fields as $field) {
            $oldValue = $user->$field;
            $newValue = $empData[$field] ?? null;

            if ($oldValue !== $newValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        // Update user
        $user->update([
            'name' => $empData['name'],
            'email' => $empData['email'],
            'phone' => $empData['phone'] ?? null,
            'department' => $empData['department'] ?? null,
            'position' => $empData['position'] ?? null,
            'access_group' => $empData['access_group'] ?? 'USER',
            'is_active' => $empData['is_active'] ?? true,
            'synced_at' => now(),
        ]);

        // Update role jika tidak ada override
        if (!$user->role_override) {
            $role = UserService::mapAccessGroupToRole($user->access_group);
            $user->syncRoles([$role]);
        }

        // Log audit jika ada perubahan
        if (!empty($changes)) {
            AuditLog::create([
                'user_id' => null, // System sync
                'action' => 'update',
                'entity_type' => 'User',
                'entity_id' => $user->id,
                'changes' => $changes,
                'reason' => 'User updated from ERP sync',
                'ip_address' => null,
            ]);

            Log::channel('audit')->info('User updated from ERP', [
                'employee_id' => $user->employee_id,
                'changes' => array_keys($changes),
            ]);
        }
    }

    /**
     * Get single employee dari ERP untuk JIT validation
     */
    public function getEmployee(string $employeeId): ?array
    {
        if (!$this->erpEnabled) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->erpApiKey,
                'Accept' => 'application/json',
            ])
            ->timeout(config('erp.timeout', 30))
            ->get($this->erpApiUrl . '/' . $employeeId);

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::channel('security')->error('Failed to fetch ERP employee', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Validate user status di ERP (untuk login check)
     */
    public function validateUserStatus(User $user): bool
    {
        if (!$this->erpEnabled || $user->source !== 'erp') {
            return true; // Jika ERP disabled atau user manual, anggap valid
        }

        $empData = $this->getEmployee($user->employee_id);

        if (!$empData) {
            Log::warning('Employee not found in ERP', [
                'user_id' => $user->id,
                'employee_id' => $user->employee_id,
            ]);
            return false;
        }

        // Check if active in ERP
        $isActive = $empData['is_active'] ?? false;

        if (!$isActive) {
            Log::channel('security')->warning('Login attempt from inactive ERP user', [
                'user_id' => $user->id,
                'employee_id' => $user->employee_id,
            ]);
        }

        return $isActive;
    }
}
