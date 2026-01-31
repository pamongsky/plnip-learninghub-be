<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class MoodleService
{
    protected string $url;
    protected string $token;

    public function __construct()
    {
        $this->url = rtrim(config('services.moodle.url', env('MOODLE_URL', '')), '/');
        $this->token = config('services.moodle.token', env('MOODLE_TOKEN', ''));
    }

    /**
     * Create a course in Moodle
     */
    public function createCourse(array $data)
    {
        if ($this->isMock()) {
            Log::info("MOCK: Creating Moodle Course", $data);
            return [
                'id' => rand(10000, 99999),
                'shortname' => $data['short_name'],
                'fullname' => $data['title']
            ];
        }

        $response = Http::post($this->url . '/webservice/rest/server.php', [
            'wstoken' => $this->token,
            'wsfunction' => 'core_course_create_courses',
            'moodlewsrestformat' => 'json',
            'courses' => [
                [
                    'fullname' => $data['title'],
                    'shortname' => $data['short_name'],
                    'categoryid' => $data['category_id'] ?? 1,
                    'startdate' => isset($data['start_date']) ? strtotime($data['start_date']) : time(),
                    'enddate' => isset($data['end_date']) ? strtotime($data['end_date']) : 0,
                    'summary' => $data['description'] ?? '',
                    'format' => 'topics',
                    'showgrades' => 1,
                    'newsitems' => 5,
                    'maxbytes' => 0,
                    'showreports' => 1,
                    'visible' => 1
                ]
            ]
        ]);

        if ($response->failed()) {
            Log::error("Moodle API Error: " . $response->body());
            throw new \Exception("Gagal menghubungi Moodle API");
        }

        $result = $response->json();

        if (isset($result['exception'])) {
            Log::error("Moodle Exception: " . json_encode($result));
            throw new \Exception("Moodle Error: " . $result['message']);
        }

        return $result[0]; // Returns array with id and shortname
    }

    /**
     * Get all courses from Moodle
     */
    public function getAllCourses()
    {
        if ($this->isMock()) {
            Log::info("MOCK: Fetching All Moodle Courses");
            return [
                [
                    'id' => 101,
                    'fullname' => 'Maintenance Turbin Gas (MOCK)',
                    'shortname' => 'gas-turbine-mock',
                    'categoryid' => 1,
                    'summary' => 'Deskripsi Mock Course',
                    'startdate' => time(),
                    'enddate' => time() + 86400 * 30,
                ],
                [
                    'id' => 102,
                    'fullname' => 'Safety Induction Level 1 (MOCK)',
                    'shortname' => 'safety-l1-mock',
                    'categoryid' => 1,
                    'summary' => 'Basic Safety for New Employees',
                    'startdate' => time(),
                    'enddate' => 0,
                ]
            ];
        }

        $response = Http::get($this->url . '/webservice/rest/server.php', [
            'wstoken' => $this->token,
            'wsfunction' => 'core_course_get_courses',
            'moodlewsrestformat' => 'json',
        ]);

        if ($response->failed()) {
            Log::error("Moodle API Error: " . $response->body());
            throw new \Exception("Gagal menghubungi Moodle API");
        }

        $result = $response->json();
        
        if (isset($result['exception'])) {
             // Handle specific case where token is invalid or function not allowed
             Log::error("Moodle API Exception: " . json_encode($result));
             throw new \Exception("Moodle API Error: " . $result['message']);
        }

        return $result;
    }

    /**
     * Enroll user to a course
     */
    public function enrollUser(int $moodleCourseId, User $user, int $roleId = 5)
    {
        if ($this->isMock()) {
            Log::info("MOCK: Enrolling User {$user->email} to Course ID {$moodleCourseId}");
            return true;
        }

        // 1. Check if user exists in Moodle, if not create
        $moodleUserId = $this->getOrCreateMoodleUser($user);

        // 2. Enroll
        $response = Http::post($this->url . '/webservice/rest/server.php', [
            'wstoken' => $this->token,
            'wsfunction' => 'enrol_manual_enrol_users',
            'moodlewsrestformat' => 'json',
            'enrolments' => [
                [
                    'roleid' => $roleId, // 5 = student
                    'userid' => $moodleUserId,
                    'courseid' => $moodleCourseId
                ]
            ]
        ]);

        if ($response->failed() || isset($response->json()['exception'])) {
            Log::error("Moodle Enroll Error: " . $response->body());
            throw new \Exception("Gagal mendaftarkan user ke Moodle");
        }

        return true;
    }

    private function getOrCreateMoodleUser(User $user)
    {
        if ($user->moodle_user_id) {
            return $user->moodle_user_id;
        }

        // Try to find by email
        $response = Http::get($this->url . '/webservice/rest/server.php', [
            'wstoken' => $this->token,
            'wsfunction' => 'core_user_get_users',
            'moodlewsrestformat' => 'json',
            'criteria' => [
                ['key' => 'email', 'value' => $user->email]
            ]
        ]);

        $users = $response->json();
        
        if (!empty($users['users'])) {
            $moodleId = $users['users'][0]['id'];
            $user->update(['moodle_user_id' => $moodleId]);
            return $moodleId;
        }

        // Create user
        $createResponse = Http::post($this->url . '/webservice/rest/server.php', [
            'wstoken' => $this->token,
            'wsfunction' => 'core_user_create_users',
            'moodlewsrestformat' => 'json',
            'users' => [
                [
                    'username' => strtolower(str_replace(' ', '', $user->name)) . rand(100,999), // Simple logic
                    'password' => 'Pln123!@#', // Default password
                    'firstname' => $user->name,
                    'lastname' => '(PLN IP)', // Placeholder
                    'email' => $user->email,
                    'auth' => 'manual',
                ]
            ]
        ]);

        $created = $createResponse->json();
        
        if (isset($created['exception'])) {
            throw new \Exception("Gagal membuat user Moodle: " . $created['message']);
        }

        $newId = $created[0]['id'];
        $user->update(['moodle_user_id' => $newId]);
        return $newId;
    }

    private function isMock(): bool
    {
        return empty($this->url) || empty($this->token);
    }
}
