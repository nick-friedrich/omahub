<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminPluginController;
use App\Http\Controllers\Admin\AdminSubmissionController;
use App\Http\Controllers\Auth\GitHubAuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PluginController;
use App\Http\Controllers\ResourcesController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SubmitController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/plugins', [PluginController::class, 'index'])->name('plugins.index');
Route::get('/plugins/{plugin:slug}', [PluginController::class, 'show'])->name('plugins.show');
Route::get('/categories/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/resources', ResourcesController::class)->name('resources.index');
Route::view('/privacy', 'privacy')->name('privacy');
Route::view('/impressum', 'impressum')->name('impressum');

// GitHub OAuth sign-in.
Route::get('/auth/github/redirect', [GitHubAuthController::class, 'redirect'])->name('auth.github.redirect');
Route::get('/auth/github/callback', [GitHubAuthController::class, 'callback'])->name('auth.github.callback');
Route::post('/auth/logout', [GitHubAuthController::class, 'logout'])
    ->middleware('auth')
    ->name('auth.logout');

// The submit form is public so visitors see what's needed; posting a
// submission requires a signed-in account to discourage spam.
Route::get('/submit', [SubmitController::class, 'index'])->name('submit');
Route::post('/submit', [SubmitController::class, 'store'])
    ->middleware(['auth', 'throttle:submissions'])
    ->name('submit.store');

// Admin-only area for reviewing submissions and maintaining published listings.
Route::middleware('admin')->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [AdminController::class, 'index'])->name('dashboard');

    Route::get('/submissions', [AdminSubmissionController::class, 'index'])->name('submissions.index');
    Route::get('/submissions/{submission}', [AdminSubmissionController::class, 'show'])->name('submissions.show');
    Route::post('/submissions/{submission}/approve', [AdminSubmissionController::class, 'approve'])->name('submissions.approve');
    Route::post('/submissions/{submission}/reject', [AdminSubmissionController::class, 'reject'])->name('submissions.reject');

    Route::get('/plugins', [AdminPluginController::class, 'index'])->name('plugins.index');
    Route::get('/plugins/{plugin}/edit', [AdminPluginController::class, 'edit'])->name('plugins.edit');
    Route::put('/plugins/{plugin}', [AdminPluginController::class, 'update'])->name('plugins.update');
    Route::post('/plugins/{plugin}/refresh', [AdminPluginController::class, 'refresh'])->name('plugins.refresh');
    Route::post('/plugins/{plugin}/status', [AdminPluginController::class, 'status'])->name('plugins.status');
    Route::delete('/plugins/{plugin}', [AdminPluginController::class, 'destroy'])->name('plugins.destroy');
});
