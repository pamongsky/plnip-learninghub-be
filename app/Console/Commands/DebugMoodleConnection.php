<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class DebugMoodleConnection extends Command
{
    protected $signature = 'debug:moodle {username=fahmi}';
    protected $description = 'Diagnose Moodle API Connection and Permissions';

    public function handle()
    {
        $moodleUrl = rtrim(env('MOODLE_URL'), '/');
        $token = env('MOODLE_WS_TOKEN');
        $username = strtolower($this->argument('username'));

        $this->info("🔍 Starting Diagnostics for Moodle URL: $moodleUrl");
        $this->info("🔑 Token: " . substr($token, 0, 5) . '...');
        $this->info("👤 Target User: $username");
        $this->newLine();

        // 1. Test General Connectivity & Token Validity
        $this->info('👉 TEST 1: Checking Token Validity (core_webservice_get_site_info)...');
        $response1 = Http::withoutVerifying()->asForm()->post($moodleUrl . '/webservice/rest/server.php', [
            'wstoken' => $token,
            'wsfunction' => 'core_webservice_get_site_info',
            'moodlewsrestformat' => 'json'
        ]);

        if ($response1->successful() && !isset($response1->json()['exception'])) {
            $info = $response1->json();
            $this->info('✅ Test 1 PASSED! Token is valid.');
            $this->line('   Site Name: ' . ($info['sitename'] ?? 'Unknown'));
            $this->line('   Token Owner ID: ' . ($info['userid'] ?? 'Unknown'));
            $this->line('   Token Owner Name: ' . ($info['fullname'] ?? 'Unknown'));
            
            if (($info['userid'] ?? 0) != 2) {
                $this->warn('⚠️ WARNING: Token does not belong to Default Admin (ID 2). Check permissions!');
            }
        } else {
            $this->error('❌ Test 1 FAILED!');
            $this->dumpResponse($response1);
            return;
        }

        $this->newLine();

        // 2. Test User Existence & Read Permission
        $this->info("👉 TEST 2: Checking User Existence (core_user_get_users) for '$username'...");
        $response2 = Http::withoutVerifying()->asForm()->post($moodleUrl . '/webservice/rest/server.php', [
            'wstoken' => $token,
            'wsfunction' => 'core_user_get_users',
            'moodlewsrestformat' => 'json',
            'criteria' => [
                [
                    'key' => 'username',
                    'value' => $username
                ]
            ]
        ]);

        if ($response2->successful() && !isset($response2->json()['exception'])) {
            $users = $response2->json()['users'] ?? [];
            if (count($users) > 0) {
                $this->info('✅ Test 2 PASSED! User found in Moodle.');
                $this->line('   User ID: ' . $users[0]['id']);
                $this->line('   Email: ' . $users[0]['email']);
            } else {
                $this->warn('⚠️ Test 2 RESULT: User NOT FOUND, but API call worked.');
            }
        } else {
            $this->error('❌ Test 2 FAILED (Permission Issue?)');
            $this->dumpResponse($response2);
        }

        $this->newLine();

        // 3. Test SSO Key Generation (The Failing Part)
        $this->info("👉 TEST 3: Attempting SSO Key Generation (auth_userkey_request_login_url)...");
        $params = [
            'wstoken' => $token,
            'wsfunction' => 'auth_userkey_request_login_url',
            'moodlewsrestformat' => 'json',
            'user' => [
                'username' => $username,
                'email' => $username.'@plnip.local', // Dummy
                'firstname' => 'Test',
                'lastname' => 'Debug',
            ]
        ];
        
        $this->line('   Sending Params: ' . json_encode($params['user']));

        $response3 = Http::withoutVerifying()->asForm()->post($moodleUrl . '/webservice/rest/server.php', $params);

        if ($response3->successful() && !isset($response3->json()['exception']) && isset($response3->json()['loginurl'])) {
            $this->info('✅ Test 3 PASSED! LOGIN URL GENERATED SUCCESSFULLY (With Full Data)!');
            $this->line('   URL: ' . $response3->json()['loginurl']);
        } else {
            $this->error('❌ Test 3 FAILED! Trying Plan B...');
            // Test 4: Try with ID only (if user found in Test 2)
            if (isset($users) && count($users) > 0) {
                $userId = $users[0]['id'];
                $this->info("👉 TEST 4: Attempting SSO Key Generation using ONLY User ID ($userId)...");
                
                $paramsId = [
                    'wstoken' => $token,
                    'wsfunction' => 'auth_userkey_request_login_url',
                    'moodlewsrestformat' => 'json',
                    'user' => [
                        'id' => $userId 
                    ]
                ];
                
                $response4 = Http::withoutVerifying()->asForm()->post($moodleUrl . '/webservice/rest/server.php', $paramsId);
                
                if ($response4->successful() && !isset($response4->json()['exception']) && isset($response4->json()['loginurl'])) {
                    $this->info('✅ Test 4 PASSED! Success using User ID only!');
                    $this->line('   URL: ' . $response4->json()['loginurl']);
                } else {
                     $this->error('❌ Test 4 FAILED! Even ID-only approach failed.');
                     $this->dumpResponse($response4);
                }
            } else {
                $this->warn('⚠️ Skipping Test 4 because User was not found in Test 2.');
            }
        
            $this->dumpResponse($response3);
        }

        // Run Test 5
        $email = $username . '@plnip.local'; // Construct email or pass it
        $this->testDirectDB($username, $email);
    }

    private function dumpResponse($response)
    {
        $this->line('   Status Code: ' . $response->status());
        $this->line('   Headers: ' . json_encode($response->headers()));
        $this->line('   Body (Raw): ' . $response->body());
        
        $json = $response->json();
        if ($json) {
            $this->line('   Body (JSON Parsed): ' . json_encode($json, JSON_PRETTY_PRINT));
        }
    }

    // New Test 5 for Direct DB
    private function testDirectDB($username, $email) {
        $this->newLine();
        $this->info("👉 TEST 5: Direct Database Access (Oracle)...");
        
        try {
            // Test Connection
            DB::connection('moodle')->getPdo();
            $this->info('✅ Database Connection: SUCCESS');
            
            // Test User Query
            $this->info("   Searching for user by Email: $email");
            $user = DB::connection('moodle')->table('user')->where('email', $email)->first();
            
            if ($user) {
                $this->info('✅ User Found in DB!');
                $this->line('   ID: ' . $user->id);
                $this->line('   Username: ' . $user->username);
                $this->line('   Email: ' . $user->email);
                
                // Test Insert
                $this->info('   Attempting to insert dummy key...');
                DB::connection('moodle')->table('user_private_key')->insert([
                     'script' => 'auth/userkey',
                     'value' => 'DEBUG_TEST_KEY_'.time(),
                     'userid' => $user->id,
                     'validuntil' => time() + 300,
                     'timecreated' => time()
                ]);
                 $this->info('✅ Key Insertion: SUCCESS');
                 
            } else {
                $this->error("❌ User NOT FOUND in DB (Email: $email)");
                
                // Debug: List similar users?
                $similar = DB::connection('moodle')->table('user')
                    ->where('email', 'like', '%fahmi%')
                    ->orWhere('username', 'like', '%fahmi%')
                    ->get(['id', 'username', 'email']);
                
                if ($similar->count() > 0) {
                     $this->warn('   Found similar users:');
                     foreach($similar as $s) {
                         $this->line("   - ID: {$s->id}, User: {$s->username}, Email: {$s->email}");
                     }
                }
            }
            
        } catch (\Exception $e) {
            $this->error('❌ DB Connection/Query FAILED: ' . $e->getMessage());
        }
    }
}
