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

            // If asking about course content, include it
            $courseContext = null;
            if (isset($validated['course_id'])) {
                $course = Course::where('id', $validated['course_id'])
                    ->whereHas('enrollments', function($query) use ($user) {
                        $query->where('user_id', $user->id);
                    })
                    ->first();

                if ($course) {
                    $courseContext = $this->getMoodleCourseContent($course);
                }
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
            'guidelines' => [
                'Bantu user dengan SEMUA pertanyaan terkait pembelajaran PLN IP (kelistrikan, teknik, perhitungan, dll)',
                'Jelaskan konsep teknis kelistrikan dengan detail, rumus, dan contoh praktis',
                'Bantu user navigasi dan menggunakan fitur platform',
                'Jawab dalam bahasa Indonesia yang mudah dipahami',
                'Gunakan rumus matematika dan perhitungan jika dibutuhkan',
                'Jika pertanyaan sangat spesifik/kompleks, sarankan buat support ticket atau tanya instructor',
                'JANGAN pernah tampilkan atau bahas source code',
                'JANGAN akses atau bahas database/API internal',
            ],
        ];
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

        // Build system prompt with context
        $systemPrompt = "Anda adalah AI assistant untuk {$context['platform_name']}.\n\n";
        $systemPrompt .= "Tugas Anda:\n";
        $systemPrompt .= "1. Membantu user memahami materi pembelajaran PLN IP (kelistrikan, teknik, perhitungan, dll)\n";
        $systemPrompt .= "2. Menjawab pertanyaan teknis dengan detail, rumus, dan contoh\n";
        $systemPrompt .= "3. Membantu navigasi dan penggunaan fitur platform\n\n";
        $systemPrompt .= "User role: {$context['user_role']}\n\n";
        $systemPrompt .= "Fitur yang tersedia:\n";

        foreach ($context['available_features'] as $feature) {
            $systemPrompt .= "- {$feature['name']}: {$feature['description']}\n";
            if (isset($feature['how_to'])) {
                $systemPrompt .= "  Cara pakai:\n";
                foreach ($feature['how_to'] as $step) {
                    $systemPrompt .= "  * {$step}\n";
                }
            }
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

        $systemPrompt .= "\nPanduan:\n";
        foreach ($context['guidelines'] as $guideline) {
            $systemPrompt .= "- {$guideline}\n";
        }

        $systemPrompt .= "\nJawab dengan lengkap, detail, dan ramah. Jika user tanya tentang fitur, jelaskan step-by-step.";

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
                    ['text' => 'Baik, saya siap membantu. Saya akan membantu dengan materi pembelajaran PLN IP (termasuk perhitungan kelistrikan, teknik, dll) dan juga navigasi platform.'],
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
                'course_name' => $course->name,
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
                'course_name' => $course->name,
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
                'course_name' => $course->name,
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
            // Download PDF temporarily
            $pdfContent = file_get_contents($pdfUrl);

            if (!$pdfContent) {
                return null;
            }

            $tempFile = tempnam(sys_get_temp_dir(), 'pdf_');
            file_put_contents($tempFile, $pdfContent);

            // Parse PDF
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($tempFile);
            $text = $pdf->getText();

            // Cleanup
            unlink($tempFile);

            // Limit text length (Gemini has token limits)
            return substr($text, 0, 10000); // First ~10k chars

        } catch (\Exception $e) {
            Log::error('PDF extraction failed: ' . $e->getMessage());
            return null;
        }
    }
}
