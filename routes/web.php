<?php

use App\Http\Controllers\Member;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public;
use App\Http\Controllers\SuperAdmin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public marketing site
|--------------------------------------------------------------------------
*/

Route::get('/', [Public\PageController::class, 'home'])->name('home');
Route::get('/about', [Public\PageController::class, 'about'])->name('about');
Route::get('/services', [Public\PageController::class, 'services'])->name('services');
Route::get('/pricing', [Public\PageController::class, 'pricing'])->name('pricing');
Route::get('/privacy', [Public\PageController::class, 'privacy'])->name('privacy');
Route::get('/terms', [Public\PageController::class, 'terms'])->name('terms');
Route::get('/offline', [Public\PageController::class, 'offline'])->name('offline');

Route::get('/contact', [Public\ContactController::class, 'show'])->name('contact');
Route::post('/contact', [Public\ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');

Route::get('/blog', [Public\BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{post:slug}', [Public\BlogController::class, 'show'])->name('blog.show');

/*
|--------------------------------------------------------------------------
| Member area (verified users)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', Member\DashboardController::class)->name('dashboard');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/preferences', [ProfileController::class, 'updatePreferences'])->name('profile.preferences');
    Route::delete('/profile/sessions', [ProfileController::class, 'destroySessions'])->name('profile.sessions.destroy');
    Route::get('/profile/export', [ProfileController::class, 'export'])->name('profile.export');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| Super admin (Kakebe Technologies staff)
|--------------------------------------------------------------------------
*/

Route::prefix('super-admin')->name('super-admin.')
    ->middleware(['auth', 'verified', 'role:super_admin'])
    ->group(function () {
        Route::get('/', fn () => redirect()->route('super-admin.dashboard'));
        Route::get('/dashboard', SuperAdmin\DashboardController::class)->name('dashboard');
    });

require __DIR__.'/auth.php';
