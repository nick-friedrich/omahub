<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PluginController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SubmitController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/plugins', [PluginController::class, 'index'])->name('plugins.index');
Route::get('/plugins/{plugin:slug}', [PluginController::class, 'show'])->name('plugins.show');
Route::get('/categories/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/submit', [SubmitController::class, 'index'])->name('submit');
Route::post('/submit', [SubmitController::class, 'store'])
    ->middleware('throttle:submissions')
    ->name('submit.store');
