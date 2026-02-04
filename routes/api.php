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

// Public routes (no authentication required)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::get('/landing-page', [LandingPageController::class, 'index']); // Public data

// Protected routes (requires Sanctum authentication)
Route::middleware('auth:sanctum')->group(function () {

    // Broadcasting authentication for real-time
    Broadcast::routes();

    // Authentication
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // =====================
    // AI ASSISTANT
    // =====================
    Route::prefix('ai-assistant')->group(function () {
        Route::get('/context', [\App\Http\Controllers\API\AIAssistantController::class, 'getContext']);
        Route::post('/chat', [\App\Http\Controllers\API\AIAssistantController::class, 'chat']);
        Route::get('/course/{courseId}/content', [\App\Http\Controllers\API\AIAssistantController::class, 'getCourseContent']);
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
    Route::get('/dashboard/employee', [DashboardController::class, 'employeeDashboard']);
    Route::get('/dashboard/instructor', [DashboardController::class, 'instructorDashboard']);
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // =====================
    // USER MANAGEMENT (Super Admin)
    // =====================
    Route::prefix('superadmin/users')->middleware('role:super-admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\UserController::class, 'getAllUsers']);
        Route::post('/', [\App\Http\Controllers\API\UserController::class, 'store']);
        Route::get('/{user}', [\App\Http\Controllers\API\UserController::class, 'show']);
        Route::put('/{user}', [\App\Http\Controllers\API\UserController::class, 'update']);
        Route::delete('/{user}', [\App\Http\Controllers\API\UserController::class, 'destroy']);
        Route::post('/{user}/override-role', [\App\Http\Controllers\API\UserController::class, 'overrideRole']);
        Route::get('/{user}/audit-history', [\App\Http\Controllers\API\UserController::class, 'auditHistory']);
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
        Route::get('/tracking', [AnnouncementController::class, 'getAnnouncementTracking']); // Analytics
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
        Route::get('/', [\App\Http\Controllers\Api\EscalationTicketController::class, 'index']);
        Route::get('/stats', [\App\Http\Controllers\Api\EscalationTicketController::class, 'stats']);
        Route::post('/', [\App\Http\Controllers\Api\EscalationTicketController::class, 'store']);
        Route::get('/check-support/{supportTicketId}', [\App\Http\Controllers\Api\EscalationTicketController::class, 'checkBySupportTicket']);
        Route::get('/{escalationTicket}', [\App\Http\Controllers\Api\EscalationTicketController::class, 'show']);
        Route::post('/{escalationTicket}/reply', [\App\Http\Controllers\Api\EscalationTicketController::class, 'reply']);
        Route::patch('/{escalationTicket}/status', [\App\Http\Controllers\Api\EscalationTicketController::class, 'updateStatus']);
    });

    // Escalate support ticket to Super Admin (Change param to id for manual finding)
    Route::post('/support/tickets/{id}/escalate', [\App\Http\Controllers\Api\EscalationTicketController::class, 'escalate']);

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
        Route::get('/', [\App\Http\Controllers\Api\CourseController::class, 'index']);
        Route::post('/sync', [\App\Http\Controllers\Api\CourseController::class, 'sync']);
        Route::get('/my', [\App\Http\Controllers\API\CourseController::class, 'myCourses']);
        // Tracking HARUS di atas /{id} agar tidak kena match sebagai ID
        Route::get('/enrollments/tracking', [\App\Http\Controllers\Api\CourseController::class, 'getEnrollmentTracking']);
        // Specific routes dulu baru dynamic {id}
        Route::get('/{id}', [\App\Http\Controllers\Api\CourseController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\Api\CourseController::class, 'update']);
        Route::post('/{id}/enroll', [\App\Http\Controllers\Api\CourseController::class, 'enrollUser']);
        Route::delete('/{id}/enroll/{userId}', [\App\Http\Controllers\Api\CourseController::class, 'unenrollUser']);
    });

    // =====================
    // CERTIFICATE TEMPLATE MANAGEMENT (Admin & Super Admin)
    // =====================
    Route::prefix('certificate-templates')->middleware('role:admin|super-admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\CertificateTemplateController::class, 'index']);
        Route::get('/variables', [\App\Http\Controllers\API\CertificateTemplateController::class, 'getAvailableVariables']);
        Route::get('/categories', [\App\Http\Controllers\API\CertificateTemplateController::class, 'getCategories']);
        Route::get('/{template}', [\App\Http\Controllers\API\CertificateTemplateController::class, 'show']);
        Route::post('/', [\App\Http\Controllers\API\CertificateTemplateController::class, 'store']);
        Route::post('/{template}', [\App\Http\Controllers\API\CertificateTemplateController::class, 'update']);
        Route::delete('/{template}', [\App\Http\Controllers\API\CertificateTemplateController::class, 'destroy']);
    });

    // =====================
    // CERTIFICATES
    // =====================
    Route::prefix('certificates')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\CertificateController::class, 'index']); // My certificates
        Route::get('/verify', [\App\Http\Controllers\API\CertificateController::class, 'verify']); // Public verification
        Route::get('/{id}', [\App\Http\Controllers\API\CertificateController::class, 'show']);
        Route::get('/{id}/download', [\App\Http\Controllers\API\CertificateController::class, 'download']);
    });

    // Admin certificate management
    Route::prefix('admin/certificates')->middleware('role:admin|super-admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\CertificateController::class, 'getAllCertificates']);
        Route::get('/stats', [\App\Http\Controllers\API\CertificateController::class, 'stats']);
        Route::patch('/{id}/revoke', [\App\Http\Controllers\API\CertificateController::class, 'revoke']);
        Route::patch('/{id}/restore', [\App\Http\Controllers\API\CertificateController::class, 'restore']);
    });

    // =====================
    // MOODLE SSO ROUTE
    // =====================
    Route::post('/moodle/login-url', [\App\Http\Controllers\API\MoodleAuthController::class, 'getLoginUrl']);

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
    // AI CHAT & FAQ ROUTES
    // =====================
    Route::get('/chat/sessions', [\App\Http\Controllers\API\ChatController::class, 'getSessions']);
    Route::put('/chat/sessions/{id}', [\App\Http\Controllers\API\ChatController::class, 'renameSession']);
    Route::delete('/chat/sessions/{id}', [\App\Http\Controllers\API\ChatController::class, 'deleteSession']);
    Route::get('/chat/history', [\App\Http\Controllers\API\ChatController::class, 'history']);
    Route::post('/chat', [\App\Http\Controllers\API\ChatController::class, 'chat']);
    Route::post('/chat/faq-feedback', [\App\Http\Controllers\API\ChatController::class, 'faqFeedback']);

    // =====================
    // FAQ MANAGEMENT (Admin/Super Admin)
    // =====================
    Route::prefix('admin/ai-faqs')->middleware('role:admin|super-admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\AiFaqController::class, 'index']);
        Route::get('/statistics', [\App\Http\Controllers\API\AiFaqController::class, 'statistics']);
        Route::post('/', [\App\Http\Controllers\API\AiFaqController::class, 'store']);
        Route::get('/{id}', [\App\Http\Controllers\API\AiFaqController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\API\AiFaqController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\API\AiFaqController::class, 'destroy']);
        Route::post('/bulk-toggle', [\App\Http\Controllers\API\AiFaqController::class, 'bulkToggle']);

        // FAQ Suggestions (Auto-Learn)
        Route::get('/suggestions/list', [\App\Http\Controllers\API\AiFaqController::class, 'suggestions']);
        Route::post('/suggestions/{id}/approve', [\App\Http\Controllers\API\AiFaqController::class, 'approveSuggestion']);
        Route::post('/suggestions/{id}/reject', [\App\Http\Controllers\API\AiFaqController::class, 'rejectSuggestion']);
    });
});
