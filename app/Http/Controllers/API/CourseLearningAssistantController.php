<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Course;

class CourseLearningAssistantController extends Controller
{
    /**
     * Get course structure with all materials
     * This gives AI context about what's available in the course
     */
    public function getCourseStructure(Request $request, int $courseId): JsonResponse
    {
        $user = $request->user();
        
        // Check enrollment
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

        $structure = $this->fetchMoodleCourseStructure($course);
        
        return response()->json([
            'success' => true,
            'data' => $structure,
        ]);
    }

    /**
     * Read specific material (PDF, HTML page, etc)
     * AI calls this when user asks about specific material
     */
    public function readMaterial(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => 'required|integer',
            'module_id' => 'required|integer',
            'module_type' => 'required|string', // 'resource', 'page', 'url'
        ]);

        $user = $request->user();
        
        // Check enrollment
        $course = Course::where('id', $validated['course_id'])
            ->whereHas('enrollments', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->first();
            
        if (!$course) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $content = $this->extractModuleContent(
            $course,
            $validated['module_id'],
            $validated['module_type']
        );
        
        return response()->json([
            'success' => true,
            'data' => $content,
        ]);
    }

    /**
     * Chat with AI about course materials
     * User asks questions, AI reads relevant materials and answers
     */
    public function chatAboutCourse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => 'required|integer',
            'message' => 'required|string|max:2000',
            'section_name' => 'nullable|string', // e.g., "Week 1", "Module 2"
        ]);

        $user = $request->user();
        
        // Check enrollment
        $course = Course::where('id', $validated['course_id'])
            ->whereHas('enrollments', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->first();
            
        if (!$course) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        try {
            // Get course structure
            $structure = $this->fetchMoodleCourseStructure($course);
            
            // Filter by section if specified
            $relevantContent = $structure;
            if (!empty($validated['section_name'])) {
                $relevantContent = $this->filterBySection($structure, $validated['section_name']);
            }
            
            // Build context for AI
            $context = $this->buildLearningContext($course, $relevantContent);
            
            // Call Gemini to answer
            $response = $this->askGemini($validated['message'], $context);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'response' => $response,
                    'course' => $course->name,
                    'section' => $validated['section_name'] ?? 'All sections',
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Course AI chat error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses pertanyaan. Silakan coba lagi.',
            ], 500);
        }
    }

    /**
     * Fetch complete course structure from Moodle
     */
    private function fetchMoodleCourseStructure(Course $course): array
    {
        $moodleUrl = config('services.moodle.url');
        $token = config('services.moodle.token');
        
        if (!$moodleUrl || !$token || !$course->moodle_course_id) {
            return ['error' => 'Moodle not configured'];
        }

        try {
            $response = Http::timeout(10)->get("{$moodleUrl}/webservice/rest/server.php", [
                'wstoken' => $token,
                'wsfunction' => 'core_course_get_contents',
                'courseid' => $course->moodle_course_id,
                'moodlewsrestformat' => 'json',
            ]);

            if (!$response->successful()) {
                throw new \Exception('Moodle API failed');
            }

            $sections = $response->json();
            
            return [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'moodle_course_id' => $course->moodle_course_id,
                'sections' => $this->parseSections($sections, $token),
            ];

        } catch (\Exception $e) {
            Log::error("Failed to fetch Moodle course structure: {$e->getMessage()}");
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Parse Moodle sections into structured format
     */
    private function parseSections(array $sections, string $token): array
    {
        $parsed = [];

        foreach ($sections as $section) {
            $sectionData = [
                'id' => $section['id'] ?? null,
                'name' => $section['name'] ?? "Topic {$section['section']}",
                'section_number' => $section['section'] ?? 0,
                'summary' => strip_tags($section['summary'] ?? ''),
                'modules' => [],
            ];

            foreach ($section['modules'] ?? [] as $module) {
                $moduleData = [
                    'id' => $module['id'] ?? null,
                    'type' => $module['modname'] ?? 'unknown',
                    'name' => $module['name'] ?? '',
                    'description' => strip_tags($module['description'] ?? ''),
                    'visible' => $module['visible'] ?? 1,
                ];

                // Add type-specific data
                switch ($module['modname']) {
                    case 'resource':
                        $moduleData['icon'] = '📄';
                        $moduleData['type_label'] = 'File/PDF';
                        if (isset($module['contents'][0])) {
                            $moduleData['file_url'] = $module['contents'][0]['fileurl'] ?? '';
                            $moduleData['filename'] = $module['contents'][0]['filename'] ?? '';
                            $moduleData['filesize'] = $module['contents'][0]['filesize'] ?? 0;
                            $moduleData['mimetype'] = $module['contents'][0]['mimetype'] ?? '';
                        }
                        break;

                    case 'page':
                        $moduleData['icon'] = '📝';
                        $moduleData['type_label'] = 'Halaman';
                        break;

                    case 'url':
                        $moduleData['icon'] = '🔗';
                        $moduleData['type_label'] = 'Link';
                        break;

                    case 'forum':
                        $moduleData['icon'] = '💬';
                        $moduleData['type_label'] = 'Forum';
                        break;

                    case 'quiz':
                        $moduleData['icon'] = '📝';
                        $moduleData['type_label'] = 'Kuis';
                        break;

                    case 'assign':
                        $moduleData['icon'] = '📋';
                        $moduleData['type_label'] = 'Tugas';
                        break;

                    default:
                        $moduleData['icon'] = '📌';
                        $moduleData['type_label'] = ucfirst($module['modname']);
                }

                $sectionData['modules'][] = $moduleData;
            }

            $parsed[] = $sectionData;
        }

        return $parsed;
    }

    /**
     * Extract content from specific module
     */
    private function extractModuleContent(Course $course, int $moduleId, string $moduleType): array
    {
        $moodleUrl = config('services.moodle.url');
        $token = config('services.moodle.token');

        try {
            // Get module details
            $structure = $this->fetchMoodleCourseStructure($course);
            $module = $this->findModule($structure, $moduleId);

            if (!$module) {
                return ['error' => 'Module not found'];
            }

            $content = [
                'module_id' => $moduleId,
                'name' => $module['name'],
                'type' => $module['type'],
                'content' => '',
            ];

            switch ($moduleType) {
                case 'resource':
                    // Download and extract PDF/file
                    if (isset($module['file_url'])) {
                        $fileUrl = $module['file_url'] . "&token={$token}";
                        $filename = $module['filename'] ?? '';

                        if (str_ends_with(strtolower($filename), '.pdf')) {
                            $content['content'] = $this->extractPDFContent($fileUrl);
                            $content['content_type'] = 'pdf_text';
                        }
                    }
                    break;

                case 'page':
                    // Get HTML page content
                    $pageContent = $this->getMoodlePageContent($course->moodle_course_id, $moduleId, $token);
                    $content['content'] = $pageContent;
                    $content['content_type'] = 'html_text';
                    break;
            }

            return $content;

        } catch (\Exception $e) {
            Log::error("Extract module content error: {$e->getMessage()}");
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Extract text from PDF file
     */
    private function extractPDFContent(string $pdfUrl): string
    {
        try {
            $pdfContent = file_get_contents($pdfUrl);
            
            if (!$pdfContent) {
                return "Failed to download PDF";
            }

            $tempFile = tempnam(sys_get_temp_dir(), 'pdf_');
            file_put_contents($tempFile, $pdfContent);

            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($tempFile);
            $text = $pdf->getText();

            unlink($tempFile);

            // Clean up text
            $text = preg_replace('/\s+/', ' ', $text);
            
            return trim($text);

        } catch (\Exception $e) {
            Log::error("PDF extraction error: {$e->getMessage()}");
            return "Failed to extract PDF content: " . $e->getMessage();
        }
    }

    /**
     * Get Moodle page content
     */
    private function getMoodlePageContent(int $courseId, int $moduleId, string $token): string
    {
        // This would call Moodle API to get page content
        // Implementation depends on Moodle API availability
        return "Page content extraction not yet implemented";
    }

    /**
     * Find specific module in structure
     */
    private function findModule(array $structure, int $moduleId): ?array
    {
        foreach ($structure['sections'] ?? [] as $section) {
            foreach ($section['modules'] as $module) {
                if ($module['id'] === $moduleId) {
                    return $module;
                }
            }
        }
        return null;
    }

    /**
     * Filter structure by section name
     */
    private function filterBySection(array $structure, string $sectionName): array
    {
        $filtered = $structure;
        $filtered['sections'] = array_filter($structure['sections'] ?? [], function($section) use ($sectionName) {
            return stripos($section['name'], $sectionName) !== false;
        });
        
        return $filtered;
    }

    /**
     * Build learning context for AI
     */
    private function buildLearningContext(Course $course, array $structure): string
    {
        $context = "MATERI PEMBELAJARAN\n";
        $context .= "==================\n";
        $context .= "Kelas: {$course->name}\n";
        $context .= "Deskripsi: " . strip_tags($course->description ?? '') . "\n\n";

        foreach ($structure['sections'] ?? [] as $section) {
            $context .= "## {$section['name']}\n";
            if (!empty($section['summary'])) {
                $context .= "{$section['summary']}\n";
            }
            
            $context .= "Materi:\n";
            foreach ($section['modules'] as $module) {
                $context .= "- {$module['icon']} {$module['name']} ({$module['type_label']})\n";
                if (!empty($module['description'])) {
                    $context .= "  " . substr($module['description'], 0, 200) . "...\n";
                }
            }
            $context .= "\n";
        }

        return $context;
    }

    /**
     * Ask Gemini with course context
     */
    private function askGemini(string $question, string $context): string
    {
        $apiKey = config('services.gemini.api_key');
        
        if (!$apiKey) {
            throw new \Exception('Gemini API key not configured');
        }

        $systemPrompt = "Anda adalah AI tutor untuk pembelajaran online.\n\n";
        $systemPrompt .= $context . "\n\n";
        $systemPrompt .= "Gunakan materi di atas untuk menjawab pertanyaan siswa.\n";
        $systemPrompt .= "Jelaskan dengan bahasa yang mudah dipahami.\n";
        $systemPrompt .= "Jika pertanyaan di luar materi yang tersedia, katakan dengan jelas.\n";

        $response = Http::timeout(30)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key={$apiKey}",
            [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [['text' => $systemPrompt]],
                    ],
                    [
                        'role' => 'model',
                        'parts' => [['text' => 'Baik, saya siap membantu menjelaskan materi pembelajaran.']],
                    ],
                    [
                        'role' => 'user',
                        'parts' => [['text' => $question]],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 2048,
                ],
            ]
        );

        if (!$response->successful()) {
            throw new \Exception('Gemini API error');
        }

        $data = $response->json();
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'No response';
    }
}
