<?php

use App\Http\Controllers\ChurchAdmin;
use App\Http\Controllers\ChurchSite;
use App\Http\Controllers\Member;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public;
use App\Http\Controllers\SuperAdmin;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use App\Http\Controllers\Webhooks\RtmpController;
use App\Http\Controllers\Webhooks\UssdController;
use App\Models\Church;
use Illuminate\Http\Request;
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
Route::get('/pay/return/{reference}', function (Request $request, string $reference) {
    return view('pay.return', ['reference' => $reference, 'cancelled' => $request->boolean('cancelled')]);
})->where('reference', '[A-Za-z0-9_-]+')->name('pay.return');
Route::get('/art/{kind}/{id}', [Public\ArtController::class, 'show'])->whereIn('kind', ['event', 'sermon', 'devotional', 'campaign', 'group'])->whereNumber('id')->middleware('throttle:240,1')->name('art');
Route::get('/terms', [Public\PageController::class, 'terms'])->name('terms');
Route::get('/offline', [Public\PageController::class, 'offline'])->name('offline');
Route::get('/sitemap.xml', [Public\SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [Public\SeoController::class, 'robots'])->name('robots');

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

    // Reading partners (circles of up to 4)
    Route::post('/reading-plans/{readingPlan}/circles', [Member\ReadingCircleController::class, 'store'])->name('reading-circles.store');
    Route::post('/reading-circles/join', [Member\ReadingCircleController::class, 'join'])->middleware('throttle:20,1')->name('reading-circles.join');
    Route::get('/reading-circles/{readingCircle}', [Member\ReadingCircleController::class, 'show'])->name('reading-circles.show');
    Route::post('/reading-circles/{readingCircle}/days/{day}', [Member\ReadingCircleController::class, 'complete'])->whereNumber('day')->name('reading-circles.complete');
    Route::put('/reading-circles/{readingCircle}', [Member\ReadingCircleController::class, 'update'])->name('reading-circles.update');
    Route::delete('/reading-circles/{readingCircle}/leave', [Member\ReadingCircleController::class, 'leave'])->name('reading-circles.leave');

    // Events
    Route::get('/events', [Member\EventController::class, 'index'])->name('events.index');
    Route::post('/events/{event}/rsvp', [Member\EventController::class, 'rsvp'])->middleware('throttle:engagement')->name('events.rsvp');
    Route::delete('/events/{event}/rsvp', [Member\EventController::class, 'cancel'])->name('events.rsvp.cancel');
    Route::get('/events/{event}/ticket', [Member\EventController::class, 'ticket'])->name('events.ticket');
    Route::post('/events/{event}/questions', [Member\EventController::class, 'ask'])->middleware('throttle:engagement')->name('events.questions.store');
    Route::post('/events/{event}/questions/{question}/upvote', [Member\EventController::class, 'upvote'])->name('events.questions.upvote');

    // Giving
    Route::get('/giving', [Member\GivingController::class, 'index'])->name('giving.index');
    Route::get('/giving/receipts/{reference}', [Member\GivingController::class, 'receipt'])->name('giving.receipt');
    Route::post('/churches/{church}/pledges', [Member\GivingController::class, 'storePledge'])->name('pledges.store');
    Route::delete('/pledges/{pledge}', [Member\GivingController::class, 'cancelPledge'])->name('pledges.cancel');
    Route::put('/recurring/{recurringGift}', [Member\GivingController::class, 'updateRecurring'])->name('recurring.update');
    Route::get('/give/{church}', [Member\GiveController::class, 'create'])->name('give.create');
    Route::post('/give/{church}', [Member\GiveController::class, 'store'])->middleware('throttle:giving')->name('give.store');
    Route::get('/give/status/{reference}', [Member\GiveController::class, 'status'])->name('give.status');
    Route::get('/give/status/{reference}/check', [Member\GiveController::class, 'statusJson'])->middleware('throttle:60,1')->name('give.status.check');
    Route::match(['get', 'post'], '/give/sandbox/{reference}', [Member\GiveController::class, 'sandbox'])->name('give.sandbox');
    Route::match(['get', 'post'], '/give/ticket/{rsvp}', [Member\GiveController::class, 'ticket'])->middleware('throttle:giving')->name('give.ticket');
    Route::get('/campaigns', [Member\CampaignController::class, 'index'])->name('campaigns.index');
    Route::post('/campaigns/{campaign}/comments', [Member\CampaignController::class, 'comment'])->middleware('throttle:engagement')->name('campaigns.comments.store');

    // Live
    Route::get('/live', [Member\LiveController::class, 'index'])->name('live.index');
    Route::post('/live/{liveStream}/messages', [Member\LiveController::class, 'postMessage'])->middleware('throttle:engagement')->name('live.messages.store');

    // Prayer wall & testimonies
    Route::get('/prayer', [Member\PrayerController::class, 'index'])->name('prayer.index');
    Route::get('/prayer/new', [Member\PrayerController::class, 'create'])->name('prayer.create');
    Route::post('/prayer', [Member\PrayerController::class, 'store'])->middleware('throttle:engagement')->name('prayer.store');
    Route::get('/prayer/{prayer}', [Member\PrayerController::class, 'show'])->name('prayer.show');
    Route::post('/prayer/{prayer}/pray', [Member\PrayerController::class, 'pray'])->middleware('throttle:engagement')->name('prayer.pray');
    Route::post('/prayer/{prayer}/answered', [Member\PrayerController::class, 'answered'])->name('prayer.answered');
    Route::delete('/prayer/{prayer}', [Member\PrayerController::class, 'destroy'])->name('prayer.destroy');
    Route::get('/testimonies', [Member\TestimonyController::class, 'index'])->name('testimonies.index');
    Route::get('/testimonies/new', [Member\TestimonyController::class, 'create'])->name('testimonies.create');
    Route::post('/testimonies', [Member\TestimonyController::class, 'store'])->middleware('throttle:engagement')->name('testimonies.store');

    // Small groups (leaders manage their own group from the group page)
    Route::get('/groups', [Member\GroupController::class, 'index'])->name('groups.index');
    Route::get('/groups/{group}', [Member\GroupController::class, 'show'])->name('groups.show');
    Route::post('/groups/{group}/join', [Member\GroupController::class, 'join'])->middleware('throttle:engagement')->name('groups.join');
    Route::delete('/groups/{group}/leave', [Member\GroupController::class, 'leave'])->name('groups.leave');
    Route::post('/groups/{group}/members', [Member\GroupController::class, 'addMember'])->name('groups.members.store');
    Route::put('/groups/{group}/members/{membership}', [Member\GroupController::class, 'updateMember'])->scopeBindings()->name('groups.members.update');
    Route::post('/groups/{group}/meetings', [Member\GroupController::class, 'storeMeeting'])->name('groups.meetings.store');
    Route::post('/groups/{group}/announcements', [Member\GroupController::class, 'storeAnnouncement'])->name('groups.announcements.store');

    // Counseling (confidential)
    Route::get('/counseling', [Member\CounselingController::class, 'index'])->name('counseling.index');
    Route::post('/counseling', [Member\CounselingController::class, 'store'])->middleware('throttle:engagement')->name('counseling.store');
    Route::post('/counseling/{appointment}/cancel', [Member\CounselingController::class, 'cancel'])->name('counseling.cancel');

    // Reactions & comments, service check-in, serving rota
    Route::post('/react/{type}/{id}', [Member\EngagementController::class, 'react'])->whereNumber('id')->middleware('throttle:engagement')->name('reactions.toggle');
    Route::post('/comments/{type}/{id}', [Member\EngagementController::class, 'comment'])->whereNumber('id')->middleware('throttle:engagement')->name('comments.store');
    Route::delete('/comments/{comment}', [Member\EngagementController::class, 'deleteComment'])->name('comments.destroy');
    Route::get('/check-in/{church}', [Member\EngagementController::class, 'checkInPage'])->name('check-in.show');
    Route::post('/check-in/{church}', [Member\EngagementController::class, 'checkIn'])->middleware('throttle:engagement')->name('check-in.store');
    Route::get('/serving', [Member\EngagementController::class, 'rota'])->name('volunteering.index');
    Route::put('/serving/{rota}', [Member\EngagementController::class, 'respondRota'])->name('volunteering.respond');

    // In-app Bible
    Route::get('/bible', [Member\BibleController::class, 'index'])->name('bible.index');
    Route::get('/bible/passage', [Member\BibleController::class, 'passage'])->middleware('throttle:60,1')->name('bible.passage');
    Route::get('/bible/{book}/{chapter}', [Member\BibleController::class, 'read'])->where(['book' => '[1-3A-Za-z]{3}', 'chapter' => '[0-9]+'])->middleware('throttle:60,1')->name('bible.read');
    Route::post('/bible/translation', [Member\BibleController::class, 'setTranslation'])->name('bible.translation');
    Route::post('/bible/highlights', [Member\BibleController::class, 'highlight'])->middleware('throttle:60,1')->name('bible.highlight');

    // Notes (note taker + notebook)
    Route::get('/notes', [Member\NoteController::class, 'index'])->name('notes.index');
    Route::post('/notes', [Member\NoteController::class, 'create'])->name('notes.create');
    Route::get('/notes/export', [Member\NoteController::class, 'export'])->name('notes.export');
    Route::get('/notes/context', [Member\NoteController::class, 'context'])->name('notes.context');
    Route::put('/notes/context', [Member\NoteController::class, 'save'])->middleware('throttle:120,1')->name('notes.save');
    Route::get('/notes/{note}', [Member\NoteController::class, 'show'])->name('notes.show');
    Route::post('/notes/{note}/pin', [Member\NoteController::class, 'pin'])->name('notes.pin');
    Route::delete('/notes/{note}', [Member\NoteController::class, 'destroy'])->name('notes.destroy');

    // Quizzes & games (live)
    Route::get('/games', [Member\GameController::class, 'index'])->name('games.index');
    Route::post('/games/join', [Member\GameController::class, 'join'])->middleware('throttle:20,1')->name('games.join');
    Route::get('/games/{quizSession}/play', [Member\GameController::class, 'play'])->name('games.play');
    Route::get('/games/{quizSession}/state', [Member\GameController::class, 'state'])->middleware('throttle:240,1')->name('games.state');
    Route::post('/games/{quizSession}/answer', [Member\GameController::class, 'answer'])->middleware('throttle:60,1')->name('games.answer');
    Route::get('/games/{quizSession}/scores', [Member\GameController::class, 'scores'])->name('games.scores');

    // Reporting content, web push devices
    Route::post('/report/{type}/{id}', [Member\ReportController::class, 'store'])->whereNumber('id')->middleware('throttle:engagement')->name('reports.store');
    Route::post('/push-subscriptions', [Member\PushSubscriptionController::class, 'store'])->middleware('throttle:engagement')->name('push-subscriptions.store');
    Route::delete('/push-subscriptions', [Member\PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
});

Route::get('/events/{event}', [Member\EventController::class, 'show'])->name('events.show');
Route::get('/campaigns/{campaign}', [Member\CampaignController::class, 'show'])->name('campaigns.show');
Route::get('/campaigns/{campaign}/poster', [Member\CampaignController::class, 'poster'])->name('campaigns.poster');
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
    Route::post('/ussd', UssdController::class)->name('ussd');
    Route::post('/{provider}', PaymentWebhookController::class)
        ->whereIn('provider', ['mtn-momo', 'mtn_momo', 'airtel', 'airtel_money', 'iotec', 'stripe'])->name('payments');
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
            Route::post('/settings/slides', [ChurchAdmin\SlideController::class, 'store'])->name('slides.store');
            Route::put('/settings/slides/{mediaId}', [ChurchAdmin\SlideController::class, 'update'])->whereNumber('mediaId')->name('slides.update');
            Route::post('/settings/slides/{mediaId}/move', [ChurchAdmin\SlideController::class, 'move'])->whereNumber('mediaId')->name('slides.move');
            Route::delete('/settings/slides/{mediaId}', [ChurchAdmin\SlideController::class, 'destroy'])->whereNumber('mediaId')->name('slides.destroy');
            Route::get('/settings/website', [ChurchAdmin\SettingsController::class, 'website'])->name('settings.website');
            Route::put('/settings/website', [ChurchAdmin\SettingsController::class, 'updateWebsite'])->name('settings.website.update');
            Route::get('/settings/integrations', [ChurchAdmin\SettingsController::class, 'integrations'])->name('settings.integrations');
            Route::put('/settings/integrations', [ChurchAdmin\SettingsController::class, 'updateIntegrations'])->name('settings.integrations.update');
            Route::resource('branches', ChurchAdmin\BranchController::class)->except('show');
            Route::get('/settings/giving', [ChurchAdmin\GivingChannelController::class, 'edit'])->name('settings.giving');
            Route::put('/settings/giving', [ChurchAdmin\GivingChannelController::class, 'update'])->name('settings.giving.update');
            Route::get('/billing', [ChurchAdmin\BillingController::class, 'index'])->name('billing.index');
            Route::post('/billing/invoices/{invoice}/pay', [ChurchAdmin\BillingController::class, 'pay'])->middleware('throttle:giving')->name('billing.pay');
            Route::post('/billing/invoices/{invoice}/check', [ChurchAdmin\BillingController::class, 'check'])->name('billing.check');
            Route::post('/billing/plan', [ChurchAdmin\BillingController::class, 'changePlan'])->name('billing.plan');
            Route::post('/billing/domain', [ChurchAdmin\BillingController::class, 'requestDomain'])->name('billing.domain');
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

        // Giving & finance
        Route::middleware('church.can:giving.view')->group(function () {
            Route::get('/giving', [ChurchAdmin\GivingController::class, 'index'])->name('giving.index');
            Route::get('/giving/transactions', [ChurchAdmin\GivingController::class, 'transactions'])->name('giving.transactions');
            Route::get('/giving/export', [ChurchAdmin\GivingController::class, 'export'])->name('giving.export');
            Route::get('/giving/payouts', [ChurchAdmin\PayoutController::class, 'index'])->name('payouts.index');
        });
        Route::middleware('church.can:giving.manage')->group(function () {
            Route::post('/giving/record', [ChurchAdmin\GivingController::class, 'record'])->name('giving.record');
            Route::post('/giving/{donation}/refund', [ChurchAdmin\GivingController::class, 'refund'])->name('giving.refund');
            Route::post('/giving/{donation}/confirm', [ChurchAdmin\GivingController::class, 'confirm'])->name('giving.confirm');
            Route::post('/giving/payouts', [ChurchAdmin\PayoutController::class, 'store'])->name('payouts.store');
            Route::put('/giving/payouts/{payout}', [ChurchAdmin\PayoutController::class, 'update'])->name('payouts.update');
            Route::get('/giving/reconciliation', [ChurchAdmin\ReconciliationController::class, 'index'])->name('reconciliation.index');
            Route::post('/giving/reconciliation', [ChurchAdmin\ReconciliationController::class, 'import'])->name('reconciliation.import');
            Route::put('/giving/reconciliation/{bankStatementLine}', [ChurchAdmin\ReconciliationController::class, 'match'])->name('reconciliation.match');
            Route::get('/giving/channels', fn ($church) => redirect()->route('church-admin.settings.giving', $church));
        });

        // Campaigns
        Route::middleware('church.can:campaigns.manage')->group(function () {
            Route::resource('campaigns', ChurchAdmin\CampaignController::class);
            Route::post('/campaigns/{campaign}/updates', [ChurchAdmin\CampaignController::class, 'storeUpdate'])->name('campaigns.updates.store');
            Route::get('/campaigns/{campaign}/export', [ChurchAdmin\CampaignController::class, 'export'])->name('campaigns.export');
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

        // Small groups & volunteers
        Route::middleware('church.can:groups.manage')->group(function () {
            Route::resource('small-groups', ChurchAdmin\SmallGroupController::class)->except('show')->parameters(['small-groups' => 'smallGroup']);
            Route::get('/volunteers', [ChurchAdmin\VolunteerController::class, 'index'])->name('volunteers.index');
            Route::post('/volunteers/teams', [ChurchAdmin\VolunteerController::class, 'storeTeam'])->name('volunteers.teams.store');
            Route::put('/volunteers/teams/{volunteerTeam}', [ChurchAdmin\VolunteerController::class, 'updateTeam'])->name('volunteers.teams.update');
            Route::delete('/volunteers/teams/{volunteerTeam}', [ChurchAdmin\VolunteerController::class, 'destroyTeam'])->name('volunteers.teams.destroy');
            Route::post('/volunteers/teams/{volunteerTeam}/members', [ChurchAdmin\VolunteerController::class, 'addMember'])->name('volunteers.members.store');
            Route::delete('/volunteers/teams/{volunteerTeam}/members/{userId}', [ChurchAdmin\VolunteerController::class, 'removeMember'])->whereNumber('userId')->name('volunteers.members.destroy');
            Route::post('/volunteers/rota', [ChurchAdmin\VolunteerController::class, 'storeRota'])->name('volunteers.rota.store');
            Route::delete('/volunteers/rota/{volunteerRota}', [ChurchAdmin\VolunteerController::class, 'destroyRota'])->name('volunteers.rota.destroy');
        });

        // Service attendance
        Route::middleware('church.can:groups.manage|members.manage')->group(function () {
            Route::get('/attendance', [ChurchAdmin\AttendanceController::class, 'index'])->name('attendance.index');
            Route::get('/attendance/qr', [ChurchAdmin\AttendanceController::class, 'qr'])->name('attendance.qr');
            Route::get('/attendance/export', [ChurchAdmin\AttendanceController::class, 'export'])->name('attendance.export');
            Route::post('/attendance', [ChurchAdmin\AttendanceController::class, 'store'])->name('attendance.store');
            Route::delete('/attendance/{attendanceRecord}', [ChurchAdmin\AttendanceController::class, 'destroy'])->name('attendance.destroy');
        });

        // Pastoral care & counseling (confidential)
        Route::middleware('church.can:care.manage')->group(function () {
            Route::get('/care', [ChurchAdmin\CareController::class, 'index'])->name('care.index');
            Route::get('/care/members/{memberId}', [ChurchAdmin\CareController::class, 'member'])->whereNumber('memberId')->name('care.member');
            Route::post('/care/notes', [ChurchAdmin\CareController::class, 'storeNote'])->name('care.notes.store');
            Route::delete('/care/notes/{pastoralNote}', [ChurchAdmin\CareController::class, 'destroyNote'])->name('care.notes.destroy');
            Route::post('/care/welfare', [ChurchAdmin\CareController::class, 'storeWelfare'])->name('care.welfare.store');
            Route::post('/care/visits', [ChurchAdmin\CareController::class, 'storeVisit'])->name('care.visits.store');
            Route::put('/care/visits/{homeVisit}', [ChurchAdmin\CareController::class, 'updateVisit'])->name('care.visits.update');
            Route::post('/care/milestones', [ChurchAdmin\CareController::class, 'storeMilestone'])->name('care.milestones.store');
            Route::delete('/care/milestones/{discipleshipRecord}', [ChurchAdmin\CareController::class, 'destroyMilestone'])->name('care.milestones.destroy');
            Route::get('/counseling', [ChurchAdmin\CounselingController::class, 'index'])->name('counseling.index');
            Route::put('/counseling/{counselingAppointment}', [ChurchAdmin\CounselingController::class, 'update'])->name('counseling.update');
        });

        // Community moderation
        Route::middleware('church.can:prayer.moderate')->group(function () {
            Route::get('/prayer', [ChurchAdmin\PrayerController::class, 'index'])->name('prayer.index');
            Route::put('/prayer/{prayerRequest}', [ChurchAdmin\PrayerController::class, 'update'])->name('prayer.update');
            Route::post('/prayer/{prayerRequest}/approve', [ChurchAdmin\PrayerController::class, 'approve'])->name('prayer.approve');
            Route::post('/prayer/{prayerRequest}/decline', [ChurchAdmin\PrayerController::class, 'decline'])->name('prayer.decline');
            Route::post('/prayer/{prayerRequest}/respond', [ChurchAdmin\PrayerController::class, 'respond'])->name('prayer.respond');
            Route::delete('/prayer/{prayerRequest}', [ChurchAdmin\PrayerController::class, 'destroy'])->name('prayer.destroy');
        });
        Route::middleware('church.can:community.moderate')->group(function () {
            Route::get('/reports', [ChurchAdmin\ReportController::class, 'index'])->name('reports.index');
            Route::put('/reports/{contentReport}', [ChurchAdmin\ReportController::class, 'update'])->name('reports.update');
            Route::get('/testimonies', [ChurchAdmin\TestimonyController::class, 'index'])->name('testimonies.index');
            Route::put('/testimonies/{testimony}', [ChurchAdmin\TestimonyController::class, 'update'])->name('testimonies.update');
            Route::delete('/testimonies/{testimony}', [ChurchAdmin\TestimonyController::class, 'destroy'])->name('testimonies.destroy');
            Route::get('/comments', [ChurchAdmin\CommentController::class, 'index'])->name('comments.index');
            Route::put('/comments/{comment}', [ChurchAdmin\CommentController::class, 'update'])->name('comments.update');
            Route::delete('/comments/{comment}', [ChurchAdmin\CommentController::class, 'destroy'])->name('comments.destroy');
        });

        // Fun & games: live quizzes
        Route::middleware('church.can:quizzes.manage')->group(function () {
            Route::resource('quizzes', ChurchAdmin\QuizController::class)->except('show');
            Route::post('/quizzes/{quiz}/host', [ChurchAdmin\QuizController::class, 'host'])->name('quizzes.host');
            Route::get('/quiz-sessions/{quizSession}', [ChurchAdmin\QuizController::class, 'session'])->name('quizzes.session');
            Route::get('/quiz-sessions/{quizSession}/state', [ChurchAdmin\QuizController::class, 'state'])->middleware('throttle:240,1')->name('quizzes.session.state');
            Route::post('/quiz-sessions/{quizSession}/{action}', [ChurchAdmin\QuizController::class, 'action'])
                ->whereIn('action', ['start', 'reveal', 'next', 'end', 'cancel'])->name('quizzes.session.action');
        });

        // Notification composer
        Route::middleware('church.can:notifications.send')->group(function () {
            Route::get('/notifications', [ChurchAdmin\NotificationController::class, 'index'])->name('notifications.index');
            Route::get('/notifications/create', [ChurchAdmin\NotificationController::class, 'create'])->name('notifications.create');
            Route::get('/notifications/estimate', [ChurchAdmin\NotificationController::class, 'estimate'])->name('notifications.estimate');
            Route::post('/notifications', [ChurchAdmin\NotificationController::class, 'store'])->middleware('throttle:10,1')->name('notifications.store');
            Route::get('/notifications/{churchBroadcast}', [ChurchAdmin\NotificationController::class, 'show'])->name('notifications.show');
            Route::delete('/notifications/{churchBroadcast}', [ChurchAdmin\NotificationController::class, 'destroy'])->name('notifications.destroy');
        });

        // Analytics & reports
        Route::middleware('church.can:analytics.view')->group(function () {
            Route::get('/analytics', [ChurchAdmin\AnalyticsController::class, 'index'])->name('analytics');
            Route::get('/analytics/report.pdf', [ChurchAdmin\AnalyticsController::class, 'pdf'])->name('analytics.pdf');
            Route::get('/analytics/export/{dataset}', [ChurchAdmin\AnalyticsController::class, 'export'])
                ->whereIn('dataset', ['members', 'engagement', 'attendance', 'giving', 'notifications'])->name('analytics.export');
        });

        // Support tickets (any active staff member)
        Route::get('/support', [ChurchAdmin\SupportController::class, 'index'])->name('support.index');
        Route::get('/support/new', [ChurchAdmin\SupportController::class, 'create'])->name('support.create');
        Route::post('/support', [ChurchAdmin\SupportController::class, 'store'])->middleware('throttle:10,60')->name('support.store');
        Route::get('/support/{supportTicket}', [ChurchAdmin\SupportController::class, 'show'])->name('support.show');
        Route::post('/support/{supportTicket}/reply', [ChurchAdmin\SupportController::class, 'reply'])->name('support.reply');
        Route::post('/support/{supportTicket}/close', [ChurchAdmin\SupportController::class, 'close'])->name('support.close');

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

        // Billing
        Route::get('/subscriptions', [SuperAdmin\BillingController::class, 'subscriptions'])->name('subscriptions.index');
        Route::put('/subscriptions/{church:id}', [SuperAdmin\BillingController::class, 'updateSubscription'])->name('subscriptions.update');
        Route::get('/invoices', [SuperAdmin\BillingController::class, 'invoices'])->name('invoices.index');
        Route::post('/invoices', [SuperAdmin\BillingController::class, 'createInvoice'])->name('invoices.store');
        Route::post('/invoices/generate', [SuperAdmin\BillingController::class, 'generate'])->name('invoices.generate');
        Route::post('/invoices/{invoice}/paid', [SuperAdmin\BillingController::class, 'markPaid'])->name('invoices.paid');
        Route::post('/invoices/{invoice}/void', [SuperAdmin\BillingController::class, 'void'])->name('invoices.void');
        Route::get('/plans', [SuperAdmin\BillingController::class, 'plans'])->name('plans.index');
        Route::post('/plans', [SuperAdmin\BillingController::class, 'savePlan'])->name('plans.store');
        Route::put('/plans/{plan}', [SuperAdmin\BillingController::class, 'savePlan'])->name('plans.update');

        // Analytics
        Route::get('/analytics', [SuperAdmin\AnalyticsController::class, 'index'])->name('analytics');
        Route::get('/analytics/report.pdf', [SuperAdmin\AnalyticsController::class, 'pdf'])->name('analytics.pdf');
        Route::get('/analytics/churches.csv', [SuperAdmin\AnalyticsController::class, 'export'])->name('analytics.export');

        // Users
        Route::get('/users', [SuperAdmin\UserController::class, 'index'])->name('users.index');
        Route::get('/users/export', [SuperAdmin\UserController::class, 'export'])->name('users.export');
        Route::get('/users/{user}', [SuperAdmin\UserController::class, 'show'])->name('users.show');
        Route::patch('/users/{user}', [SuperAdmin\UserController::class, 'update'])->name('users.update');

        // Support, moderation, content
        Route::get('/support', [SuperAdmin\SupportController::class, 'index'])->name('support.index');
        Route::get('/support/{supportTicket}', [SuperAdmin\SupportController::class, 'show'])->name('support.show');
        Route::post('/support/{supportTicket}/reply', [SuperAdmin\SupportController::class, 'reply'])->name('support.reply');
        Route::patch('/support/{supportTicket}', [SuperAdmin\SupportController::class, 'update'])->name('support.update');
        Route::get('/moderation', [SuperAdmin\ModerationController::class, 'index'])->name('moderation.index');
        Route::patch('/moderation/{contentReport}', [SuperAdmin\ModerationController::class, 'update'])->name('moderation.update');
        Route::get('/blog', [SuperAdmin\BlogController::class, 'index'])->name('blog.index');
        Route::get('/blog/create', [SuperAdmin\BlogController::class, 'create'])->name('blog.create');
        Route::post('/blog', [SuperAdmin\BlogController::class, 'store'])->name('blog.store');
        Route::get('/blog/{blogPost:id}/edit', [SuperAdmin\BlogController::class, 'edit'])->name('blog.edit');
        Route::put('/blog/{blogPost:id}', [SuperAdmin\BlogController::class, 'update'])->name('blog.update');
        Route::delete('/blog/{blogPost:id}', [SuperAdmin\BlogController::class, 'destroy'])->name('blog.destroy');
        Route::get('/messages', [SuperAdmin\MessageController::class, 'index'])->name('messages.index');
        Route::get('/messages/{message}', [SuperAdmin\MessageController::class, 'show'])->name('messages.show');
        Route::post('/messages/{message}/reply', [SuperAdmin\MessageController::class, 'reply'])->name('messages.reply');
        Route::delete('/messages/{message}', [SuperAdmin\MessageController::class, 'destroy'])->name('messages.destroy');
        Route::get('/audit-logs', [SuperAdmin\AuditLogController::class, 'index'])->name('audit-logs');
        Route::get('/audit-logs/export', [SuperAdmin\AuditLogController::class, 'export'])->name('audit-logs.export');

        // Platform settings & gateways
        Route::get('/settings', [SuperAdmin\SettingsController::class, 'edit'])->name('settings');
        Route::put('/settings', [SuperAdmin\SettingsController::class, 'update'])->name('settings.update');
        Route::get('/settings/gateways', [SuperAdmin\SettingsController::class, 'gateways'])->name('settings.gateways');
        Route::put('/settings/gateways/{method}', [SuperAdmin\SettingsController::class, 'toggleGateway'])->name('settings.gateways.toggle');
    });

require __DIR__.'/auth.php';
