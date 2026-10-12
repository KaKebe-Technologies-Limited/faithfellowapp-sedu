<?php

use App\Http\Controllers\ChurchAdmin;
use App\Http\Controllers\ChurchSite;
use App\Http\Controllers\Member;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public;
use App\Http\Controllers\SuperAdmin;
use App\Http\Controllers\Webhooks\RtmpController;
use App\Models\Church;
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

    // Subscriptions ("my churches")
    Route::get('/my-churches', [Member\SubscriptionController::class, 'index'])->name('my-churches.index');
    // Guests clicking "Subscribe" land here after signing up (intended URL), then confirm on the church page.
    Route::get('/churches/{church}/join', fn (Church $church) => redirect()->route('churches.show', [$church, 'join' => 1]))->name('churches.join');
    Route::post('/churches/{church}/subscribe', [Member\SubscriptionController::class, 'store'])->middleware('throttle:engagement')->name('churches.subscribe');
    Route::delete('/churches/{church}/subscribe', [Member\SubscriptionController::class, 'destroy'])->name('churches.unsubscribe');
    Route::put('/churches/{church}/mute', [Member\SubscriptionController::class, 'mute'])->name('churches.mute');

    // Notification preferences & center
    Route::get('/notification-preferences', [Member\NotificationPreferenceController::class, 'edit'])->name('notification-preferences.edit');
    Route::put('/notification-preferences', [Member\NotificationPreferenceController::class, 'update'])->name('notification-preferences.update');
    Route::get('/notifications', [Member\NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/count', [Member\NotificationController::class, 'count'])->name('notifications.count');
    Route::post('/notifications/read-all', [Member\NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/notifications/{id}', [Member\NotificationController::class, 'open'])->name('notifications.open');
    Route::delete('/notifications/{id}', [Member\NotificationController::class, 'destroy'])->name('notifications.destroy');

    // Opt-in member directory
    Route::get('/directory', [Member\DirectoryController::class, 'index'])->name('directory.index');

    // Content
    Route::get('/search', Member\SearchController::class)->name('search');
    Route::get('/sermons', [Member\SermonController::class, 'index'])->name('sermons.index');
    Route::get('/devotionals', [Member\DevotionalController::class, 'index'])->name('devotionals.index');
    Route::get('/reading-plans', [Member\ReadingPlanController::class, 'index'])->name('reading-plans.index');
    Route::get('/reading-plans/{readingPlan}', [Member\ReadingPlanController::class, 'show'])->name('reading-plans.show');
    Route::post('/reading-plans/{readingPlan}/start', [Member\ReadingPlanController::class, 'start'])->name('reading-plans.start');
    Route::post('/reading-plans/{readingPlan}/complete', [Member\ReadingPlanController::class, 'complete'])->name('reading-plans.complete');
    Route::delete('/reading-plans/{readingPlan}', [Member\ReadingPlanController::class, 'leave'])->name('reading-plans.leave');

    // Events
    Route::get('/events', [Member\EventController::class, 'index'])->name('events.index');
    Route::post('/events/{event}/rsvp', [Member\EventController::class, 'rsvp'])->middleware('throttle:engagement')->name('events.rsvp');
    Route::delete('/events/{event}/rsvp', [Member\EventController::class, 'cancel'])->name('events.rsvp.cancel');
    Route::get('/events/{event}/ticket', [Member\EventController::class, 'ticket'])->name('events.ticket');
    Route::post('/events/{event}/questions', [Member\EventController::class, 'ask'])->middleware('throttle:engagement')->name('events.questions.store');
    Route::post('/events/{event}/questions/{question}/upvote', [Member\EventController::class, 'upvote'])->name('events.questions.upvote');

    // Live
    Route::get('/live', [Member\LiveController::class, 'index'])->name('live.index');
    Route::post('/live/{liveStream}/messages', [Member\LiveController::class, 'postMessage'])->middleware('throttle:engagement')->name('live.messages.store');
});

Route::get('/events/{event}', [Member\EventController::class, 'show'])->name('events.show');
Route::get('/live/{liveStream}', [Member\LiveController::class, 'show'])->name('live.show');
Route::post('/live/{liveStream}/heartbeat', [Member\LiveController::class, 'heartbeat'])->middleware('throttle:engagement')->name('live.heartbeat');
Route::get('/live/{liveStream}/messages', [Member\LiveController::class, 'messages'])->middleware('throttle:120,1')->name('live.messages');

/*
|--------------------------------------------------------------------------
| Webhooks (CSRF-exempt, signature/secret verified per provider)
|--------------------------------------------------------------------------
*/

Route::prefix('webhooks')->name('webhooks.')->middleware('throttle:webhooks')->group(function () {
    Route::post('/rtmp/publish', [RtmpController::class, 'publish'])->name('rtmp.publish');
    Route::post('/rtmp/done', [RtmpController::class, 'done'])->name('rtmp.done');
});

// Public sermons / devotionals / announcements are viewable by visitors (policies decide).
Route::get('/sermons/{sermon}', [Member\SermonController::class, 'show'])->name('sermons.show');
Route::post('/sermons/{sermon}/track', [Member\SermonController::class, 'track'])->middleware('throttle:engagement')->name('sermons.track');
Route::get('/sermons/{sermon}/download', [Member\SermonController::class, 'download'])->middleware('throttle:engagement')->name('sermons.download');
Route::get('/devotionals/{devotional}', [Member\DevotionalController::class, 'show'])->name('devotionals.show');
Route::get('/announcements/{announcement}', [Member\DevotionalController::class, 'announcement'])->name('announcements.show');

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

        Route::get('/search', ChurchAdmin\SearchController::class)->name('search');

        // Content
        Route::middleware('church.can:content.manage')->group(function () {
            Route::resource('sermons', ChurchAdmin\SermonController::class)->except('show');
            Route::post('/sermons/{sermon}/transcribe', [ChurchAdmin\SermonController::class, 'transcribe'])->name('sermons.transcribe');
            Route::get('/content/sermons', fn ($church) => redirect()->route('church-admin.sermons.index', $church));
            Route::get('/content/sermons/create', fn ($church) => redirect()->route('church-admin.sermons.create', $church));
            Route::post('/series', [ChurchAdmin\SeriesController::class, 'store'])->name('series.store');
            Route::delete('/series/{series}', [ChurchAdmin\SeriesController::class, 'destroy'])->name('series.destroy');
            Route::post('/playlists', [ChurchAdmin\SeriesController::class, 'storePlaylist'])->name('playlists.store');
            Route::put('/playlists/{playlist}', [ChurchAdmin\SeriesController::class, 'updatePlaylist'])->name('playlists.update');
            Route::delete('/playlists/{playlist}', [ChurchAdmin\SeriesController::class, 'destroyPlaylist'])->name('playlists.destroy');
            Route::resource('devotionals', ChurchAdmin\DevotionalController::class)->except('show');
            Route::resource('announcements', ChurchAdmin\AnnouncementController::class)->except('show');
            Route::resource('reading-plans', ChurchAdmin\ReadingPlanController::class)->except('show')->parameters(['reading-plans' => 'readingPlan']);
            Route::post('/reading-plans/import', [ChurchAdmin\ReadingPlanController::class, 'import'])->name('reading-plans.import');
            Route::get('/content/devotionals', fn ($church) => redirect()->route('church-admin.devotionals.index', $church));
            Route::get('/content/announcements', fn ($church) => redirect()->route('church-admin.announcements.index', $church));
        });

        // Events
        Route::middleware('church.can:events.manage')->group(function () {
            Route::resource('events', ChurchAdmin\EventController::class);
            Route::get('/events/{event}/export', [ChurchAdmin\EventController::class, 'export'])->name('events.export');
            Route::get('/events/{event}/check-in', [ChurchAdmin\EventController::class, 'checkInScreen'])->name('events.check-in');
            Route::post('/events/{event}/check-in', [ChurchAdmin\EventController::class, 'checkIn'])->name('events.check-in.store');
            Route::get('/events/{event}/check-in/{code}', [ChurchAdmin\EventController::class, 'checkInCode'])->name('events.check-in.code');
            Route::post('/events/{event}/recording', [ChurchAdmin\EventController::class, 'publishRecording'])->name('events.recording');
            Route::put('/events/{event}/questions/{question}', [ChurchAdmin\EventController::class, 'moderateQuestion'])->name('events.questions.update');
        });

        // Live streaming
        Route::middleware('church.can:live.manage')->group(function () {
            Route::resource('live', ChurchAdmin\LiveStreamController::class)->parameters(['live' => 'liveStream']);
            Route::get('/live-schedule', fn ($church) => redirect()->route('church-admin.live.create', $church))->name('live.schedule');
            Route::post('/live/{liveStream}/start', [ChurchAdmin\LiveStreamController::class, 'start'])->name('live.start');
            Route::post('/live/{liveStream}/stop', [ChurchAdmin\LiveStreamController::class, 'stop'])->name('live.stop');
            Route::post('/live/{liveStream}/archive', [ChurchAdmin\LiveStreamController::class, 'archive'])->name('live.archive');
            Route::post('/live/{liveStream}/key', [ChurchAdmin\LiveStreamController::class, 'rotateKey'])->name('live.key');
            Route::get('/live/{liveStream}/stats', [ChurchAdmin\LiveStreamController::class, 'stats'])->name('live.stats');
            Route::post('/live/{liveStream}/messages/{messageId}/hide', [ChurchAdmin\LiveStreamController::class, 'hideMessage'])->whereNumber('messageId')->name('live.messages.hide');
        });

        Route::middleware('church.can:media.manage')->group(function () {
            Route::get('/media', [ChurchAdmin\MediaLibraryController::class, 'index'])->name('media.index');
            Route::post('/media', [ChurchAdmin\MediaLibraryController::class, 'store'])->name('media.store');
            Route::delete('/media/{mediaId}', [ChurchAdmin\MediaLibraryController::class, 'destroy'])->whereNumber('mediaId')->name('media.destroy');
        });

        // Members
        Route::middleware('church.can:members.manage')->group(function () {
            Route::get('/members', [ChurchAdmin\MemberController::class, 'index'])->name('members.index');
            Route::get('/members/export', [ChurchAdmin\MemberController::class, 'export'])->name('members.export');
            Route::put('/members/{subscription}', [ChurchAdmin\MemberController::class, 'update'])->name('members.update');
        });

        // Feed posts
        Route::middleware('church.can:content.manage|community.moderate')->group(function () {
            Route::get('/posts', [ChurchAdmin\PostController::class, 'index'])->name('posts.index');
            Route::post('/posts', [ChurchAdmin\PostController::class, 'store'])->name('posts.store');
            Route::put('/posts/{post}', [ChurchAdmin\PostController::class, 'update'])->name('posts.update');
            Route::delete('/posts/{post}', [ChurchAdmin\PostController::class, 'destroy'])->name('posts.destroy');
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
