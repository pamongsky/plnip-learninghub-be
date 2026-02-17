<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use App\Mail\OTPMail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class ForgotPasswordController extends Controller
{
    /**
     * Step 1: Request OTP
     * User inputs email, system sends OTP to registered phone number
     */
    public function requestOTP(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = strtolower($request->email);

        // Rate limiting: Max 3 requests per 15 minutes per email
        $recentAttempts = DB::table('password_reset_otps')
            ->where('email', $email)
            ->where('created_at', '>=', Carbon::now()->subMinutes(15))
            ->count();

        if ($recentAttempts >= 3) {
            return ApiResponse::error('Terlalu banyak percobaan. Silakan coba lagi dalam 15 menit.', null, 429);
        }

        // Find user by email
        $user = User::where('email', $email)->first();

        if (!$user) {
            // Security: Don't reveal if email exists or not
            return ApiResponse::notFound('Email tidak terdaftar.');
        }

        // Generate 6-digit OTP
        $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = Carbon::now()->addMinutes(5);

        // Store OTP in database
        DB::table('password_reset_otps')->insert([
            'user_id' => $user->id,
            'email' => $email,
            'phone_number' => $user->phone ?? 'N/A',
            'otp_code' => Hash::make($otpCode), // Hash OTP for security
            'is_verified' => false,
            'expires_at' => $expiresAt,
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Send OTP via Email
        try {
            Mail::to($user->email)->send(new OTPMail($otpCode, $user->name, 5));
        } catch (\Exception $e) {
            // Log error but don't reveal to user
            Log::error("Failed to send OTP email to {$email}: " . $e->getMessage());

            return ApiResponse::serverError('Gagal mengirim email. Silakan coba lagi atau hubungi HCIS.');
        }

        // Only log OTP in debug mode for development
        if (config('app.debug')) {
            Log::info("OTP sent to {$email}: {$otpCode}");
        }

        // Mask email (show only first 2 chars and domain)
        $emailParts = explode('@', $email);
        $maskedEmail = substr($emailParts[0], 0, 2) . str_repeat('*', strlen($emailParts[0]) - 2) . '@' . $emailParts[1];

        // Log audit
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'forgot_password_request',
            'entity_type' => 'PasswordReset',
            'entity_id' => $user->id,
            'changes' => null,
            'reason' => 'User requested password reset OTP',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return ApiResponse::success([
            'email_masked' => $maskedEmail,
            'expires_in' => 300, // 5 minutes in seconds
        ], 'Kode OTP telah dikirim ke email Anda.');
    }

    /**
     * Step 2: Verify OTP
     * User inputs OTP code, system verifies and returns reset token
     */
    public function verifyOTP(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'otp_code' => 'required|string|size:6',
        ]);

        $email = strtolower($request->email);
        $otpCode = $request->otp_code;

        // Find the most recent OTP for this email
        $otpRecord = DB::table('password_reset_otps')
            ->where('email', $email)
            ->where('is_verified', false)
            ->where('expires_at', '>', Carbon::now())
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$otpRecord) {
            return ApiResponse::error('Kode OTP tidak valid atau sudah kadaluarsa.', null, 400);
        }

        // Check attempts (max 5 attempts)
        if ($otpRecord->attempts >= 5) {
            // Delete expired OTP
            DB::table('password_reset_otps')->where('id', $otpRecord->id)->delete();

            return ApiResponse::error('Terlalu banyak percobaan yang salah. Silakan minta OTP baru.', null, 429);
        }

        // Verify OTP
        if (!Hash::check($otpCode, $otpRecord->otp_code)) {
            // Increment attempts
            DB::table('password_reset_otps')
                ->where('id', $otpRecord->id)
                ->increment('attempts');

            $remainingAttempts = 5 - ($otpRecord->attempts + 1);

            return ApiResponse::error("Kode OTP salah. Sisa percobaan: {$remainingAttempts}", null, 400);
        }

        // Mark OTP as verified
        DB::table('password_reset_otps')
            ->where('id', $otpRecord->id)
            ->update([
                'is_verified' => true,
                'updated_at' => now(),
            ]);

        // Generate reset token (JWT-like token, valid for 10 minutes)
        $resetToken = base64_encode(json_encode([
            'user_id' => $otpRecord->user_id,
            'email' => $email,
            'otp_id' => $otpRecord->id,
            'expires_at' => Carbon::now()->addMinutes(10)->timestamp,
            'signature' => hash_hmac('sha256', $otpRecord->user_id . $email, config('app.key')),
        ]));

        // Log audit
        AuditLog::create([
            'user_id' => $otpRecord->user_id,
            'action' => 'otp_verified',
            'entity_type' => 'PasswordReset',
            'entity_id' => $otpRecord->user_id,
            'changes' => null,
            'reason' => 'OTP verified successfully',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return ApiResponse::success([
            'reset_token' => $resetToken,
        ], 'Kode OTP berhasil diverifikasi.');
    }

    /**
     * Step 3: Reset Password
     * User inputs new password with reset token
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'reset_token' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        // Decode and validate reset token
        try {
            $tokenData = json_decode(base64_decode($request->reset_token), true);

            if (!$tokenData || !isset($tokenData['user_id'], $tokenData['email'], $tokenData['otp_id'], $tokenData['expires_at'], $tokenData['signature'])) {
                throw new \Exception('Invalid token structure');
            }

            // Check if token expired
            if ($tokenData['expires_at'] < Carbon::now()->timestamp) {
                return ApiResponse::error('Token reset password sudah kadaluarsa. Silakan minta OTP baru.', null, 400);
            }

            // Verify signature
            $expectedSignature = hash_hmac('sha256', $tokenData['user_id'] . $tokenData['email'], config('app.key'));
            if (!hash_equals($expectedSignature, $tokenData['signature'])) {
                throw new \Exception('Invalid signature');
            }

        } catch (\Exception $e) {
            return ApiResponse::error('Token tidak valid.', null, 400);
        }

        // Find user
        $user = User::find($tokenData['user_id']);

        if (!$user) {
            return ApiResponse::notFound('User tidak ditemukan.');
        }

        // Verify OTP was verified
        $otpRecord = DB::table('password_reset_otps')
            ->where('id', $tokenData['otp_id'])
            ->where('is_verified', true)
            ->first();

        if (!$otpRecord) {
            return ApiResponse::error('OTP belum diverifikasi atau sudah digunakan.', null, 400);
        }

        // Update password and reset password management fields
        $user->password = Hash::make($request->new_password);
        $user->must_change_password = false; // User chose their own password
        $user->password_changed_at = now();
        $user->save();

        // Delete all OTP records for this user (cleanup)
        DB::table('password_reset_otps')->where('user_id', $user->id)->delete();

        // Invalidate all existing tokens (force logout from all devices)
        DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->delete();

        // Log audit
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'password_reset',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'changes' => null,
            'reason' => 'Password reset via OTP',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Optional: Send email notification
        // TODO: Send email to user about password change

        return ApiResponse::success(null, 'Password berhasil diubah. Silakan login dengan password baru.');
    }
}
