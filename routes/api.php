<?php

use App\Http\Controllers\API\AnnouncementController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ClassChatController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\DirectMessageController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\SupportTicketController;
use App\Http\Controllers\API\LandingPageController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health check endpoints (public, no auth)
Route::get('/health', [\App\Http\Controllers\API\HealthController::class, 'index']);
Route::get('/health/moodle', [\App\Http\Controllers\API\HealthController::class, 'moodle']);

// Public routes (no authentication required)
// Rate limited to prevent brute force attacks
Route::middleware(['throttle:10,1'])->group(function () {
    Route::post('/login', [AuthController::class, 'login']); // 10 attempts per minute
    Route::post('/register', [AuthController::class, 'register']);
});

Route::get('/landing-page', [LandingPageController::class, 'index']); // Public data

// Forgot Password (OTP-based) - Strict rate limiting
Route::middleware(['throttle:5,1'])->group(function () {
    Route::post('/forgot-password/request-otp', [\App\Http\Controllers\API\ForgotPasswordController::class, 'requestOTP']); // 5 attempts per minute
    Route::post('/forgot-password/verify-otp', [\App\Http\Controllers\API\ForgotPasswordController::class, 'verifyOTP']);
    Route::post('/forgot-password/reset', [\App\Http\Controllers\API\ForgotPasswordController::class, 'resetPassword']);
});

