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
     * Get ALL features across all roles so AI understands the entire platform
     */
    private function getAvailableFeatures(User $user): array
    {
        $features = [];

        // ===== USER/EMPLOYEE FEATURES =====
        $features[] = [
            'name' => 'Dashboard User',
            'description' => 'Ringkasan aktivitas belajar: kelas aktif, progress, pengumuman terbaru, sertifikat',
            'path' => '/dashboard',
            'roles' => ['employee', 'user'],
            'how_to' => ['Otomatis muncul setelah login sebagai user/employee'],
        ];
        $features[] = [
            'name' => 'Kelas Saya',
            'description' => 'Daftar kelas yang diikuti user. Bisa akses Moodle untuk belajar, lihat progress, lihat materi',
            'path' => '/dashboard/classes',
            'roles' => ['employee', 'user'],
            'how_to' => [
                'Klik menu "Kelas Saya" di sidebar',
                'Klik salah satu kelas untuk melihat detail',
                'Klik "Buka di Moodle" untuk masuk ke LMS dan belajar',
                'Progress otomatis terupdate dari Moodle',
            ],
        ];
        $features[] = [
            'name' => 'Sertifikat',
            'description' => 'Lihat dan download sertifikat yang sudah diterbitkan setelah menyelesaikan kelas',
            'path' => '/dashboard/certificates',
            'roles' => ['employee', 'user'],
            'how_to' => [
                'Klik menu "Sertifikat" di sidebar',
                'Sertifikat muncul setelah admin meng-upload sertifikat untuk kelas yang sudah selesai',
                'Klik "Download" untuk mengunduh file PDF sertifikat',
            ],
        ];
        $features[] = [
            'name' => 'Support Ticket (User)',
            'description' => 'Buat tiket bantuan untuk kendala teknis, pembelajaran, sertifikat, atau lainnya',
            'path' => '/dashboard/support',
            'roles' => ['employee', 'user'],
            'how_to' => [
                'Klik menu "Support Ticket" di sidebar',
                'Klik tombol "Buat Tiket Baru"',
                'Pilih kategori masalah',
                'Isi subjek dan deskripsi masalah dengan jelas',
                'Upload screenshot jika perlu',
                'Klik "Kirim Tiket"',
                'Admin/Super Admin akan merespon di halaman tiket',
            ],
        ];
        $features[] = [
            'name' => 'Profil',
            'description' => 'Edit profil, upload avatar, ganti password',
            'path' => '/dashboard/profile',
            'roles' => ['employee', 'user'],
            'how_to' => [
                'Klik menu "Profil" atau klik avatar di sidebar bawah',
                'Edit nama, email, department, posisi',
                'Upload foto profil (avatar)',
                'Ganti password di tab "Keamanan"',
            ],
        ];
        $features[] = [
            'name' => 'Pengumuman',
            'description' => 'Baca pengumuman dari admin, super admin, atau instructor',
            'path' => '/dashboard',
            'roles' => ['employee', 'user'],
        ];
        $features[] = [
            'name' => 'Chat / AI Assistant',
            'description' => 'Chat dengan AI untuk bertanya apapun: pertanyaan umum, navigasi platform, materi kelas, dll',
            'path' => 'Tombol chat di pojok kanan bawah',
            'roles' => ['all'],
            'how_to' => [
                'Klik ikon chat di pojok kanan bawah layar',
                'Ketik pertanyaan apapun',
                'AI bisa membantu: pertanyaan umum, cara pakai fitur, penjelasan materi, dll',
                'Riwayat chat tersimpan, bisa dilanjutkan nanti',
            ],
        ];
        $features[] = [
            'name' => 'Direct Message',
            'description' => 'Kirim pesan langsung ke user lain (instructor, admin, sesama peserta)',
            'path' => '/dashboard/messages atau /instructor/messages',
            'roles' => ['all'],
            'how_to' => [
                'Klik menu "Pesan" di sidebar',
                'Klik "Mulai Percakapan Baru"',
                'Cari user yang ingin diajak chat',
                'Ketik pesan dan kirim',
            ],
        ];

        // ===== INSTRUCTOR FEATURES =====
        $features[] = [
            'name' => 'Dashboard Instructor',
            'description' => 'Ringkasan: kelas yang diajar, jumlah peserta, statistik pertanyaan',
            'path' => '/instructor',
            'roles' => ['instructor'],
        ];
        $features[] = [
            'name' => 'Manajemen Kelas (Instructor)',
            'description' => 'Lihat detail kelas yang diajar, daftar peserta, progress peserta, upload sertifikat',
            'path' => '/instructor/classes',
            'roles' => ['instructor'],
            'how_to' => [
                'Klik menu "Manajemen Kelas" di sidebar',
                'Pilih kelas untuk melihat detail',
                'Tab "Peserta" menampilkan daftar peserta enrolled',
                'Klik 3-dot menu (⋮) di setiap peserta untuk: Lihat Progress, Upload Sertifikat',
                'Progress menampilkan: progress keseluruhan, aktivitas selesai, nilai, last access',
            ],
        ];
        $features[] = [
            'name' => 'Class Group Chat',
            'description' => 'Forum chat per kelas untuk diskusi antara instructor dan peserta',
            'path' => '/instructor/classes/[id] → tab Chat',
            'roles' => ['instructor', 'employee'],
            'how_to' => [
                'Buka detail kelas',
                'Klik tab "Chat Kelas"',
                'Kirim pesan untuk diskusi',
                'Instructor bisa menandai pertanyaan sebagai "Terjawab"',
            ],
        ];
        $features[] = [
            'name' => 'Pengumuman Instructor',
            'description' => 'Buat pengumuman khusus untuk peserta kelas yang diajar',
            'path' => '/instructor/announcements',
            'roles' => ['instructor'],
            'how_to' => [
                'Klik menu "Pengumuman" di sidebar',
                'Klik "Buat Pengumuman Baru"',
                'Isi judul, konten, dan pilih tingkat prioritas',
                'Pengumuman akan muncul di dashboard peserta',
            ],
        ];

        // ===== ADMIN FEATURES =====
        $features[] = [
            'name' => 'Dashboard Admin',
            'description' => 'Ringkasan: total user, kelas aktif, enrollment terbaru, statistik',
            'path' => '/admin',
            'roles' => ['admin'],
        ];
        $features[] = [
            'name' => 'Kelola User (Admin)',
            'description' => 'Lihat daftar user di department admin, read-only',
            'path' => '/admin/users',
            'roles' => ['admin'],
        ];
        $features[] = [
            'name' => 'Manajemen Kelas (Admin)',
            'description' => 'Kelola kelas: sync dari Moodle, enroll peserta, ubah role, lihat progress, upload sertifikat',
            'path' => '/admin/courses',
            'roles' => ['admin'],
            'how_to' => [
                'Klik menu "Manajemen Kelas" di sidebar',
                'Klik "Sync Moodle" untuk sinkronisasi kelas dari Moodle LMS',
                'Klik salah satu kelas untuk melihat detail',
                'Klik "Enroll Siswa" untuk mendaftarkan peserta baru (bisa multi-select)',
                'Pilih role Moodle saat enroll: Student, Editing Teacher, Non-Editing Teacher, Course Creator, Manager',
                'Klik 3-dot menu (⋮) di setiap peserta untuk: Ubah Role, Lihat Progress, Upload Sertifikat, Hapus Peserta',
                'Tab "Informasi Kelas" untuk edit detail kelas',
                'Upload sertifikat individual (PDF) atau bulk (ZIP)',
            ],
        ];
        $features[] = [
            'name' => 'Pengumuman Admin',
            'description' => 'Buat pengumuman untuk semua user di department admin',
            'path' => '/admin/announcements',
            'roles' => ['admin'],
        ];
        $features[] = [
            'name' => 'Support Ticket (Admin)',
            'description' => 'Kelola tiket bantuan dari user, reply, eskalasi ke super admin',
            'path' => '/admin/support',
            'roles' => ['admin'],
            'how_to' => [
                'Klik menu "Support Ticket" di sidebar',
                'Lihat daftar tiket yang masuk',
                'Klik tiket untuk melihat detail dan membalas',
                'Ubah status tiket (Open, In Progress, Resolved, Closed)',
                'Eskalasi ke Super Admin jika perlu bantuan lebih',
            ],
        ];
        $features[] = [
            'name' => 'Eskalasi ke Super Admin',
            'description' => 'Eskalasi tiket support yang tidak bisa diselesaikan ke super admin',
            'path' => '/admin/escalations',
            'roles' => ['admin'],
        ];

        // ===== SUPER ADMIN FEATURES =====
        $features[] = [
            'name' => 'Dashboard Super Admin',
            'description' => 'Ringkasan seluruh platform: total user, kelas, enrollment, statistik global',
            'path' => '/superadmin',
            'roles' => ['super-admin'],
        ];
        $features[] = [
            'name' => 'Kelola User (Super Admin)',
            'description' => 'CRUD user, override role, lihat audit history, sync dari ERP',
            'path' => '/superadmin/users',
            'roles' => ['super-admin'],
            'how_to' => [
                'Klik menu "Kelola User" di sidebar',
                'Tambah user baru, edit, atau hapus user',
                'Override role user (employee, instructor, admin, super-admin)',
                'Klik "Sync ERP" untuk sinkronisasi data user dari sistem ERP PLN IP',
                'Lihat audit history perubahan data user',
            ],
        ];
        $features[] = [
            'name' => 'Role & Permission (Super Admin)',
            'description' => 'Kelola role dan permission menggunakan Spatie Permission',
            'path' => '/superadmin/roles',
            'roles' => ['super-admin'],
        ];
        $features[] = [
            'name' => 'Pengumuman Super Admin',
            'description' => 'Buat pengumuman global untuk seluruh platform',
            'path' => '/superadmin/announcements',
            'roles' => ['super-admin'],
        ];
        $features[] = [
            'name' => 'Moodle Sync (Super Admin)',
            'description' => 'Sinkronisasi data users, courses, enrollments, categories dari/ke Moodle LMS',
            'path' => '/superadmin/moodle-sync',
            'roles' => ['super-admin'],
        ];
        $features[] = [
            'name' => 'CMS Landing Page',
            'description' => 'Kelola konten landing page: hero images, login background, pimpinan, partner',
            'path' => '/superadmin/home',
            'roles' => ['super-admin'],
        ];
        $features[] = [
            'name' => 'Kelola Pimpinan',
            'description' => 'CRUD data pimpinan PLN IP yang ditampilkan di landing page',
            'path' => '/superadmin/leaders',
            'roles' => ['super-admin'],
        ];
        $features[] = [
            'name' => 'Kelola Partner',
            'description' => 'CRUD data partner/mitra yang ditampilkan di landing page',
            'path' => '/superadmin/partners',
            'roles' => ['super-admin'],
        ];
        $features[] = [
            'name' => 'Eskalasi Tiket (Super Admin)',
            'description' => 'Tangani tiket yang di-eskalasi admin, reply dan resolve',
            'path' => '/superadmin/escalations',
            'roles' => ['super-admin'],
        ];
        $features[] = [
            'name' => 'Activity Log',
            'description' => 'Lihat log aktivitas seluruh user di platform',
            'path' => '/superadmin/activity-log',
            'roles' => ['super-admin'],
        ];

        return $features;
    }

    /**
     * Get navigation menu structure for ALL roles
     */
    private function getNavigationMenu(User $user): array
    {
        return [
            'user_employee' => [
                'Dashboard' => '/dashboard',
                'Kelas Saya' => '/dashboard/classes',
                'Sertifikat' => '/dashboard/certificates',
                'Support Ticket' => '/dashboard/support',
                'Profil' => '/dashboard/profile',
            ],
            'instructor' => [
                'Dashboard' => '/instructor',
                'Manajemen Kelas' => '/instructor/classes',
                'Pengumuman' => '/instructor/announcements',
                'Pesan' => '/instructor/messages',
            ],
            'admin' => [
                'Dashboard' => '/admin',
                'Kelola User' => '/admin/users',
                'Manajemen Kelas' => '/admin/courses',
                'Pengumuman' => '/admin/announcements',
                'Support Ticket' => '/admin/support',
                'Eskalasi' => '/admin/escalations',
            ],
            'super_admin' => [
                'Dashboard' => '/superadmin',
                'Kelola User' => '/superadmin/users',
                'Role & Permission' => '/superadmin/roles',
                'Pengumuman' => '/superadmin/announcements',
                'Moodle Sync' => '/superadmin/moodle-sync',
                'CMS Landing Page' => '/superadmin/home',
                'Pimpinan' => '/superadmin/leaders',
                'Partner' => '/superadmin/partners',
                'Eskalasi Tiket' => '/superadmin/escalations',
                'Activity Log' => '/superadmin/activity-log',
            ],
        ];
    }

    /**
     * Get quick actions based on user role
     */
    private function getQuickActions(User $user): array
    {
        $actions = [];

        if ($user->hasRole(['employee']) || !$user->hasRole(['admin', 'super-admin', 'instructor'])) {
            $actions[] = ['label' => 'Buat Tiket Support', 'path' => '/dashboard/support'];
            $actions[] = ['label' => 'Lihat Kelas Saya', 'path' => '/dashboard/classes'];
            $actions[] = ['label' => 'Lihat Sertifikat', 'path' => '/dashboard/certificates'];
        }

        if ($user->hasRole(['instructor'])) {
            $actions[] = ['label' => 'Lihat Kelas yang Diajar', 'path' => '/instructor/classes'];
            $actions[] = ['label' => 'Buat Pengumuman', 'path' => '/instructor/announcements'];
        }

        if ($user->hasRole(['admin'])) {
            $actions[] = ['label' => 'Kelola Kelas', 'path' => '/admin/courses'];
            $actions[] = ['label' => 'Kelola Support Ticket', 'path' => '/admin/support'];
        }

        if ($user->hasRole(['super-admin'])) {
            $actions[] = ['label' => 'Kelola User', 'path' => '/superadmin/users'];
            $actions[] = ['label' => 'Sync Moodle', 'path' => '/superadmin/moodle-sync'];
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
            Log::info('Fetching Moodle course content', [
                'course_id' => $course->id,
                'moodle_course_id' => $course->moodle_course_id,
                'moodle_url' => $moodleUrl,
            ]);

            // Get course contents from Moodle
            $response = Http::get("{$moodleUrl}/webservice/rest/server.php", [
                'wstoken' => $token,
                'wsfunction' => 'core_course_get_contents',
                'courseid' => $course->moodle_course_id,
                'moodlewsrestformat' => 'json',
            ]);

            Log::info('Moodle API response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
            ]);

            if (!$response->successful()) {
                Log::error('Moodle API failed', [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);
                throw new \Exception('Moodle API error: ' . $response->status());
            }

            $sections = $response->json();

            // Check if Moodle returned an error in the response body
            if (isset($sections['exception']) || isset($sections['errorcode'])) {
                Log::error('Moodle returned error in response', [
                    'error' => $sections['message'] ?? $sections['errorcode'] ?? 'Unknown error',
                ]);
                throw new \Exception('Moodle error: ' . ($sections['message'] ?? 'Unknown'));
            }

            Log::info('Moodle content fetched successfully', [
                'sections_count' => count($sections),
            ]);

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
                            $moduleData['type_label'] = 'File/Materi';
                            if (isset($module['contents'][0]['fileurl'])) {
                                $fileUrl = $module['contents'][0]['fileurl'] . "&token={$token}";
                                $fileName = $module['contents'][0]['filename'] ?? '';
                                $moduleData['file_name'] = $fileName;

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
                            $moduleData['type_label'] = 'Halaman';
                            $moduleData['content'] = strip_tags($module['contents'][0]['content'] ?? '');
                            $content['resources'][] = $moduleData;
                            break;

                        case 'url': // External links
                            $moduleData['type_label'] = 'Link';
                            $moduleData['url'] = $module['contents'][0]['fileurl'] ?? '';
                            $content['resources'][] = $moduleData;
                            break;

                        case 'label': // Inline HTML content
                            $moduleData['type_label'] = 'Label';
                            $moduleData['content'] = strip_tags($module['description'] ?? '');
                            $content['resources'][] = $moduleData;
                            break;

                        case 'folder': // Folder with files
                            $moduleData['type_label'] = 'Folder';
                            $fileNames = [];
                            $folderTexts = [];
                            foreach ($module['contents'] ?? [] as $file) {
                                $fname = $file['filename'] ?? 'unknown';
                                $fileNames[] = $fname;
                                // Extract PDFs inside folder
                                if (str_ends_with(strtolower($fname), '.pdf') && isset($file['fileurl'])) {
                                    $pdfText = $this->extractPDFText($file['fileurl'] . "&token={$token}");
                                    if ($pdfText) {
                                        $folderTexts[] = "--- {$fname} ---\n" . $pdfText;
                                    }
                                }
                            }
                            $moduleData['files'] = $fileNames;
                            if (!empty($folderTexts)) {
                                $moduleData['content'] = implode("\n\n", $folderTexts);
                            }
                            $content['resources'][] = $moduleData;
                            break;

                        case 'book': // Book chapters
                            $moduleData['type_label'] = 'Buku';
                            $bookContent = $this->getBookContent($module['instance'] ?? null, $moodleUrl, $token);
                            if ($bookContent) {
                                $moduleData['content'] = $bookContent;
                            }
                            $content['resources'][] = $moduleData;
                            break;

                        case 'lesson': // Lesson pages
                            $moduleData['type_label'] = 'Pelajaran';
                            $lessonContent = $this->getLessonContent($module['instance'] ?? null, $moodleUrl, $token);
                            if ($lessonContent) {
                                $moduleData['content'] = $lessonContent;
                            }
                            $content['resources'][] = $moduleData;
                            break;

                        case 'forum': // Forum discussions
                            $moduleData['type_label'] = 'Forum Diskusi';
                            $content['activities'][] = $moduleData;
                            break;

                        case 'assign': // Assignments - AI BISA baca deskripsi tugas
                            $moduleData['type_label'] = 'Tugas';
                            $assignContent = $this->getAssignmentContent($course->moodle_course_id, $module['instance'] ?? null, $moodleUrl, $token);
                            if ($assignContent) {
                                $moduleData['content'] = $assignContent;
                            }
                            $content['activities'][] = $moduleData;
                            break;

                        case 'quiz': // Quiz/Exam - AI TIDAK BOLEH baca soal
                            $moduleData['type_label'] = 'Kuis/Ujian';
                            $moduleData['note'] = 'Konten kuis/ujian tidak dapat dibaca AI untuk menjaga integritas ujian';
                            $content['activities'][] = $moduleData;
                            break;

                        case 'glossary':
                            $moduleData['type_label'] = 'Glosarium';
                            $content['activities'][] = $moduleData;
                            break;

                        case 'wiki':
                            $moduleData['type_label'] = 'Wiki';
                            $content['activities'][] = $moduleData;
                            break;

                        case 'workshop':
                            $moduleData['type_label'] = 'Workshop';
                            $content['activities'][] = $moduleData;
                            break;

                        case 'feedback':
                            $moduleData['type_label'] = 'Feedback';
                            $content['activities'][] = $moduleData;
                            break;

                        case 'choice':
                            $moduleData['type_label'] = 'Pilihan';
                            $content['activities'][] = $moduleData;
                            break;

                        case 'scorm':
                            $moduleData['type_label'] = 'SCORM';
                            $content['activities'][] = $moduleData;
                            break;

                        case 'h5pactivity':
                            $moduleData['type_label'] = 'H5P Interaktif';
                            $content['activities'][] = $moduleData;
                            break;

                        default: // Tipe lainnya
                            $moduleData['type_label'] = ucfirst($module['modname'] ?? 'Lainnya');
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
     * Fetch assignment content (deskripsi tugas) dari Moodle API
     */
    private function getAssignmentContent(int $moodleCourseId, ?int $instanceId, string $moodleUrl, string $token): ?string
    {
        if (!$instanceId) return null;

        try {
            $response = Http::withoutVerifying()->get("{$moodleUrl}/webservice/rest/server.php", [
                'wstoken' => $token,
                'wsfunction' => 'mod_assign_get_assignments',
                'courseids[0]' => $moodleCourseId,
                'moodlewsrestformat' => 'json',
            ]);

            if (!$response->successful()) return null;

            $data = $response->json();
            if (isset($data['exception'])) return null;

            foreach ($data['courses'] ?? [] as $course) {
                foreach ($course['assignments'] ?? [] as $assign) {
                    if ($assign['id'] == $instanceId) {
                        $intro = strip_tags($assign['intro'] ?? '');
                        // Juga ambil attachment files jika ada
                        $attachments = [];
                        foreach ($assign['introattachments'] ?? [] as $att) {
                            $attachments[] = $att['filename'] ?? '';
                            // Extract PDF attachments
                            if (str_ends_with(strtolower($att['filename'] ?? ''), '.pdf') && isset($att['fileurl'])) {
                                $pdfText = $this->extractPDFText($att['fileurl'] . "?token={$token}");
                                if ($pdfText) {
                                    $intro .= "\n\n--- Lampiran: {$att['filename']} ---\n" . $pdfText;
                                }
                            }
                        }
                        return $intro ?: null;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch assignment content: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Fetch book chapters content dari Moodle API
     */
    private function getBookContent(?int $instanceId, string $moodleUrl, string $token): ?string
    {
        if (!$instanceId) return null;

        try {
            $response = Http::withoutVerifying()->get("{$moodleUrl}/webservice/rest/server.php", [
                'wstoken' => $token,
                'wsfunction' => 'mod_book_get_books_by_courses',
                'courseids[0]' => 0, // Will use bookid below
                'moodlewsrestformat' => 'json',
            ]);

            // Alternative: get book content directly via book view
            // Use mod_book_view_book to get chapters, but that requires user context
            // Instead, get course contents which already includes book file data
            // Books typically have chapter content as sub-files

            // Fallback: try to get book intro at least
            if ($response->successful()) {
                $data = $response->json();
                if (!isset($data['exception'])) {
                    foreach ($data['books'] ?? [] as $book) {
                        if ($book['id'] == $instanceId) {
                            return strip_tags($book['intro'] ?? '');
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch book content: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Fetch lesson pages content dari Moodle API
     */
    private function getLessonContent(?int $instanceId, string $moodleUrl, string $token): ?string
    {
        if (!$instanceId) return null;

        try {
            $response = Http::withoutVerifying()->get("{$moodleUrl}/webservice/rest/server.php", [
                'wstoken' => $token,
                'wsfunction' => 'mod_lesson_get_pages',
                'lessonid' => $instanceId,
                'moodlewsrestformat' => 'json',
            ]);

            if (!$response->successful()) return null;

            $data = $response->json();
            if (isset($data['exception'])) return null;

            $pages = [];
            foreach ($data['pages'] ?? [] as $page) {
                $pageContent = strip_tags($page['page']['contents'] ?? '');
                if ($pageContent) {
                    $title = $page['page']['title'] ?? '';
                    $pages[] = ($title ? "## {$title}\n" : '') . $pageContent;
                }
            }

            return !empty($pages) ? implode("\n\n", $pages) : null;

        } catch (\Exception $e) {
            Log::warning('Failed to fetch lesson content: ' . $e->getMessage());
        }

        return null;
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

    /**
     * Get all conversation sessions for current user
     */
    public function getSessions(Request $request): JsonResponse
    {
        $user = $request->user();

        // Group by conversation_id, get the first user message as title and latest timestamp
        $sessions = AiConversation::where('user_id', $user->id)
            ->select('conversation_id')
            ->selectRaw('MIN(created_at) as started_at')
            ->selectRaw('MAX(created_at) as last_message_at')
            ->selectRaw('COUNT(*) as message_count')
            ->groupBy('conversation_id')
            ->orderByRaw('MAX(created_at) DESC')
            ->get();

        // Get first user message of each conversation as title
        $result = $sessions->map(function ($session) {
            $firstMessage = AiConversation::where('conversation_id', $session->conversation_id)
                ->where('role', 'user')
                ->orderBy('created_at', 'asc')
                ->first();

            return [
                'conversation_id' => $session->conversation_id,
                'title' => $firstMessage
                    ? \Illuminate\Support\Str::limit($firstMessage->message, 60)
                    : 'Percakapan Baru',
                'message_count' => $session->message_count,
                'started_at' => $session->started_at,
                'last_message_at' => $session->last_message_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Get conversation history for a specific session
     */
    public function getHistory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => 'required|string',
        ]);

        $user = $request->user();

        $messages = AiConversation::where('conversation_id', $validated['conversation_id'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'asc')
            ->get(['message', 'role', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => $messages->map(function ($msg) {
                return [
                    'role' => $msg->role,
                    'content' => $msg->message,
                    'timestamp' => $msg->created_at->toIso8601String(),
                ];
            }),
        ]);
    }

    /**
     * Delete a conversation session
     */
    public function deleteSession(Request $request, string $conversationId): JsonResponse
    {
        $user = $request->user();

        $deleted = AiConversation::where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "Percakapan dihapus ({$deleted} pesan)",
        ]);
    }
}
