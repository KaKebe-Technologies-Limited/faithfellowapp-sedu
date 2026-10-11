<?php

use App\Http\Controllers\ChurchAdmin;
use App\Http\Controllers\ChurchSite;
use App\Http\Controllers\Member;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public;
use App\Http\Controllers\SuperAdmin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Church websites on subdomains: {slug}.faithfellows.app
|--------------------------------------------------------------------------
| Registered first so a church subdomain's "/" renders its website.
*/

Route::domain('{subdomain}.'.config('faithfellows.root_domain'))->group(function () {
    Route::get('/', [ChurchSite\SiteController::class, 'show'])->name('church-site.home');
});

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

// Church directory & public church pages
Route::get('/churches', [Public\ChurchDirectoryController::class, 'index'])->name('churches.index');
Route::get('/register-church', [Public\ChurchApplicationController::class, 'create'])->name('church.apply');
Route::post('/register-church', [Public\ChurchApplicationController::class, 'store'])
    ->middleware(['auth', 'verified', 'throttle:5,60'])->name('church.apply.store');
Route::get('/churches/{church}', [Public\ChurchDirectoryController::class, 'show'])->name('churches.show');

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

    Route::post('/impersonate/stop', [SuperAdmin\ImpersonationController::class, 'stop'])->name('impersonate.stop');
});

/*
|--------------------------------------------------------------------------
| Church admin: /church/{slug}/admin
|--------------------------------------------------------------------------
| Child models resolve through the church (scopeBindings), so a record from
| another church can never be loaded via this church's URLs.
*/

Route::prefix('church/{church}/admin')->name('church-admin.')
    ->middleware(['auth', 'verified', 'church.staff'])
    ->scopeBindings()
    ->group(function () {
        Route::get('/', fn ($church) => redirect()->route('church-admin.dashboard', $church));
        Route::get('/dashboard', ChurchAdmin\DashboardController::class)->name('dashboard');

        // Setup wizard
        Route::get('/setup', [ChurchAdmin\SetupController::class, 'show'])->name('setup');
        Route::post('/setup', [ChurchAdmin\SetupController::class, 'update'])->name('setup.update');

        // Settings
        Route::middleware('church.can:settings.manage')->group(function () {
            Route::get('/settings/profile', [ChurchAdmin\SettingsController::class, 'profile'])->name('settings.profile');
            Route::put('/settings/profile', [ChurchAdmin\SettingsController::class, 'updateProfile'])->name('settings.profile.update');
            Route::get('/settings/branding', [ChurchAdmin\SettingsController::class, 'branding'])->name('settings.branding');
            Route::post('/settings/branding', [ChurchAdmin\SettingsController::class, 'updateBranding'])->name('settings.branding.update');
            Route::get('/settings/website', [ChurchAdmin\SettingsController::class, 'website'])->name('settings.website');
            Route::put('/settings/website', [ChurchAdmin\SettingsController::class, 'updateWebsite'])->name('settings.website.update');
            Route::get('/settings/integrations', [ChurchAdmin\SettingsController::class, 'integrations'])->name('settings.integrations');
            Route::put('/settings/integrations', [ChurchAdmin\SettingsController::class, 'updateIntegrations'])->name('settings.integrations.update');
            Route::resource('branches', ChurchAdmin\BranchController::class)->except('show');
        });

        // Staff & roles
        Route::middleware('church.can:roles.manage')->group(function () {
            Route::get('/roles', [ChurchAdmin\RoleController::class, 'index'])->name('roles.index');
            Route::get('/settings/roles', fn ($church) => redirect()->route('church-admin.roles.index', $church))->name('settings.roles');
            Route::resource('roles', ChurchAdmin\RoleController::class)->except(['index', 'show']);
            Route::post('/staff', [ChurchAdmin\StaffController::class, 'store'])->name('staff.store');
            Route::put('/staff/{staffMember}', [ChurchAdmin\StaffController::class, 'update'])->name('staff.update');
            Route::delete('/staff/{staffMember}', [ChurchAdmin\StaffController::class, 'destroy'])->name('staff.destroy');
            Route::get('/audit-logs', ChurchAdmin\AuditLogController::class)->name('audit-logs');
        });
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

        Route::get('/churches', [SuperAdmin\ChurchController::class, 'index'])->name('churches.index');
        Route::get('/churches/create', [SuperAdmin\ChurchController::class, 'create'])->name('churches.create');
        Route::post('/churches', [SuperAdmin\ChurchController::class, 'store'])->name('churches.store');
        Route::get('/churches/{church:id}', [SuperAdmin\ChurchController::class, 'show'])->name('churches.show');
        Route::patch('/churches/{church:id}', [SuperAdmin\ChurchController::class, 'update'])->name('churches.update');
        Route::post('/churches/{church:id}/approve', [SuperAdmin\ChurchController::class, 'approve'])->name('churches.approve');
        Route::post('/churches/{church:id}/suspend', [SuperAdmin\ChurchController::class, 'suspend'])->name('churches.suspend');
        Route::post('/churches/{church:id}/reactivate', [SuperAdmin\ChurchController::class, 'reactivate'])->name('churches.reactivate');
        Route::post('/churches/{church:id}/archive', [SuperAdmin\ChurchController::class, 'archive'])->name('churches.archive');

        Route::post('/impersonate/{user}', [SuperAdmin\ImpersonationController::class, 'start'])->name('impersonate.start');
    });

require __DIR__.'/auth.php';
