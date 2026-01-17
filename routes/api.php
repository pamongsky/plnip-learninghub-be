<?php

use App\Http\Controllers\API\AnnouncementController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DashboardController;
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

// Protected routes (requires Sanctum authentication)
Route::middleware('auth:sanctum')->group(function () {

    // Authentication
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Dashboard
    Route::get('/dashboard/employee', [DashboardController::class, 'employeeDashboard']);
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Announcements
    Route::get('/announcements', [AnnouncementController::class, 'index']);
    Route::get('/announcements/latest', [AnnouncementController::class, 'latest']);
    Route::get('/announcements/{id}', [AnnouncementController::class, 'show']);
});
