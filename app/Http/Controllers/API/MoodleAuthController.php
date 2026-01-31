<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

        if (!$this->moodleUrl || !$this->token) {
            return response()->json([
                'success' => false,
                'message' => 'Moodle configuration missing in backend.'
            ], 500);
        }

        try {
            // Prepare parameters for auth_userkey_request_login_url
            $params = [
                'wstoken' => $this->token,
                'wsfunction' => 'auth_userkey_request_login_url',
                'moodlewsrestformat' => 'json',
                'user' => [
                    'username' => explode('@', $user->email)[0], // Use email prefix or employee_id as username
                    'email' => $user->email,
                    'firstname' => explode(' ', $user->name)[0],
                    'lastname' => count(explode(' ', $user->name)) > 1 ? substr(strstr($user->name, " "), 1) : '(Portal)',
                ]
            ];

            // Send request to Moodle
            // withoutVerifying() is crucial for localhost/self-signed certs
            $response = Http::withoutVerifying()->asForm()->post($this->moodleUrl . '/webservice/rest/server.php', $params);
            
            if (!$response->successful()) {
                Log::error('Moodle API Connection Failed. Status: '.$response->status().' Body: ' . $response->body());
                return response()->json(['success' => false, 'message' => 'Failed to connect to Moodle (Status: '.$response->status().')'], 502);
            }

            $data = $response->json();

            // Check for Moodle exceptions
            if (isset($data['exception'])) {
                Log::error('Moodle Exception: ' . json_encode($data));
                return response()->json([
                    'success' => false, 
                    'message' => 'Moodle Error: ' . ($data['message'] ?? 'Unknown error')
                ], 400);
            }

            if (!isset($data['loginurl'])) {
                Log::error('Moodle response missing loginurl: ' . json_encode($data));
                return response()->json(['success' => false, 'message' => 'Invalid response from Moodle'], 500);
            }

            return response()->json([
                'success' => true,
                'login_url' => $data['loginurl']
            ]);

        } catch (\Exception $e) {
            Log::error('Moodle Controller Exception: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Server error'], 500);
        }
    }
}
