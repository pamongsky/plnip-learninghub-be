<?php

namespace App\Http\Controllers\API;
use AppHelpersApiResponse;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MoodleAuthController extends Controller
{
    private $moodleUrl;
    private $token;

    public function __construct()
    {
        $this->moodleUrl = rtrim(config('services.moodle.url'), '/');
        $this->token = config('services.moodle.token');
    }

    public function getLoginUrl(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $email = strtolower($user->email);
        $username = strtolower(str_replace(['@', '.', ' '], ['_', '_', '_'], explode('@', $email)[0]));
        $firstname = explode(' ', $user->name)[0];
        $lastname = count(explode(' ', $user->name)) > 1 ? substr(strstr($user->name, " "), 1) : 'User';

        Log::info("Hybrid SSO: Portal user {$user->email} requesting Moodle access");

        try {
            // HYBRID SYSTEM: Portal (Oracle) adalah MASTER
            // 1. User sudah ada di Portal (dari $request->user())
            // 2. Cek apakah user sudah di Moodle
            $moodleUser = DB::connection('moodle')->table('user')
                ->where('email', $email)
                ->where('deleted', 0)
                ->first();

            // 3. Kalau belum di Moodle, AUTO CREATE dari data Portal
            if (!$moodleUser) {
                Log::info("User not in Moodle, auto-creating from Portal data: $email");

                // Generate random password (user ga perlu tau, SSO otomatis)
                $randomPassword = Str::random(32);
                $hashedPassword = password_hash($randomPassword, PASSWORD_BCRYPT);

                // Create user di Moodle
                $userId = DB::connection('moodle')->table('user')->insertGetId([
                    'auth' => 'userkey',
                    'confirmed' => 1,
                    'username' => $username,
                    'password' => $hashedPassword,
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                    'email' => $email,
                    'emailstop' => 0,
                    'mailformat' => 1,
                    'maildigest' => 0,
                    'maildisplay' => 2,
                    'autosubscribe' => 1,
                    'trackforums' => 0,
                    'timecreated' => now()->timestamp,
                    'timemodified' => now()->timestamp,
                    'trustbitmask' => 0,
                    'imagealt' => '',
                    'mnethostid' => 1,
                    'lang' => 'en',
                    'calendartype' => 'gregorian',
                ]);

                // Re-fetch user
                $moodleUser = DB::connection('moodle')->table('user')->where('id', $userId)->first();

                // UPDATE Portal user dengan moodle_user_id (HYBRID SYNC)
                $user->update(['moodle_user_id' => $userId]);
                Log::info("User auto-created in Moodle with ID: $userId, Portal user updated");

                // AUTO-ASSIGN MOODLE ROLE based on Portal role
                $this->assignMoodleRole($user, $moodleUser->id);

            } elseif (!$user->moodle_user_id) {
                // User ada di Moodle tapi Portal belum punya link, update Portal
                $user->update(['moodle_user_id' => $moodleUser->id]);
                Log::info("Portal user linked to existing Moodle user ID: {$moodleUser->id}");
            }

            // Ensure role is assigned (even for existing users)
            $this->ensureMoodleRoleAssigned($user, $moodleUser->id);

            // 4. Generate Magic Key untuk SSO
            $key = Str::random(32);
            $validUntil = now()->addMinutes(10)->timestamp;

            DB::connection('moodle')->table('user_private_key')->insert([
                'script' => 'auth/userkey',
                'value' => $key,
                'userid' => $moodleUser->id,
                'instance' => null,
                'iprestriction' => null,
                'validuntil' => $validUntil,
                'timecreated' => now()->timestamp
            ]);

            // 5. Construct SSO URL
            $loginUrl = $this->moodleUrl . '/auth/userkey/login.php?key=' . $key;

            Log::info("SSO URL generated for user {$user->email} → Moodle user ID {$moodleUser->id}");

            // Log Moodle access
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'akses_lms',
                'entity_type' => 'Moodle',
                'entity_id' => $moodleUser->id,
                'changes' => null,
                'reason' => 'Akses LMS Moodle via SSO',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'login_url' => $loginUrl,
                'message' => 'SSO link generated successfully',
                'should_download_creds' => $user->moodle_creds_downloaded_at === null
            ]);

        } catch (\Exception $e) {
            Log::error("Hybrid SSO Error for {$user->email}: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat akses Moodle. Silakan coba lagi atau hubungi Admin.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign Moodle role based on Portal role
     */
    private function assignMoodleRole($portalUser, $moodleUserId)
    {
        // Get system context (context_level = 10)
        $systemContext = DB::connection('moodle')
            ->table('context')
            ->where('contextlevel', 10)
            ->first();

        if (!$systemContext) {
            Log::warning("System context not found in Moodle");
            return;
        }

        // Role mapping: Portal role → Moodle role
        $roleMap = [
            'super-admin' => 1,  // Manager (full system access)
            'admin' => 2,        // Course Creator (manage users + create courses, NOT manager)
            'instructor' => 3,   // Editing Teacher
            'learner' => 5,      // Student
        ];

        // Get Portal user's role
        $portalRole = $portalUser->getRoleNames()->first();
        $moodleRoleId = $roleMap[$portalRole] ?? 5; // Default: Student

        // Check if user already has ANY role assignment in system context
        $existingAssignment = DB::connection('moodle')
            ->table('role_assignments')
            ->where('userid', $moodleUserId)
            ->where('contextid', $systemContext->id)
            ->first();

        if ($existingAssignment) {
            // Update existing role if different
            if ($existingAssignment->roleid != $moodleRoleId) {
                DB::connection('moodle')
                    ->table('role_assignments')
                    ->where('id', $existingAssignment->id)
                    ->update([
                        'roleid' => $moodleRoleId,
                        'timemodified' => now()->timestamp,
                    ]);

                Log::info("Updated Moodle role from {$existingAssignment->roleid} to {$moodleRoleId} ({$portalRole}) for user {$moodleUserId}");
            }
        } else {
            // Create new role assignment
            DB::connection('moodle')->table('role_assignments')->insert([
                'roleid' => $moodleRoleId,
                'contextid' => $systemContext->id,
                'userid' => $moodleUserId,
                'timemodified' => now()->timestamp,
                'modifierid' => 2, // Admin
                'component' => ' ', // Oracle Moodle uses space, not NULL or empty string
                'itemid' => 0,
                'sortorder' => 0,
            ]);

            Log::info("Assigned Moodle role {$moodleRoleId} ({$portalRole}) to user {$moodleUserId}");
        }

        // If super-admin, add to siteadmins config
        if ($portalRole === 'super-admin') {
            $this->addToSiteAdmins($moodleUserId);
        }
    }

    /**
     * Ensure existing user has correct role
     */
    private function ensureMoodleRoleAssigned($portalUser, $moodleUserId)
    {
        $this->assignMoodleRole($portalUser, $moodleUserId);
    }

    /**
     * Add user to Moodle Site Administrators
     */
    private function addToSiteAdmins($moodleUserId)
    {
        // Get current siteadmins config
        $config = DB::connection('moodle')
            ->table('config')
            ->where('name', 'siteadmins')
            ->first();

        if (!$config) {
            // Create if not exists
            DB::connection('moodle')->table('config')->insert([
                'name' => 'siteadmins',
                'value' => (string)$moodleUserId,
            ]);
            Log::info("Created siteadmins config with user {$moodleUserId}");
        } else {
            $adminIds = array_filter(explode(',', $config->value));

            if (!in_array($moodleUserId, $adminIds)) {
                $adminIds[] = $moodleUserId;
                $newValue = implode(',', $adminIds);

                DB::connection('moodle')
                    ->table('config')
                    ->where('name', 'siteadmins')
                    ->update(['value' => $newValue]);

                Log::info("Added user {$moodleUserId} to siteadmins: {$newValue}");
            }
        }
    }
    /**
     * Regenerate Moodle Credentials (Password Reset) & Return PDF/HTML
     * POST /api/moodle/credentials
     */
    public function regenerateCredentials(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            // 1. Get/Create Moodle User
            // Reuse logic from getLoginUrl (simplified)
            $email = strtolower($user->email);
            $username = strtolower(str_replace(['@', '.', ' '], ['_', '_', '_'], explode('@', $email)[0]));
            
            $moodleUser = DB::connection('moodle')->table('user')
                ->where('email', $email)
                ->where('deleted', 0)
                ->first();

            if (!$moodleUser) {
                // Auto create if not exists
                $response = $this->getLoginUrl($request); 
                if ($response->status() !== 200) {
                    throw new \Exception("Gagal membuat user Moodle otomatis.");
                }
                // Fetch again
                $moodleUser = DB::connection('moodle')->table('user')
                    ->where('email', $email)
                    ->where('deleted', 0)
                    ->first();
            }

            // 2. Generate New Password
            // Format: Pln-{Random4}-{Random4}!
            $randomPart = Str::upper(Str::random(4));
            $newPassword = "Pln-{$randomPart}!";
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

            // 3. Update Moodle Password
            DB::connection('moodle')->table('user')
                ->where('id', $moodleUser->id)
                ->update([
                    'password' => $hashedPassword,
                    'timemodified' => now()->timestamp
                ]);

            // 4. Log Audit
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'regenerate_credentials',
                'entity_type' => 'Moodle',
                'entity_id' => $moodleUser->id,
                'changes' => json_encode(['username' => $moodleUser->username]),
                'reason' => 'User meminta reset password Moodle manual',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // 5. Generate HTML Content for "Surat Akses"
            $htmlContent = $this->generateCredentialLetter($user, $moodleUser->username, $newPassword);

            // 6. Update Download Timestamp
            $user->update(['moodle_creds_downloaded_at' => now()]);

            return response()->json([
                'success' => true,
                'username' => $moodleUser->username,
                'password' => $newPassword,
                'moodle_url' => $this->moodleUrl,
                'html_content' => $htmlContent,
                'filename' => 'Akun_LMS_PLNIP_' . Str::slug($user->name) . '.html'
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to regenerate credentials for {$user->email}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses kredensial Moodle.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function generateCredentialLetter($user, $username, $password)
    {
        $date = now()->translatedFormat('d F Y');
        $moodleUrl = $this->moodleUrl;
        
        return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Akun Akses LMS - PLN Indonesia Power</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 40px auto; padding: 20px; border: 1px solid #ddd; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .header { text-align: center; border-bottom: 2px solid #005e6a; padding-bottom: 20px; margin-bottom: 30px; }
        .logo { font-size: 24px; font-weight: bold; color: #008C99; text-transform: uppercase; }
        .title { font-size: 20px; margin-top: 10px; font-weight: 600; }
        .content { margin-bottom: 30px; }
        .credentials-box { background: #f0f9fa; border: 1px solid #bce3e6; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .credential-item { margin-bottom: 10px; font-size: 16px; }
        .label { font-weight: bold; width: 120px; display: inline-block; color: #555; }
        .value { font-family: 'Courier New', monospace; font-weight: bold; color: #000; font-size: 18px; background: #fff; padding: 2px 6px; border-radius: 4px; border: 1px solid #ddd; }
        .footer { text-align: center; font-size: 12px; color: #888; margin-top: 50px; border-top: 1px solid #eee; padding-top: 20px; }
        .btn { display: inline-block; background: #008C99; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 20px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">PLN INDONESIA POWER</div>
        <div class="title">Kartu Akses Learning Management System</div>
    </div>

    <div class="content">
        <p>Halo, <strong>{$user->name}</strong></p>
        <p>Berikut adalah detail akun Anda untuk mengakses <strong>PLN IP Learning Hub (LMS Moodle)</strong> secara langsung.</p>
        <p>Silakan gunakan kredensial di bawah ini untuk Masuk (Login) melalui halaman LMS.</p>

        <div class="credentials-box">
            <div class="credential-item">
                <span class="label">URL LMS:</span>
                <a href="{$moodleUrl}" target="_blank">{$moodleUrl}</a>
            </div>
            <div class="credential-item">
                <span class="label">Username:</span>
                <span class="value">{$username}</span>
            </div>
            <div class="credential-item">
                <span class="label">Password:</span>
                <span class="value">{$password}</span>
            </div>
        </div>

        <p><em>Catatan: Demi keamanan, mohon segera ganti password Anda setelah berhasil login pertama kali.</em></p>
        
        <div style="text-align: center;">
            <a href="{$moodleUrl}" class="btn">Buka LMS Moodle Sekarang</a>
        </div>
    </div>

    <div class="footer">
        <p>Dokumen ini digenerate secara otomatis oleh sistem PLN IP Learning Hub pada {$date}.</p>
        <p>&copy; 2026 PT PLN Indonesia Power. All rights reserved.</p>
    </div>
    
    <script>
        window.print();
    </script>
</body>
</html>
HTML;
    }
}
