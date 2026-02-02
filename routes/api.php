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

    // Dashboard
    Route::get('/users', [\App\Http\Controllers\API\UserController::class, 'index']);
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
    // ROLES & PERMISSIONS (Super Admin & Admin)
    // =====================
    Route::prefix('superadmin/roles')->middleware('role:super-admin,admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\RoleController::class, 'getAllRoles']);
        Route::get('/permissions/all', [\App\Http\Controllers\API\RoleController::class, 'getAllPermissions']);
        Route::post('/', [\App\Http\Controllers\API\RoleController::class, 'createRole']);
        Route::get('/{role}', [\App\Http\Controllers\API\RoleController::class, 'showRole']);
        Route::put('/{role}/permissions', [\App\Http\Controllers\API\RoleController::class, 'updateRolePermissions']);
        Route::delete('/{role}', [\App\Http\Controllers\API\RoleController::class, 'deleteRole']);
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
        Route::get('/tickets', [SupportTicketController::class, 'index']);
        // Moved up to prevent conflict with {id}
        Route::get('/stats', [SupportTicketController::class, 'getStats']); // Changed from /tickets/stats to /stats based on controller prefix logic, but prefix is 'support', controller uses 'getStats'. Wait, frontend calls /support/tickets/stats. So it should be under prefix support.
        // Actually, looking at the file content:
        // Route::prefix('support')->group(function () {
        //     Route::get('/tickets', [SupportTicketController::class, 'index']);
        //     Route::get('/tickets/stats', [SupportTicketController::class, 'getStats']);
        //     Route::get('/tickets/{id}', [SupportTicketController::class, 'show']);
        // });
        // The issue is definitely order.
        
        Route::get('/tickets/stats', [SupportTicketController::class, 'getStats']);
        Route::get('/tickets/{id}', [SupportTicketController::class, 'show']);
        Route::get('/tickets', [SupportTicketController::class, 'index']); // Index should be accessible too.
        
        // Let's rewrite the block clearly.
        Route::get('/tickets/stats', [SupportTicketController::class, 'getStats']);
        Route::get('/tickets', [SupportTicketController::class, 'index']);
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
        Route::get('/{id}', [\App\Http\Controllers\Api\CourseController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\Api\CourseController::class, 'update']);
        Route::post('/{id}/enroll', [\App\Http\Controllers\Api\CourseController::class, 'enrollUser']);
        Route::delete('/{id}/enroll/{userId}', [\App\Http\Controllers\Api\CourseController::class, 'unenrollUser']);
    });

    // =====================
    // MOODLE SSO ROUTE
    // =====================
    Route::post('/moodle/login-url', [\App\Http\Controllers\API\MoodleAuthController::class, 'getLoginUrl']);

    // =====================
    // AI CHAT ROUTE
    // =====================
    Route::get('/chat/sessions', [\App\Http\Controllers\API\ChatController::class, 'getSessions']);
    Route::put('/chat/sessions/{id}', [\App\Http\Controllers\API\ChatController::class, 'renameSession']);
    Route::delete('/chat/sessions/{id}', [\App\Http\Controllers\API\ChatController::class, 'deleteSession']);
    Route::get('/chat/history', [\App\Http\Controllers\API\ChatController::class, 'history']);
    Route::post('/chat', [\App\Http\Controllers\API\ChatController::class, 'chat']);
});
