<?php

use App\Http\Controllers\Admin\AiConfigurationController as AdminAiConfigurationController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\StatisticsController as AdminStatisticsController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\WhatsAppController as AdminWhatsAppController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\FridayController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\User\AiAssistantController;
use App\Http\Controllers\User\CalendarController;
use App\Http\Controllers\User\DashboardController as UserDashboardController;
use App\Http\Controllers\User\FeedbackController as UserFeedbackController;
use App\Http\Controllers\User\NotificationController as UserNotificationController;
use App\Http\Controllers\User\ScheduleController;
use App\Http\Controllers\User\StatisticsController;
use App\Http\Controllers\User\TaskController;
use App\Http\Controllers\User\WhatsAppSettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/

Route::get('/', [LandingController::class, 'index'])->name('home');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.store');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('register.store');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/language/{locale}', [LanguageController::class, 'switch'])
    ->middleware('auth')
    ->name('language.switch');

/*
|--------------------------------------------------------------------------
| User routes (authenticated + role user only)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:user'])
    ->prefix('user')
    ->name('user.')
    ->group(function () {
        Route::get('/dashboard', [UserDashboardController::class, 'index'])->name('dashboard');

        Route::patch('/tasks/{id}/status', [TaskController::class, 'updateStatus'])->name('tasks.status');
        Route::resource('tasks', TaskController::class)->parameters(['tasks' => 'id']);

        Route::resource('schedules', ScheduleController::class)
            ->except(['show'])
            ->parameters(['schedules' => 'id']);
        Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');
        Route::get('/statistics', [StatisticsController::class, 'index'])->name('statistics');

        Route::get('/ai-assistant', [AiAssistantController::class, 'index'])->name('ai.index');
        Route::post('/ai-assistant', [AiAssistantController::class, 'send'])->name('ai.send');
        Route::get('/ai-assistant/history', [AiAssistantController::class, 'history'])->name('ai.history');
        Route::delete('/ai-assistant', [AiAssistantController::class, 'clear'])->name('ai.clear');

        Route::get('/notifications', [UserNotificationController::class, 'index'])->name('notifications.index');
        Route::patch('/notifications/read-all', [UserNotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::patch('/notifications/{id}/read', [UserNotificationController::class, 'markAsRead'])->name('notifications.read');

        Route::get('/feedback', [UserFeedbackController::class, 'index'])->name('feedback.index');
        Route::post('/feedback', [UserFeedbackController::class, 'store'])->name('feedback.store');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::put('/profile/whatsapp', [WhatsAppSettingsController::class, 'update'])->name('whatsapp.update');
    });

/*
|--------------------------------------------------------------------------
| Admin routes (authenticated + role admin only)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/{id}', [AdminUserController::class, 'show'])->name('users.show');
        Route::patch('/users/{id}/toggle-status', [AdminUserController::class, 'toggleStatus'])->name('users.toggle-status');
        Route::delete('/users/{id}', [AdminUserController::class, 'destroy'])->name('users.destroy');

        Route::get('/statistics', [AdminStatisticsController::class, 'index'])->name('statistics');

        Route::get('/feedback', [AdminFeedbackController::class, 'index'])->name('feedback.index');
        Route::get('/feedback/{id}', [AdminFeedbackController::class, 'show'])->name('feedback.show');
        Route::patch('/feedback/{id}', [AdminFeedbackController::class, 'update'])->name('feedback.update');

        Route::get('/notifications', [AdminNotificationController::class, 'index'])->name('notifications.index');

        Route::get('/reports', [AdminReportController::class, 'index'])->name('reports.index');
        Route::put('/reports', [AdminReportController::class, 'update'])->name('reports.update');
        Route::post('/reports/send', [AdminReportController::class, 'sendNow'])->name('reports.send');

        Route::get('/ai', [AdminAiConfigurationController::class, 'edit'])->name('ai.edit');
        Route::put('/ai', [AdminAiConfigurationController::class, 'update'])->name('ai.update');
        Route::match(['post', 'put'], '/ai/test-connection', [AdminAiConfigurationController::class, 'testConnection'])->name('ai.test-connection');

        Route::get('/whatsapp', [AdminWhatsAppController::class, 'edit'])->name('whatsapp.edit');
        Route::put('/whatsapp', [AdminWhatsAppController::class, 'update'])->name('whatsapp.update');
        Route::match(['post', 'put'], '/whatsapp/test-connection', [AdminWhatsAppController::class, 'testConnection'])->name('whatsapp.test-connection');
        Route::post('/whatsapp/test-notification', [AdminWhatsAppController::class, 'testNotification'])->name('whatsapp.test-notification');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    });

/*
|--------------------------------------------------------------------------
| Friday AI (both authenticated roles)
|--------------------------------------------------------------------------
|
| Every query is scoped to the authenticated account. The same routes are
| used by User and Admin, while QwenService builds role-appropriate context.
|
*/

Route::middleware(['auth', 'role:admin,user'])
    ->prefix('friday')
    ->name('friday.')
    ->group(function () {
        Route::get('/', [FridayController::class, 'index'])->name('index');
        Route::post('/', [FridayController::class, 'send'])->name('send');
        Route::get('/history', [FridayController::class, 'history'])->name('history');
        Route::delete('/', [FridayController::class, 'clear'])->name('clear');
    });

// Friendly fallback instead of a generic 404 for unknown URLs.
Route::fallback(function () {
    if (auth()->check()) {
        return auth()->user()->isAdmin()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('user.dashboard');
    }

    return redirect()->route('home');
});