// Protected routes (requires Sanctum authentication)
Route::middleware('auth:sanctum')->group(function () {

    // Broadcasting authentication for real-time
    Broadcast::routes();

    // Authentication
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/auth/change-password-first-time', [AuthController::class, 'changePasswordFirstTime']);

    // =====================
    // AI ASSISTANT
    // =====================
    Route::prefix('ai-assistant')->group(function () {
        Route::get('/context', [\App\Http\Controllers\API\AIAssistantController::class, 'getContext']);
        Route::post('/chat', [\App\Http\Controllers\API\AIAssistantController::class, 'chat']);
        Route::get('/course/{courseId}/content', [\App\Http\Controllers\API\AIAssistantController::class, 'getCourseContent']);
        Route::get('/sessions', [\App\Http\Controllers\API\AIAssistantController::class, 'getSessions']);
        Route::get('/history', [\App\Http\Controllers\API\AIAssistantController::class, 'getHistory']);
        Route::delete('/sessions/{conversationId}', [\App\Http\Controllers\API\AIAssistantController::class, 'deleteSession']);
    });

    // =====================
    // COURSE LEARNING ASSISTANT (AI reads course materials)
    // =====================
    Route::prefix('course-assistant')->group(function () {
        Route::get('/{courseId}/structure', [\App\Http\Controllers\API\CourseLearningAssistantController::class, 'getCourseStructure']);
        Route::post('/read-material', [\App\Http\Controllers\API\CourseLearningAssistantController::class, 'readMaterial']);
        Route::post('/chat', [\App\Http\Controllers\API\CourseLearningAssistantController::class, 'chatAboutCourse']);
    });

    // Dashboard
    Route::get('/users', [\App\Http\Controllers\API\UserController::class, 'index']);
    Route::get('/users/all', [\App\Http\Controllers\API\UserController::class, 'getAllUsers']); // For admin view (read-only)
    Route::get('/dashboard/learner', [DashboardController::class, 'learnerDashboard']);
    Route::get('/dashboard/instructor', [DashboardController::class, 'instructorDashboard']);
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // =====================
    // USER MANAGEMENT (Super Admin)
    // =====================
    // Generic Super Admin Routes
    Route::middleware('role:super-admin')->group(function () {
        Route::post('/superadmin/sync-erp', [\App\Http\Controllers\API\UserController::class, 'syncWithERP']);
    });

    Route::prefix('superadmin/users')->middleware('role:super-admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\UserController::class, 'getAllUsers']);
        Route::post('/', [\App\Http\Controllers\API\UserController::class, 'store']);
        Route::post('/bulk', [\App\Http\Controllers\API\UserController::class, 'storeBulk']);
        Route::get('/{user}', [\App\Http\Controllers\API\UserController::class, 'show']);
        Route::put('/{user}', [\App\Http\Controllers\API\UserController::class, 'update']);
        Route::delete('/{user}', [\App\Http\Controllers\API\UserController::class, 'destroy']);
        Route::post('/{user}/override-role', [\App\Http\Controllers\API\UserController::class, 'overrideRole']);
        Route::get('/{user}/audit-history', [\App\Http\Controllers\API\UserController::class, 'auditHistory']);
    });

    // =====================
    // SUPER ADMIN ANNOUNCEMENTS
    // =====================
    Route::prefix('superadmin/announcements')->middleware('role:super-admin')->group(function () {
        Route::get('/', [AnnouncementController::class, 'superAdminIndex']);
        Route::get('/tracking', [AnnouncementController::class, 'getAnnouncementTracking']);
    });

    // =====================
    // ACTIVITY LOG (Super Admin Only)
    // =====================
    Route::prefix('activity-log')->middleware('role:super-admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\ActivityLogController::class, 'index']);
        Route::get('/stats', [\App\Http\Controllers\API\ActivityLogController::class, 'stats']);
        Route::get('/users/{user}', [\App\Http\Controllers\API\ActivityLogController::class, 'userLogs']);
    });

    // =====================
    // ROLES & PERMISSIONS (Super Admin Only)
    // =====================
    Route::prefix('superadmin/roles')->middleware('role:super-admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\RoleController::class, 'getAllRoles']);
        Route::get('/permissions/all', [\App\Http\Controllers\API\RoleController::class, 'getAllPermissions']);
        Route::post('/', [\App\Http\Controllers\API\RoleController::class, 'createRole']);
        Route::get('/{role}', [\App\Http\Controllers\API\RoleController::class, 'showRole']);
        Route::put('/{role}/permissions', [\App\Http\Controllers\API\RoleController::class, 'updateRolePermissions']);
        Route::delete('/{role}', [\App\Http\Controllers\API\RoleController::class, 'deleteRole']);
    });

    // PERMISSION MANAGEMENT (Super Admin Only - Real-time CRUD)
    // =====================
    Route::prefix('superadmin/permissions')->middleware('role:super-admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\PermissionController::class, 'index']); // List all
        Route::post('/', [\App\Http\Controllers\API\PermissionController::class, 'store']); // Create one
        Route::post('/bulk', [\App\Http\Controllers\API\PermissionController::class, 'bulkStore']); // Create many
        Route::post('/sync-standard', [\App\Http\Controllers\API\PermissionController::class, 'syncStandard']); // Sync standard permissions
        Route::get('/stats', [\App\Http\Controllers\API\PermissionController::class, 'stats']); // Stats
        Route::get('/{id}', [\App\Http\Controllers\API\PermissionController::class, 'show']); // View one
        Route::put('/{id}', [\App\Http\Controllers\API\PermissionController::class, 'update']); // Update
        Route::delete('/{id}', [\App\Http\Controllers\API\PermissionController::class, 'destroy']); // Delete
    });

    // =====================
    // ERP SYNC (Super Admin)
    // =====================
    Route::post('/superadmin/sync-erp', [\App\Http\Controllers\API\UserController::class, 'triggerERPSync'])
        ->middleware('role:super-admin');

    // Announcements
    Route::get('/announcements', [AnnouncementController::class, 'index']);
    Route::get('/announcements/latest', [AnnouncementController::class, 'latest']);
    Route::get('/announcements/{id}', [AnnouncementController::class, 'show']);

    // =====================
    // ANNOUNCEMENTS - Super Admin Only
    // =====================
    Route::prefix('superadmin/announcements')->middleware('role:super-admin')->group(function () {
        Route::get('/', [AnnouncementController::class, 'getAllAnnouncements']); // Tracking semua announcements
        Route::post('/', [AnnouncementController::class, 'createGlobalAnnouncement']); // Create global announcement
        Route::put('/{id}', [AnnouncementController::class, 'update']);
        Route::delete('/{id}', [AnnouncementController::class, 'destroy']);
        Route::get('/tracking', [AnnouncementController::class, 'getAnnouncementTracking']); // Analytics
    });

    // =====================
    // ANNOUNCEMENTS - Admin
    // =====================
    Route::prefix('admin/announcements')->middleware('role:admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\AdminAnnouncementController::class, 'index']); // Get all + mine
        Route::post('/', [\App\Http\Controllers\API\AdminAnnouncementController::class, 'store']); // Create announcement
        Route::put('/{id}', [\App\Http\Controllers\API\AdminAnnouncementController::class, 'update']); // Update
        Route::delete('/{id}', [\App\Http\Controllers\API\AdminAnnouncementController::class, 'destroy']); // Delete
    });

    // =====================
    // ANNOUNCEMENTS - Instructor
    // =====================
    Route::prefix('instructor/announcements')->middleware('role:instructor')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\InstructorAnnouncementController::class, 'index']); // Get all + mine
        Route::post('/', [\App\Http\Controllers\API\InstructorAnnouncementController::class, 'store']); // Create announcement
        Route::put('/{id}', [\App\Http\Controllers\API\InstructorAnnouncementController::class, 'update']); // Update
        Route::delete('/{id}', [\App\Http\Controllers\API\InstructorAnnouncementController::class, 'destroy']); // Delete
    });

    // User Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar']);
    Route::put('/profile/password', [ProfileController::class, 'changePassword']);

    // =====================
    // CLASS CHAT ROUTES
    // =====================
    Route::prefix('classes/{classId}/chat')->group(function () {
        Route::get('/', [ClassChatController::class, 'index']);
        Route::post('/', [ClassChatController::class, 'store']);
        Route::get('/new', [ClassChatController::class, 'getNewMessages']);
        Route::get('/questions', [ClassChatController::class, 'getQuestions']);
        Route::patch('/{messageId}/answered', [ClassChatController::class, 'markAsAnswered']);
    });

    // Instructor question stats
    Route::get('/instructor/question-stats', [ClassChatController::class, 'getQuestionStats']);

    // =====================
    // SUPPORT TICKET ROUTES
    // =====================
    Route::prefix('support')->group(function () {
        Route::get('/tickets/stats', [SupportTicketController::class, 'getStats']);
        Route::get('/tickets', [SupportTicketController::class, 'index']);
        Route::post('/tickets', [SupportTicketController::class, 'store']); // Create ticket
        Route::get('/tickets/{id}', [SupportTicketController::class, 'show']);
        Route::post('/tickets/{id}/reply', [SupportTicketController::class, 'addReply']);
        Route::patch('/tickets/{id}/status', [SupportTicketController::class, 'updateStatus']);
    });

    // =====================
    // DIRECT MESSAGE ROUTES
    // =====================
    Route::prefix('messages')->group(function () {
        // Conversations
        Route::get('/conversations', [DirectMessageController::class, 'getConversations']);
        Route::post('/conversations', [DirectMessageController::class, 'startConversation']);
        Route::get('/conversations/{id}', [DirectMessageController::class, 'getMessages']);
        Route::post('/conversations/{id}', [DirectMessageController::class, 'sendMessage']);
        Route::patch('/conversations/{id}/read', [DirectMessageController::class, 'markAsRead']);

        // Users & Search
        Route::get('/users', [DirectMessageController::class, 'getAvailableUsers']);
        Route::get('/search', [DirectMessageController::class, 'searchUsers']);

        // Stats & Utils
        Route::get('/unread', [DirectMessageController::class, 'getUnreadCount']);
        Route::get('/stats', [DirectMessageController::class, 'getStats']);
        Route::delete('/{messageId}', [DirectMessageController::class, 'deleteMessage']);
    });

    // =====================
    // ESCALATION TICKET ROUTES (Admin <-> Super Admin)
    // =====================
    Route::prefix('escalations')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\EscalationTicketController::class, 'index']);
        Route::get('/stats', [\App\Http\Controllers\API\EscalationTicketController::class, 'stats']);
        Route::post('/', [\App\Http\Controllers\API\EscalationTicketController::class, 'store']);
        Route::get('/check-support/{supportTicketId}', [\App\Http\Controllers\API\EscalationTicketController::class, 'checkBySupportTicket']);
        Route::get('/{escalationTicket}', [\App\Http\Controllers\API\EscalationTicketController::class, 'show']);
        Route::post('/{escalationTicket}/reply', [\App\Http\Controllers\API\EscalationTicketController::class, 'reply']);
        Route::patch('/{escalationTicket}/status', [\App\Http\Controllers\API\EscalationTicketController::class, 'updateStatus']);
    });

    // Escalate support ticket to Super Admin (Change param to id for manual finding)
    Route::post('/support/tickets/{id}/escalate', [\App\Http\Controllers\API\EscalationTicketController::class, 'escalate']);

    // =====================
    // LANDING PAGE CMS
    // =====================


    Route::middleware('auth:sanctum')->prefix('cms')->group(function () {
        // Settings
        Route::post('/settings', [LandingPageController::class, 'updateSettings']);
        Route::post('/settings/upload', [LandingPageController::class, 'uploadFile']);

        // Hero Images
        Route::post('/hero-images', [LandingPageController::class, 'storeHeroImage']);
        Route::delete('/hero-images/{heroImage}', [LandingPageController::class, 'deleteHeroImage']);

        // Login Background Images
        Route::post('/login-backgrounds', [LandingPageController::class, 'storeLoginBackground']);
        Route::delete('/login-backgrounds/{loginBackground}', [LandingPageController::class, 'deleteLoginBackground']);

        // Leaders
        Route::post('/leaders', [LandingPageController::class, 'storeLeader']);
        Route::post('/leaders/{leader}', [LandingPageController::class, 'updateLeader']); // Upd w/ file
        Route::delete('/leaders/{leader}', [LandingPageController::class, 'deleteLeader']);

        // Partners
        Route::post('/partners', [LandingPageController::class, 'storePartner']);
        Route::delete('/partners/{partner}', [LandingPageController::class, 'deletePartner']);
    });

    // =====================
    // COURSE MANAGEMENT ROUTES
    // =====================
    Route::prefix('courses')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\CourseController::class, 'index']);
        Route::post('/sync', [\App\Http\Controllers\API\CourseController::class, 'sync']);
        Route::get('/my', [\App\Http\Controllers\API\CourseController::class, 'myCourses']);
        Route::get('/teaching', [\App\Http\Controllers\API\CourseController::class, 'teachingCourses']);
        // Tracking HARUS di atas /{id} agar tidak kena match sebagai ID
        Route::get('/enrollments/tracking', [\App\Http\Controllers\API\CourseController::class, 'getEnrollmentTracking']);
        // Specific routes dulu baru dynamic {id}
        Route::get('/{id}', [\App\Http\Controllers\API\CourseController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\API\CourseController::class, 'update']);
        Route::post('/{id}/enroll', [\App\Http\Controllers\API\CourseController::class, 'enrollUser']);
        Route::delete('/{id}/enroll/{userId}', [\App\Http\Controllers\API\CourseController::class, 'unenrollUser']);
        Route::patch('/{id}/enroll/{userId}/role', [\App\Http\Controllers\API\CourseController::class, 'updateEnrollmentRole']);
        Route::get('/{id}/progress/{userId}', [\App\Http\Controllers\API\CourseController::class, 'getUserProgress']);
        Route::post('/{id}/upload-certificate/{userId}', [\App\Http\Controllers\API\CertificateController::class, 'uploadForUser'])->middleware('role:admin|super-admin');
        Route::post('/{id}/upload-certificates-zip', [\App\Http\Controllers\API\CertificateController::class, 'uploadBulkZip'])->middleware('role:admin|super-admin');
    });

    // =====================
    // CERTIFICATES (User)
    // =====================
    Route::prefix('certificates')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\CertificateController::class, 'index']);
        Route::get('/{id}/download', [\App\Http\Controllers\API\CertificateController::class, 'download']);
    });

    // Admin certificate management
    Route::prefix('admin/certificates')->middleware('role:admin|super-admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\CertificateController::class, 'getAllCertificates']);
        Route::patch('/{id}/revoke', [\App\Http\Controllers\API\CertificateController::class, 'revoke']);
    });

    // =====================
    // MOODLE SSO ROUTE
    // =====================
    Route::post('/moodle/login-url', [\App\Http\Controllers\API\MoodleAuthController::class, 'getLoginUrl']);
    Route::post('/moodle/credentials', [\App\Http\Controllers\API\MoodleAuthController::class, 'regenerateCredentials']);

    // =====================
    // MOODLE SYNC ROUTES (Super Admin & Admin)
    // =====================
    Route::prefix('moodle/sync')->middleware('role:super-admin|admin')->group(function () {
        Route::get('/status', [\App\Http\Controllers\API\MoodleSyncController::class, 'status']);
        Route::get('/history', [\App\Http\Controllers\API\MoodleSyncController::class, 'history']);
        Route::post('/full', [\App\Http\Controllers\API\MoodleSyncController::class, 'fullSync'])->middleware('role:super-admin');
        Route::post('/users', [\App\Http\Controllers\API\MoodleSyncController::class, 'syncUsers']);
        Route::post('/courses', [\App\Http\Controllers\API\MoodleSyncController::class, 'syncCourses']);
        Route::post('/enrollments', [\App\Http\Controllers\API\MoodleSyncController::class, 'syncEnrollments']);
        Route::post('/categories', [\App\Http\Controllers\API\MoodleSyncController::class, 'syncCategories']);
    });

    // =====================
    // AI CHAT ROUTES
    // =====================
    Route::get('/chat/sessions', [\App\Http\Controllers\API\ChatController::class, 'getSessions']);
    Route::put('/chat/sessions/{id}', [\App\Http\Controllers\API\ChatController::class, 'renameSession']);
    Route::delete('/chat/sessions/{id}', [\App\Http\Controllers\API\ChatController::class, 'deleteSession']);
    Route::get('/chat/history', [\App\Http\Controllers\API\ChatController::class, 'history']);
    Route::post('/chat', [\App\Http\Controllers\API\ChatController::class, 'chat']);
});
