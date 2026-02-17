<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use App\Utils\PasswordGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if user is active
        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account is inactive. Please contact administrator.'],
            ]);
        }

        // Create token
        $token = $user->createToken('api-token')->plainTextToken;

        // Log login activity
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'changes' => null,
            'reason' => 'Login sukses',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'employee_id' => $user->employee_id,
                    'phone' => $user->phone,
                    'department' => $user->department,
                    'position' => $user->position,
                    'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                    'is_active' => $user->is_active,
                    'roles' => $user->getRoleNames(),
                    'created_at' => $user->created_at,
                ],
                'token' => $token,
                'requires_password_change' => $user->must_change_password,
            ],
        ], 200);
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'is_active' => true,
            'must_change_password' => false, // Self-registered users don't need to change password
            'password_changed_at' => now(),
            'account_source' => 'manual',
        ]);

        // Assign default learner role
        $user->assignRole('learner');

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames(),
                ],
                'token' => $token,
            ],
        ], 201);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        // Log logout activity
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'logout',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'changes' => null,
            'reason' => 'Logout dari sistem',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $user->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful',
        ], 200);
    }

    public function user(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'employee_id' => $user->employee_id,
                    'phone' => $user->phone,
                    'department' => $user->department,
                    'position' => $user->position,
                    'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                    'is_active' => $user->is_active,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                    'must_change_password' => $user->must_change_password,
                    'created_at' => $user->created_at,
                ],
            ],
        ], 200);
    }

    public function changePasswordFirstTime(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        // Validate new password strength
        $validation = PasswordGenerator::validate($request->new_password);
        if (!$validation['valid']) {
            throw ValidationException::withMessages([
                'new_password' => $validation['errors'],
            ]);
        }

        // Update password
        $user->password = Hash::make($request->new_password);
        $user->must_change_password = false;
        $user->password_changed_at = now();
        $user->save();

        // Log password change activity
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'password_changed',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'changes' => json_encode(['password' => 'changed']),
            'reason' => 'First time password change',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully. You can now access your dashboard.',
        ], 200);
    }
}
