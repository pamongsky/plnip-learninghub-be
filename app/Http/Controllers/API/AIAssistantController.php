<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Course;
use App\Models\AiConversation;

class AIAssistantController extends Controller
{
    /**
     * Get context for AI assistant (menu structure, features, etc)
     * NO CODE ACCESS - only UI structure
     */
    public function getContext(Request $request): JsonResponse
    {
        $user = $request->user();

        // Build safe context - NO CODE, only menu & features
        $context = [
            'user_info' => [
                'name' => $user->name,
                'role' => $user->role,
                'employee_id' => $user->employee_id ?? null,
            ],
            'available_features' => $this->getAvailableFeatures($user),
            'navigation_menu' => $this->getNavigationMenu($user),
            'quick_actions' => $this->getQuickActions($user),
        ];

        return response()->json([
            'success' => true,
            'data' => $context,
        ]);
    }

    /**
     * Chat with AI assistant
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'conversation_id' => 'nullable|string',
            'course_id' => 'nullable|integer', // Optional: if asking about specific course
        ]);

        $user = $request->user();

        // Generate conversation ID if not provided
        $conversationId = $validated['conversation_id'] ?? 'conv-' . uniqid() . time();

        Log::info('AI Chat Request', [
            'user_id' => $user->id,
            'message' => $validated['message'],
            'conversation_id' => $conversationId,
        ]);

        try {
            // Save user message
            AiConversation::create([
                'conversation_id' => $conversationId,
                'user_id' => $user->id,
                'message' => $validated['message'],
                'role' => 'user',
            ]);

            // Get conversation history (last 10 messages)
            $history = AiConversation::getHistory($conversationId, 10);

            // Get user context
            $context = $this->buildUserContext($user);

            Log::info('User context built', ['features_count' => count($context['available_features'])]);

            // Get user's enrolled courses for context
            $enrolledCourses = $this->getUserEnrolledCourses($user);
            $context['enrolled_courses'] = $enrolledCourses;

            // If asking about course content, include it
            $courseContext = null;

            // Check if course_id is explicitly provided
            if (isset($validated['course_id'])) {
                $course = Course::where('id', $validated['course_id'])
                    ->whereHas('enrollments', function($query) use ($user) {
                        $query->where('user_id', $user->id);
                    })
                    ->first();

                if ($course) {
                    $courseContext = $this->getMoodleCourseContent($course);
                }
            } else {
                // Auto-detect: Check if user is asking about learning materials
                $courseContext = $this->autoDetectAndFetchCourseContent($validated['message'], $user, $enrolledCourses);
            }

            // Call Gemini API with history
            $response = $this->callGeminiAPI($validated['message'], $context, $courseContext, $history);

            // Save assistant response
            AiConversation::create([
                'conversation_id' => $conversationId,
                'user_id' => $user->id,
                'message' => $response,
                'role' => 'assistant',
            ]);

            Log::info('Gemini API response received', ['response_length' => strlen($response)]);

            return response()->json([
                'success' => true,
                'data' => [
                    'response' => $response,
                    'conversation_id' => $conversationId,
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('AI Assistant error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Maaf, AI assistant sedang mengalami gangguan. Silakan coba lagi.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get course content for AI to help with learning
     */
    public function getCourseContent(Request $request, int $courseId): JsonResponse
    {
        $user = $request->user();

        // Check if user enrolled in course
        $course = Course::where('id', $courseId)
            ->whereHas('enrollments', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->first();

        if (!$course) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak terdaftar di kelas ini',
            ], 403);
        }

        // Get course content from Moodle
        $content = $this->getMoodleCourseContent($course);

        return response()->json([
            'success' => true,
            'data' => $content,
        ]);
    }

    /**
     * Build safe user context (NO CODE ACCESS)
     */
    private function buildUserContext(User $user): array
    {
        return [
            'user_role' => $user->role,
            'platform_name' => 'PLN IP Learning Hub',
            'available_features' => $this->getAvailableFeatures($user),
            'navigation' => $this->getNavigationMenu($user),
            'landing_page_info' => $this->getLandingPageInfo(),
            'guidelines' => [
                // General AI capabilities
                'Anda adalah AI assistant UMUM yang bisa menjawab SEMUA pertanyaan seperti Gemini/ChatGPT biasa',
                'Jawab pertanyaan apapun: matematika, sains, sejarah, programming, bahasa, dll',
                'Bantu perhitungan, penjelasan konsep, terjemahan, penulisan, dll',

                // Platform specific
                'TAMBAHAN: Anda juga memahami platform PLN IP Learning Hub',
                'Bantu user navigasi fitur platform jika ditanya',
                'Bantu jelaskan materi pembelajaran dari Moodle jika tersedia',
                'Bantu user memahami quiz/tugas (jelaskan konsep, JANGAN kasih jawaban langsung)',

                // Language
                'Jawab dalam bahasa yang sama dengan pertanyaan user (Indonesia/English)',
                'Gunakan format markdown untuk response yang rapi',

                // Restrictions
                'JANGAN tampilkan source code atau database internal',
                'Untuk masalah teknis platform, sarankan buat support ticket',
            ],
        ];
    }

    /**
     * Get landing page and public features info
     */
    private function getLandingPageInfo(): array
    {
        return [
            'public_pages' => [
                [
                    'name' => 'Landing Page',
                    'path' => '/',
                    'description' => 'Halaman utama PLN IP Learning Hub',
                    'sections' => ['Hero banner', 'Fitur unggulan', 'Kelas populer', 'Statistik', 'Testimonial'],
                ],
                [
                    'name' => 'Login',
                    'path' => '/login',
                    'description' => 'Halaman login untuk masuk ke sistem',
                    'how_to' => [
                        'Buka halaman /login atau klik tombol "Masuk" di pojok kanan atas',
                        'Masukkan email/NIP dan password',
                        'Klik tombol "Masuk"',
                        'Jika lupa password, klik "Lupa Password?"',
                    ],
                ],
                [
                    'name' => 'Register',
                    'path' => '/register',
                    'description' => 'Halaman pendaftaran akun baru',
                    'how_to' => [
                        'Buka halaman /register atau klik "Daftar" di landing page',
                        'Isi form: Nama, Email, NIP, Password',
                        'Klik "Daftar"',
                        'Cek email untuk verifikasi (jika ada)',
                    ],
                ],
            ],
            'auth_info' => [
                'Sistem mendukung login dengan email/NIP',
                'Password minimal 8 karakter',
                'Ada fitur "Ingat Saya" untuk login otomatis',
                'Session timeout setelah 2 jam tidak aktif',
            ],
        ];
    }

    /**
     * Get user's enrolled courses
     */
    private function getUserEnrolledCourses(User $user): array
    {
        $courses = Course::whereHas('enrollments', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->get(['id', 'title', 'description', 'moodle_course_id']);

        return $courses->map(function($course) {
            return [
                'id' => $course->id,
                'name' => $course->title, // Course model uses 'title' not 'name'
                'description' => substr(strip_tags($course->description ?? ''), 0, 200),
                'moodle_id' => $course->moodle_course_id,
            ];
        })->toArray();
    }

    /**
     * Auto-detect if user is asking about course material and fetch it
     */
    private function autoDetectAndFetchCourseContent(string $message, User $user, array $enrolledCourses): ?array
    {
        // Keywords that indicate user is asking about learning materials
        $materialKeywords = [
            'materi', 'modul', 'bab', 'topik', 'pelajaran', 'pembelajaran',
            'jelaskan', 'explain', 'ajarkan', 'tolong jelaskan',
            'pdf', 'dokumen', 'file', 'slide',
            'quiz', 'kuis', 'soal', 'ujian', 'tugas', 'assignment',
            'video', 'rekaman',
        ];

        $messageLower = strtolower($message);
        $isAskingAboutMaterial = false;

        foreach ($materialKeywords as $keyword) {
            if (str_contains($messageLower, $keyword)) {
                $isAskingAboutMaterial = true;
                break;
            }
        }

        if (!$isAskingAboutMaterial) {
            return null;
        }

        Log::info('User is asking about learning material, searching courses...');

        // Try to match course name from user message
        $matchedCourse = null;
        $highestScore = 0;

        foreach ($enrolledCourses as $course) {
            $courseName = strtolower($course['name']);
            $courseWords = explode(' ', $courseName);

            $score = 0;
            foreach ($courseWords as $word) {
                if (strlen($word) > 3 && str_contains($messageLower, $word)) {
                    $score++;
                }
            }

            if ($score > $highestScore) {
                $highestScore = $score;
                $matchedCourse = $course;
            }
        }

        // If we found a matching course, fetch its content
        if ($matchedCourse && $highestScore > 0) {
            Log::info('Matched course: ' . $matchedCourse['name']);

            $course = Course::find($matchedCourse['id']);
            if ($course) {
                return $this->getMoodleCourseContent($course);
            }
        }

        // If no specific match but user has only 1 course, use that
        if (count($enrolledCourses) === 1) {
            $course = Course::find($enrolledCourses[0]['id']);
            if ($course) {
                Log::info('Using single enrolled course: ' . $course->title);
                return $this->getMoodleCourseContent($course);
            }
        }

        return null;
    }

    /**
     * Get available features based on user role
     */
    private function getAvailableFeatures(User $user): array
    {
        $baseFeatures = [
            [
                'name' => 'Dashboard',
                'description' => 'Lihat ringkasan aktivitas belajar Anda',
                'path' => '/dashboard',
            ],
            [
                'name' => 'Kelas Saya',
                'description' => 'Akses kelas yang Anda ikuti',
                'path' => '/dashboard/classes',
            ],
            [
                'name' => 'Sertifikat',
                'description' => 'Lihat dan download sertifikat Anda',
                'path' => '/dashboard/certificates',
            ],
            [
                'name' => 'Support Ticket',
                'description' => 'Buat tiket bantuan untuk kendala teknis',
                'path' => '/dashboard/support',
                'how_to' => [
                    'Klik menu "Support" di sidebar',
                    'Klik tombol "Buat Tiket Baru"',
                    'Pilih kategori masalah (Teknis, Pembelajaran, Sertifikat, dll)',
                    'Isi subjek dan deskripsi masalah',
                    'Upload screenshot jika perlu',
                    'Klik "Kirim Tiket"',
                    'Tim support akan merespon dalam 1-2 jam kerja',
                ],
            ],
            [
                'name' => 'Pengumuman',
                'description' => 'Baca pengumuman terbaru dari admin',
                'path' => '/dashboard/announcements',
            ],
            [
                'name' => 'Profil',
                'description' => 'Edit profil dan ganti password',
                'path' => '/dashboard/profile',
            ],
        ];

        // Add role-specific features
        if ($user->hasRole(['instructor', 'Instructor'])) {
            $baseFeatures[] = [
                'name' => 'Kelola Kelas (Instructor)',
                'description' => 'Kelola kelas yang Anda ajar',
                'path' => '/instructor/classes',
            ];
        }

        if ($user->hasRole(['admin', 'Admin', 'super-admin', 'superadmin'])) {
            $baseFeatures[] = [
                'name' => 'User Management',
                'description' => 'Kelola pengguna sistem',
                'path' => '/admin/users',
            ];
            $baseFeatures[] = [
                'name' => 'Course Management',
                'description' => 'Kelola kelas dan materi',
                'path' => '/admin/courses',
            ];
        }

        return $baseFeatures;
    }

    /**
     * Get navigation menu structure
     */
    private function getNavigationMenu(User $user): array
    {
        if ($user->hasRole(['admin', 'Admin', 'super-admin', 'superadmin'])) {
            return [
                'Dashboard' => '/admin',
                'Users' => '/admin/users',
                'Courses' => '/admin/courses',
                'Support Tickets' => '/admin/support',
                'Announcements' => '/admin/announcements',
                'Reports' => '/admin/reports',
            ];
        }

        if ($user->hasRole(['instructor', 'Instructor'])) {
            return [
                'Dashboard' => '/instructor',
                'My Classes' => '/instructor/classes',
                'Messages' => '/instructor/messages',
                'Announcements' => '/instructor/announcements',
            ];
        }

        return [
            'Dashboard' => '/dashboard',
            'My Classes' => '/dashboard/classes',
            'Certificates' => '/dashboard/certificates',
            'Support' => '/dashboard/support',
            'Announcements' => '/dashboard/announcements',
        ];
    }

    /**
     * Get quick actions based on user role
     */
    private function getQuickActions(User $user): array
    {
        $actions = [
            [
                'label' => 'Buat Tiket Support',
                'path' => '/dashboard/support/create',
                'icon' => '🎫',
            ],
            [
                'label' => 'Lihat Pengumuman',
                'path' => '/dashboard/announcements',
                'icon' => '📢',
            ],
        ];

        if ($user->hasRole(['admin', 'Admin'])) {
            $actions[] = [
                'label' => 'Tambah User Baru',
                'path' => '/admin/users/create',
                'icon' => '👤',
            ];
        }

        return $actions;
    }

    /**
     * Call Gemini API with safe context
     */
    private function callGeminiAPI(string $userMessage, array $context, ?array $courseContext = null, $history = null): string
    {
        $apiKey = config('services.gemini.api_key');

        if (!$apiKey) {
            Log::error('Gemini API key not configured');
            throw new \Exception('Gemini API key not configured');
        }

        Log::info('Building Gemini prompt...');

        // Build system prompt with context - GENERAL AI + PLATFORM CONTEXT
        $systemPrompt = "Anda adalah AI assistant bernama 'PLN IP Assistant'.\n\n";
        $systemPrompt .= "## KEMAMPUAN UTAMA\n";
        $systemPrompt .= "Anda adalah AI UMUM yang bisa menjawab SEMUA jenis pertanyaan seperti Gemini atau ChatGPT:\n";
        $systemPrompt .= "- Matematika, fisika, kimia, dan perhitungan\n";
        $systemPrompt .= "- Kelistrikan, teknik, engineering\n";
        $systemPrompt .= "- Bahasa, penulisan, terjemahan\n";
        $systemPrompt .= "- Programming dan teknologi\n";
        $systemPrompt .= "- Sejarah, geografi, pengetahuan umum\n";
        $systemPrompt .= "- Dan topik lainnya\n\n";

        $systemPrompt .= "## KONTEKS PLATFORM\n";
        $systemPrompt .= "Anda juga memahami platform {$context['platform_name']} dan bisa membantu:\n";
        $systemPrompt .= "- Navigasi fitur dan menu\n";
        $systemPrompt .= "- Cara login, register, akses kelas\n";
        $systemPrompt .= "- Memahami materi pembelajaran dari Moodle\n";
        $systemPrompt .= "- Menjelaskan konsep dari quiz/tugas (tanpa memberikan jawaban langsung)\n\n";

        $systemPrompt .= "User saat ini: {$context['user_role']}\n\n";
        $systemPrompt .= "## FITUR PLATFORM\n";

        foreach ($context['available_features'] as $feature) {
            $systemPrompt .= "- {$feature['name']}: {$feature['description']}\n";
            if (isset($feature['how_to'])) {
                $systemPrompt .= "  Cara pakai:\n";
                foreach ($feature['how_to'] as $step) {
                    $systemPrompt .= "  * {$step}\n";
                }
            }
        }

        // Add user's enrolled courses
        if (!empty($context['enrolled_courses'])) {
            $systemPrompt .= "\n## KELAS YANG DIIKUTI USER\n";
            $systemPrompt .= "User terdaftar di kelas-kelas berikut:\n";
            foreach ($context['enrolled_courses'] as $course) {
                $systemPrompt .= "- **{$course['name']}**: {$course['description']}\n";
            }
            $systemPrompt .= "\nJika user bertanya tentang materi, cari dari kelas-kelas di atas.\n";
            $systemPrompt .= "Jika tidak yakin kelas mana, tanyakan ke user.\n";
        }

        // Add course content if provided
        if ($courseContext && !isset($courseContext['error'])) {
            $systemPrompt .= "\n=== MATERI PEMBELAJARAN ===\n";
            $systemPrompt .= "Kelas: {$courseContext['course_name']}\n";
            $systemPrompt .= "Deskripsi: {$courseContext['description']}\n\n";

            foreach ($courseContext['sections'] ?? [] as $section) {
                $systemPrompt .= "## {$section['name']}\n";
                if (!empty($section['summary'])) {
                    $systemPrompt .= "{$section['summary']}\n\n";
                }

                foreach ($section['modules'] ?? [] as $module) {
                    $systemPrompt .= "### {$module['name']}\n";
                    if (!empty($module['description'])) {
                        $systemPrompt .= "{$module['description']}\n";
                    }
                    if (!empty($module['content'])) {
                        // Limit content to avoid token overflow
                        $content = substr($module['content'], 0, 5000);
                        $systemPrompt .= "Konten:\n{$content}\n\n";
                    }
                }
            }

            $systemPrompt .= "Gunakan materi di atas untuk menjawab pertanyaan user tentang pembelajaran.\n";
        }

        // Add landing page info for navigation help
        if (isset($context['landing_page_info'])) {
            $systemPrompt .= "\n## HALAMAN PUBLIK (sebelum login)\n";
            foreach ($context['landing_page_info']['public_pages'] ?? [] as $page) {
                $systemPrompt .= "- {$page['name']} ({$page['path']}): {$page['description']}\n";
                if (isset($page['how_to'])) {
                    $systemPrompt .= "  Cara akses:\n";
                    foreach ($page['how_to'] as $step) {
                        $systemPrompt .= "  * {$step}\n";
                    }
                }
            }
        }

        $systemPrompt .= "\n## PANDUAN RESPONSE\n";
        $systemPrompt .= "- Jawab SEMUA pertanyaan dengan lengkap dan akurat\n";
        $systemPrompt .= "- Gunakan bahasa yang sama dengan user (Indonesia/English)\n";
        $systemPrompt .= "- Untuk pertanyaan umum: jawab seperti AI biasa\n";
        $systemPrompt .= "- Untuk pertanyaan platform: berikan panduan step-by-step\n";
        $systemPrompt .= "- Untuk materi pembelajaran: jelaskan konsep, berikan contoh\n";
        $systemPrompt .= "- Untuk quiz/tugas: jelaskan cara mengerjakan, JANGAN kasih jawaban langsung\n";
        $systemPrompt .= "- Gunakan format markdown untuk response yang rapi\n";
        $systemPrompt .= "- JANGAN bahas source code atau database internal\n";

        Log::info('Calling Gemini API...', ['prompt_length' => strlen($systemPrompt)]);

        // Build conversation contents with history
        $contents = [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $systemPrompt],
                ],
            ],
            [
                'role' => 'model',
                'parts' => [
                    ['text' => 'Halo! Saya PLN IP Assistant, siap membantu Anda dengan berbagai pertanyaan. Saya bisa membantu:\n\n1. **Pertanyaan Umum** - Matematika, sains, bahasa, programming, dll\n2. **Materi Pembelajaran** - Penjelasan konsep dari kelas Anda\n3. **Navigasi Platform** - Cara menggunakan fitur PLN IP Learning Hub\n4. **Quiz & Tugas** - Membantu memahami soal (tanpa memberikan jawaban langsung)\n\nSilakan tanyakan apa saja!'],
                ],
            ],
        ];

        // Add conversation history if exists
        if ($history && count($history) > 0) {
            foreach ($history as $msg) {
                $contents[] = [
                    'role' => $msg->role === 'user' ? 'user' : 'model',
                    'parts' => [
                        ['text' => $msg->message],
                    ],
                ];
            }
        }

        // Add current user message
        $contents[] = [
            'role' => 'user',
            'parts' => [
                ['text' => $userMessage],
            ],
        ];

        // Call Gemini API
        $response = Http::timeout(30)
            ->withoutVerifying() // Disable SSL verification for development
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}", [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 2048,
            ],
        ]);

        if (!$response->successful()) {
            Log::error('Gemini API error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new \Exception('Gemini API error: ' . $response->body());
        }

        $data = $response->json();

        Log::info('Gemini API raw response', ['data' => $data]);

        $aiResponse = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Maaf, tidak ada respons dari AI.';

        Log::info('Extracted AI response', ['response' => $aiResponse]);

        return $aiResponse;
    }

    /**
     * Get Moodle course content (for learning assistance)
     */
    private function getMoodleCourseContent(Course $course): array
    {
        $moodleUrl = config('services.moodle.url');
        $token = config('services.moodle.token');

        if (!$moodleUrl || !$token) {
            return [
                'course_name' => $course->title,
                'description' => $course->description,
                'error' => 'Moodle integration not configured',
            ];
        }

        try {
            // Get course contents from Moodle
            $response = Http::get("{$moodleUrl}/webservice/rest/server.php", [
                'wstoken' => $token,
                'wsfunction' => 'core_course_get_contents',
                'courseid' => $course->moodle_course_id,
                'moodlewsrestformat' => 'json',
            ]);

            if (!$response->successful()) {
                throw new \Exception('Moodle API error');
            }

            $sections = $response->json();

            // Extract readable content
            $content = [
                'course_name' => $course->title,
                'description' => strip_tags($course->description ?? ''),
                'instructor' => $course->instructor->name ?? null,
                'sections' => [],
                'resources' => [],
                'activities' => [],
            ];

            foreach ($sections as $section) {
                $sectionData = [
                    'name' => $section['name'] ?? 'Topic ' . ($section['section'] ?? ''),
                    'summary' => strip_tags($section['summary'] ?? ''),
                    'modules' => [],
                ];

                // Parse modules (resources, activities, etc.)
                foreach ($section['modules'] ?? [] as $module) {
                    $moduleData = [
                        'type' => $module['modname'] ?? 'unknown',
                        'name' => $module['name'] ?? '',
                        'description' => strip_tags($module['description'] ?? ''),
                    ];

                    // Extract content based on module type
                    switch ($module['modname']) {
                        case 'resource': // Files (PDF, DOC, etc)
                            if (isset($module['contents'][0]['fileurl'])) {
                                $fileUrl = $module['contents'][0]['fileurl'] . "&token={$token}";
                                $fileName = $module['contents'][0]['filename'] ?? '';

                                // Try to extract text from PDF
                                if (str_ends_with(strtolower($fileName), '.pdf')) {
                                    $pdfText = $this->extractPDFText($fileUrl);
                                    if ($pdfText) {
                                        $moduleData['content'] = $pdfText;
                                    }
                                }
                            }
                            $content['resources'][] = $moduleData;
                            break;

                        case 'page': // HTML pages
                            $moduleData['content'] = strip_tags($module['contents'][0]['content'] ?? '');
                            $content['resources'][] = $moduleData;
                            break;

                        case 'url': // External links
                            $moduleData['url'] = $module['contents'][0]['fileurl'] ?? '';
                            $content['resources'][] = $moduleData;
                            break;

                        case 'forum': // Forum discussions
                            $moduleData['type_label'] = 'Forum Diskusi';
                            $content['activities'][] = $moduleData;
                            break;

                        case 'quiz': // Quizzes
                            $moduleData['type_label'] = 'Kuis';
                            $content['activities'][] = $moduleData;
                            break;

                        case 'assign': // Assignments
                            $moduleData['type_label'] = 'Tugas';
                            $content['activities'][] = $moduleData;
                            break;
                    }

                    $sectionData['modules'][] = $moduleData;
                }

                $content['sections'][] = $sectionData;
            }

            return $content;

        } catch (\Exception $e) {
            Log::error('Failed to fetch Moodle content: ' . $e->getMessage());

            return [
                'course_name' => $course->title,
                'description' => $course->description,
                'error' => 'Failed to fetch course content',
            ];
        }
    }

    /**
     * Extract text from PDF file
     */
    private function extractPDFText(string $pdfUrl): ?string
    {
        try {
            Log::info('Extracting PDF from: ' . $pdfUrl);

            // Download PDF with timeout
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $pdfContent = @file_get_contents($pdfUrl, false, $context);

            if (!$pdfContent) {
                Log::warning('Failed to download PDF from: ' . $pdfUrl);
                return null;
            }

            $tempFile = tempnam(sys_get_temp_dir(), 'pdf_');
            file_put_contents($tempFile, $pdfContent);

            // Parse PDF with smalot/pdfparser
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($tempFile);

            // Get text from all pages
            $text = $pdf->getText();

            // Clean up the text
            $text = $this->cleanPdfText($text);

            // Cleanup temp file
            @unlink($tempFile);

            // Limit text length (Gemini has token limits ~32k)
            $maxLength = 15000; // Safe limit
            if (strlen($text) > $maxLength) {
                $text = substr($text, 0, $maxLength) . "\n\n[... teks dipotong karena terlalu panjang ...]";
            }

            Log::info('PDF extracted successfully', ['text_length' => strlen($text)]);

            return $text;

        } catch (\Exception $e) {
            Log::error('PDF extraction failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Clean PDF text for better readability
     */
    private function cleanPdfText(string $text): string
    {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Fix common PDF extraction issues
        $text = preg_replace('/([a-z])([A-Z])/', '$1 $2', $text); // Add space between camelCase
        $text = preg_replace('/(\d)([A-Za-z])/', '$1 $2', $text); // Add space between number and letter
        $text = preg_replace('/([A-Za-z])(\d)/', '$1 $2', $text); // Add space between letter and number

        // Add paragraph breaks at likely sentence endings
        $text = preg_replace('/\. ([A-Z])/', ".\n\n$1", $text);

        // Trim and clean
        $text = trim($text);

        return $text;
    }

    /**
     * Get course materials for AI context (called from chat)
     */
    public function getCourseMaterials(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => 'required|integer',
        ]);

        $user = $request->user();
        $course = Course::where('id', $validated['course_id'])
            ->whereHas('enrollments', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->first();

        if (!$course) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak terdaftar di kelas ini',
            ], 403);
        }

        $content = $this->getMoodleCourseContent($course);

        return response()->json([
            'success' => true,
            'data' => $content,
        ]);
    }
}
