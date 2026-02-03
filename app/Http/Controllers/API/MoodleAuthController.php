<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
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
        $this->moodleUrl = rtrim(env('MOODLE_URL'), '/');
        $this->token = env('MOODLE_WS_TOKEN');
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

                Log::info("User auto-created in Moodle with ID: $userId");
            }

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

            return response()->json([
                'success' => true,
                'login_url' => $loginUrl,
                'message' => 'SSO link generated successfully'
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
}
