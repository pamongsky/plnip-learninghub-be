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

        $username = strtolower(explode(' ', $user->name)[0]); // Simple logic
        // Or if we stored the mapped username, use that.
        // For 'fahmi', it matches.
        
        $email = strtolower($user->email);
        $firstname = explode(' ', $user->name)[0];
        $lastname = count(explode(' ', $user->name)) > 1 ? substr(strstr($user->name, " "), 1) : 'User';

        Log::info("Moodle SSO DirectDB Attempt for: $username");

        try {
            // New Strategy: DIRECT DATABASE ACCESS (Bypass API Block)
            
            // 1. Check if user exists in Moodle DB
            // Prioritize EMAIL check as it's more reliable/unique than guessed username
            $moodleUser = DB::connection('moodle')->table('user')
                ->where('email', $email)
                ->first();

            if (!$moodleUser) {
                // Determine if we should create? 
                // Creating via DB is risky (password hashes etc).
                // For now, fail gracefully.
                Log::warning("Moodle User not found in DB via Email: $email");
                return response()->json([
                    'success' => false, 
                    'message' => "User dengan email $email belum terdaftar di Moodle. Silakan hubungi Admin."
                ], 404);
            }

            // 2. Generate Key
            $key = Str::random(32);
            $validUntil = now()->addMinutes(10)->timestamp; // 10 minutes validity

            // 3. Insert Key into mdl_user_private_key
            // Note: Table prefix 'mdl_' is handled by config usually, 
            // but if config says 'mdl_', then `table('user_private_key')` becomes `mdl_user_private_key`.
            // Let's rely on config prefix.
            
            DB::connection('moodle')->table('user_private_key')->insert([
                'script' => 'auth/userkey',
                'value' => $key,
                'userid' => $moodleUser->id,
                'instance' => null,
                'iprestriction' => null,
                'validuntil' => $validUntil,
                'timecreated' => now()->timestamp
            ]);

            // 4. Construct URL
            $loginUrl = $this->moodleUrl . '/auth/userkey/login.php?key=' . $key;

            return response()->json([
                'success' => true,
                'login_url' => $loginUrl
            ]);

        } catch (\Exception $e) {
            Log::error("Moodle DirectDB Error: " . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Database Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
