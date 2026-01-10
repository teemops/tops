<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Test notifications (remove in production)
Route::get('/test-notifications', function () {
    return Inertia::render('TestNotifications');
})->middleware(['auth'])->name('test-notifications');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Organizations
    Route::get('/organizations', function () {
        return Inertia::render('Organizations/Index');
    })->name('organizations.index');
    
    Route::get('/organizations/{orgId}/settings', function (string $orgId) {
        return Inertia::render('Organizations/Settings', ['orgId' => $orgId]);
    })->name('organizations.settings');
});

require __DIR__.'/auth.php';
