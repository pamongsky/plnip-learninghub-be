<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Utils\FileValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /**
     * Get authenticated user profile
     */
    public function show(Request $request)
    {
        try {
            $user = $request->user()->load('roles');

            return response()->json([
                'success' => true,
                'message' => 'Profile retrieved successfully',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'department' => $user->department,
                        'position' => $user->position,
                        'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                        'email_verified_at' => $user->email_verified_at,
                        'roles' => $user->roles->pluck('name'),
                        'created_at' => $user->created_at,
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user profile
     */
    public function update(Request $request)
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:20'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user->update([
                'name' => $request->name,
                'phone' => $request->phone,
            ]);

            // Sync name to Moodle DB if user has moodle_user_id
            if ($user->moodle_user_id) {
                try {
                    $nameParts = explode(' ', trim($request->name), 2);
                    \Illuminate\Support\Facades\DB::connection('moodle')->table('user')
                        ->where('id', $user->moodle_user_id)
                        ->update([
                            'firstname' => $nameParts[0],
                            'lastname'  => $nameParts[1] ?? '-',
                            'timemodified' => time(),
                        ]);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning("Could not sync name to Moodle: " . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'department' => $user->department,
                        'position' => $user->position,
                        'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload/update avatar
     */
    public function uploadAvatar(Request $request)
    {
        try {
            // Basic validation
            $validator = Validator::make($request->all(), [
                'avatar' => ['required', 'file'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Avatar file is required',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Comprehensive file validation
            $file = $request->file('avatar');
            $validation = FileValidator::validate($file);

            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'File validation failed',
                    'errors' => $validation['errors']
                ], 422);
            }

            $user = $request->user();

            // Delete old avatar if exists
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Sanitize filename and store
            $originalName = $file->getClientOriginalName();
            $sanitizedName = FileValidator::sanitizeFilename($originalName);
            $extension = $file->getClientOriginalExtension();
            $filename = pathinfo($sanitizedName, PATHINFO_FILENAME) . '_' . time() . '.' . $extension;

            $path = $file->storeAs('avatars', $filename, 'public');

            $user->update(['avatar' => $path]);

            return response()->json([
                'success' => true,
                'message' => 'Avatar uploaded successfully',
                'data' => [
                    'avatar' => asset('storage/' . $path),
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'avatar' => asset('storage/' . $path),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload avatar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete avatar
     */
    public function deleteAvatar(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
                $user->update(['avatar' => null]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Avatar deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete avatar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'current_password' => ['required', 'current_password'],
                'password' => [
                    'required',
                    'confirmed',
                    'min:8',
                    'regex:/[a-z]/',      // must contain lowercase
                    'regex:/[A-Z]/',      // must contain uppercase
                    'regex:/[0-9]/',      // must contain number
                    'regex:/[@$!%*#?&]/', // must contain special char
                ],
            ], [
                'password.regex' => 'Password harus mengandung huruf besar, huruf kecil, angka, dan karakter spesial (@$!%*#?&)',
                'password.min' => 'Password minimal 8 karakter',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $user->update([
                'password' => Hash::make($request->password)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
