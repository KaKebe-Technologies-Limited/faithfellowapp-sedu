<?php

use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| FaithFellows API v1 (/api/v1) — see docs/API.md
|--------------------------------------------------------------------------
| Bearer tokens from POST /api/v1/auth/token (Laravel Sanctum).
*/

// Health check for the mobile app and uptime monitors: GET /api/ping (no auth, never cached).
Route::get('/ping', fn () => response()->json([
    'status' => 'ok',
    'service' => 'FaithFellows API',
    'version' => '1.0.0',
    // Which update is live: the commit and time of the build, written by deploy/shared-hosting/build.py (null in development).
    'release' => is_file(base_path('RELEASE')) ? trim((string) file_get_contents(base_path('RELEASE'))) : null,
    'time' => now()->toIso8601String(),
])->header('Cache-Control', 'no-store'))->name('api.ping');

Route::prefix('v1')->name('api.v1.')->middleware('throttle:api')->group(function () {
    Route::post('/auth/token', [V1\AuthController::class, 'token'])->middleware('throttle:60,1')->name('auth.token');
    Route::post('/auth/register', [V1\AuthController::class, 'register'])->middleware('throttle:register')->name('auth.register');
    Route::post('/auth/google', [V1\AuthController::class, 'google'])->middleware('throttle:60,1')->name('auth.google');
    Route::post('/auth/google/exchange', [V1\AuthController::class, 'exchangeGoogleCode'])->middleware('throttle:60,1')->name('auth.google.exchange');

    Route::post('/auth/password/code', [V1\PasswordResetController::class, 'sendCode'])->middleware('throttle:20,1')->name('auth.password.code');
    Route::post('/auth/password/reset', [V1\PasswordResetController::class, 'reset'])->middleware('throttle:30,1')->name('auth.password.reset');
    Route::get('/pages/{page}', [V1\PageController::class, 'show'])->name('pages.show');

    // Public directory
    Route::get('/churches', [V1\ChurchController::class, 'index'])->name('churches.index');
    Route::get('/churches/nearby', [V1\ChurchController::class, 'nearby'])->middleware(['auth:sanctum', 'api.active'])->name('churches.nearby');
    Route::get('/churches/{church}', [V1\ChurchController::class, 'show'])->name('churches.show');
    Route::get('/churches/{church}/page', [V1\ChurchController::class, 'page'])->name('churches.page');

    // A church's join link in the app: which church a code is (before there is an account), and the church the app was installed from.
    Route::get('/join/{code}', [V1\JoinController::class, 'show'])->where('code', '[A-Za-z0-9\-]+')->middleware('throttle:60,1')->name('join.show');
    Route::post('/join/match', [V1\JoinController::class, 'match'])->middleware('throttle:20,1')->name('join.match');

    Route::middleware(['auth:sanctum', 'api.active'])->group(function () {
        Route::post('/join/{code}/follow', [V1\JoinController::class, 'follow'])->where('code', '[A-Za-z0-9\-]+')->middleware('throttle:engagement')->name('join.follow');

        Route::delete('/auth/token', [V1\AuthController::class, 'revoke'])->name('auth.revoke');
        Route::get('/me', [V1\AuthController::class, 'me'])->name('me');
        Route::delete('/me', [V1\AuthController::class, 'destroy'])->middleware('throttle:5,1')->name('me.destroy');

        Route::post('/churches/{church}/subscription', [V1\ChurchController::class, 'subscribe'])->name('churches.subscribe');
        Route::delete('/churches/{church}/subscription', [V1\ChurchController::class, 'unsubscribe'])->name('churches.unsubscribe');

        Route::get('/home', V1\HomeController::class)->name('home');
        Route::post('/react/{type}/{id}', [V1\ReactionController::class, 'toggle'])->whereNumber('id')->middleware('throttle:engagement')->name('react');
        Route::get('/feed', [V1\ContentController::class, 'feed'])->name('feed');
        Route::get('/sermons', [V1\ContentController::class, 'sermons'])->name('sermons.index');
        Route::get('/sermons/browse', [V1\ContentController::class, 'sermonsBrowse'])->name('sermons.browse');
        Route::get('/sermons/{sermon}', [V1\ContentController::class, 'sermon'])->name('sermons.show');
        Route::post('/sermons/{sermon}/listen', [V1\ContentController::class, 'listen'])->middleware('throttle:60,1')->name('sermons.listen');
        Route::get('/events', [V1\ContentController::class, 'events'])->name('events.index');
        Route::get('/events/{event}', [V1\ContentController::class, 'event'])->name('events.show');
        Route::get('/tickets', [V1\EventController::class, 'tickets'])->name('tickets.index');
        Route::post('/events/{event}/rsvp', [V1\EventController::class, 'rsvp'])->middleware('throttle:engagement')->name('events.rsvp');
        Route::delete('/events/{event}/rsvp', [V1\EventController::class, 'cancel'])->name('events.rsvp.cancel');
        Route::post('/events/{event}/questions', [V1\EventController::class, 'ask'])->middleware('throttle:engagement')->name('events.ask');
        Route::post('/rsvps/{rsvp}/pay', [V1\EventController::class, 'pay'])->middleware('throttle:giving')->name('rsvps.pay');

        Route::get('/live', [V1\ContentController::class, 'live'])->name('live');
        Route::get('/live/{liveStream}', [V1\LiveController::class, 'show'])->name('live.show');
        Route::post('/live/{liveStream}/heartbeat', [V1\LiveController::class, 'heartbeat'])->middleware('throttle:120,1')->name('live.heartbeat');
        Route::get('/live/{liveStream}/messages', [V1\LiveController::class, 'messages'])->middleware('throttle:240,1')->name('live.messages');
        Route::post('/live/{liveStream}/messages', [V1\LiveController::class, 'postMessage'])->middleware('throttle:30,1')->name('live.messages.store');

        Route::get('/devotionals', [V1\ReadingController::class, 'devotionals'])->name('devotionals.index');
        Route::get('/devotionals/{devotional}', [V1\ReadingController::class, 'devotional'])->name('devotionals.show');
        Route::get('/announcements', [V1\ReadingController::class, 'announcements'])->name('announcements.index');
        Route::middleware('feature:hymns')->group(function () {
            Route::get('/hymnals', [V1\HymnController::class, 'hymnals'])->name('hymns.hymnals');
            Route::get('/hymnals/{hymnal}', [V1\HymnController::class, 'hymnal'])->whereNumber('hymnal')->name('hymns.hymnal');
            Route::get('/hymnals/{hymnal}/download', [V1\HymnController::class, 'download'])->whereNumber('hymnal')->middleware('throttle:20,1')->name('hymns.download');
            Route::get('/hymns/search', [V1\HymnController::class, 'search'])->middleware('throttle:120,1')->name('hymns.search');
            Route::get('/hymns/favorites', [V1\HymnController::class, 'favorites'])->name('hymns.favorites');
            Route::get('/hymns/{hymn}', [V1\HymnController::class, 'show'])->whereNumber('hymn')->name('hymns.show');
            Route::post('/hymns/{hymn}/favorite', [V1\HymnController::class, 'favorite'])->whereNumber('hymn')->middleware('throttle:engagement')->name('hymns.favorite');
            Route::delete('/hymns/{hymn}/favorite', [V1\HymnController::class, 'unfavorite'])->whereNumber('hymn')->name('hymns.unfavorite');
        });
        Route::middleware('feature:podcasts')->group(function () {
            Route::get('/podcasts/overview', [V1\PodcastController::class, 'overview'])->name('podcasts.overview');
            Route::get('/podcasts', [V1\PodcastController::class, 'index'])->name('podcasts.index');
            Route::get('/podcasts/{podcast}', [V1\PodcastController::class, 'show'])->whereNumber('podcast')->name('podcasts.show');
            Route::get('/podcasts/{podcast}/episodes', [V1\PodcastController::class, 'episodes'])->whereNumber('podcast')->name('podcasts.episodes');
            Route::post('/podcasts/{podcast}/follow', [V1\PodcastController::class, 'follow'])->whereNumber('podcast')->middleware('throttle:engagement')->name('podcasts.follow');
            Route::delete('/podcasts/{podcast}/follow', [V1\PodcastController::class, 'unfollow'])->whereNumber('podcast')->name('podcasts.unfollow');
            Route::post('/podcast-episodes/{episode}/played', [V1\PodcastController::class, 'played'])->middleware('throttle:120,1')->name('podcasts.played');
            // What listeners say about an episode (and, in the radio group below, about a podcast channel).
            Route::get('/podcast-episodes/{episode}/comments', [V1\PodcastCommentController::class, 'episode'])->whereNumber('episode')->middleware('throttle:240,1')->name('podcast-comments.episode');
            Route::post('/podcast-episodes/{episode}/comments', [V1\PodcastCommentController::class, 'addToEpisode'])->whereNumber('episode')->middleware('throttle:engagement')->name('podcast-comments.episode.store');
            Route::delete('/podcast-comments/{podcastComment}', [V1\PodcastCommentController::class, 'destroy'])->whereNumber('podcastComment')->name('podcast-comments.destroy');
            Route::post('/podcast-comments/{podcastComment}/report', [V1\PodcastCommentController::class, 'report'])->whereNumber('podcastComment')->middleware('throttle:20,1')->name('podcast-comments.report');
        });
        Route::middleware('feature:radio')->group(function () {
            Route::get('/radio', [V1\RadioController::class, 'show'])->middleware('throttle:120,1')->name('radio');
            Route::get('/radio/stations', [V1\RadioController::class, 'stations'])->middleware('throttle:120,1')->name('radio.stations');
            Route::get('/radio/stations/{slug}', [V1\RadioController::class, 'station'])->where('slug', '[a-z0-9-]+')->middleware('throttle:240,1')->name('radio.station');
            Route::get('/radio/stations/{slug}/comments', [V1\PodcastCommentController::class, 'station'])->where('slug', '[a-z0-9-]+')->middleware('throttle:240,1')->name('radio.station.comments');
            Route::post('/radio/stations/{slug}/comments', [V1\PodcastCommentController::class, 'addToStation'])->where('slug', '[a-z0-9-]+')->middleware('throttle:engagement')->name('radio.station.comments.store');
        });
        Route::get('/posts/{post}', [V1\ContentController::class, 'post'])->name('posts.show');
        Route::get('/announcements/{announcement}', [V1\ReadingController::class, 'announcement'])->name('announcements.show');
        Route::get('/campaigns', [V1\ReadingController::class, 'campaigns'])->name('campaigns.index');
        Route::get('/campaigns/{campaign:id}', [V1\ReadingController::class, 'campaign'])->name('campaigns.show');
        Route::post('/campaigns/{campaign:id}/comments', [V1\ReadingController::class, 'comment'])->middleware('throttle:engagement')->name('campaigns.comment');

        Route::get('/giving', [V1\GivingController::class, 'index'])->name('giving.index');
        Route::get('/giving/summary', [V1\GivingController::class, 'summary'])->name('giving.summary');
        Route::get('/churches/{church}/giving', [V1\GivingController::class, 'options'])->name('giving.options');
        Route::post('/churches/{church}/give', [V1\GivingController::class, 'store'])->middleware('throttle:giving')->name('giving.store');
        Route::get('/giving/{reference}', [V1\GivingController::class, 'show'])->middleware('throttle:60,1')->name('giving.show');
        Route::get('/giving/{reference}/receipt', [V1\GivingController::class, 'receipt'])->name('giving.receipt');
        Route::post('/churches/{church}/pledges', [V1\GivingController::class, 'storePledge'])->middleware('throttle:giving')->name('pledges.store');
        Route::delete('/pledges/{pledge}', [V1\GivingController::class, 'cancelPledge'])->name('pledges.cancel');
        Route::put('/recurring/{recurringGift}', [V1\GivingController::class, 'updateRecurring'])->name('recurring.update');

        Route::get('/prayer', [V1\CommunityController::class, 'prayers'])->name('prayer.index');
        Route::post('/prayer', [V1\CommunityController::class, 'storePrayer'])->middleware('throttle:engagement')->name('prayer.store');
        Route::post('/prayer/{prayer}/pray', [V1\CommunityController::class, 'pray'])->middleware('throttle:engagement')->name('prayer.pray');
        Route::get('/prayer/{prayer}', [V1\CommunityController::class, 'prayer'])->name('prayer.show');
        Route::post('/prayer/{prayer}/answered', [V1\CommunityController::class, 'answered'])->name('prayer.answered');
        Route::delete('/prayer/{prayer}', [V1\CommunityController::class, 'destroyPrayer'])->name('prayer.destroy');

        Route::get('/bible', [V1\BibleController::class, 'index'])->name('bible.index');
        Route::get('/bible/passage', [V1\BibleController::class, 'passage'])->name('bible.passage');
        Route::get('/bible/highlights', [V1\BibleController::class, 'highlights'])->name('bible.highlights');
        Route::post('/bible/highlights', [V1\BibleController::class, 'highlight'])->middleware('throttle:engagement')->name('bible.highlight');
        Route::put('/bible/translation', [V1\BibleController::class, 'setTranslation'])->name('bible.translation');
        Route::get('/bible/download/{book}', [V1\BibleController::class, 'download'])->middleware('throttle:120,1')->name('bible.download');
        Route::get('/bible/{book}/{chapter}', [V1\BibleController::class, 'read'])->whereNumber('chapter')->name('bible.read');

        Route::get('/notes', [V1\NoteController::class, 'index'])->name('notes.index');
        Route::get('/notes/context', [V1\NoteController::class, 'context'])->name('notes.context');
        Route::post('/notes', [V1\NoteController::class, 'store'])->middleware('throttle:engagement')->name('notes.store');
        Route::get('/notes/{note}', [V1\NoteController::class, 'show'])->name('notes.show');
        Route::patch('/notes/{note}', [V1\NoteController::class, 'update'])->name('notes.update');
        Route::post('/notes/{note}/pin', [V1\NoteController::class, 'pin'])->name('notes.pin');
        Route::delete('/notes/{note}', [V1\NoteController::class, 'destroy'])->name('notes.destroy');

        Route::get('/reading-plans', [V1\StudyController::class, 'plans'])->name('plans.index');
        Route::get('/reading-plans/{readingPlan}', [V1\StudyController::class, 'showPlan'])->name('plans.show');
        Route::post('/reading-plans/{readingPlan}/start', [V1\StudyController::class, 'start'])->name('plans.start');
        Route::post('/reading-plans/{readingPlan}/complete', [V1\StudyController::class, 'complete'])->name('plans.complete');
        Route::delete('/reading-plans/{readingPlan}', [V1\StudyController::class, 'leave'])->name('plans.leave');
        Route::post('/reading-plans/{readingPlan}/circles', [V1\StudyController::class, 'createCircle'])->middleware('throttle:engagement')->name('circles.store');
        Route::post('/reading-circles/join', [V1\StudyController::class, 'joinCircle'])->middleware('throttle:10,1')->name('circles.join');
        Route::get('/reading-circles/{readingCircle}', [V1\StudyController::class, 'showCircle'])->name('circles.show');
        Route::put('/reading-circles/{readingCircle}', [V1\StudyController::class, 'updateCircle'])->name('circles.update');
        Route::post('/reading-circles/{readingCircle}/days/{day}', [V1\StudyController::class, 'completeCircleDay'])->whereNumber('day')->name('circles.complete');
        Route::delete('/reading-circles/{readingCircle}/leave', [V1\StudyController::class, 'leaveCircle'])->name('circles.leave');

        Route::patch('/me', [V1\ProfileController::class, 'update'])->name('me.update');
        Route::post('/me/activity', [V1\MemberActivityController::class, 'store'])->middleware('throttle:120,1')->name('me.activity');
        Route::get('/me/stats', [V1\MemberActivityController::class, 'show'])->name('me.stats');
        Route::put('/me/location', [V1\ProfileController::class, 'location'])->middleware('throttle:20,1')->name('me.location');
        Route::delete('/me/location', [V1\ProfileController::class, 'removeLocation'])->name('me.location.remove');
        Route::post('/me/avatar', [V1\ProfileController::class, 'avatar'])->middleware('throttle:10,1')->name('me.avatar');
        Route::delete('/me/avatar', [V1\ProfileController::class, 'removeAvatar'])->name('me.avatar.remove');
        Route::put('/me/password', [V1\ProfileController::class, 'password'])->middleware('throttle:6,1')->name('me.password');

        Route::get('/games', [V1\GameController::class, 'index'])->name('games.index');
        Route::post('/games/join', [V1\GameController::class, 'join'])->middleware('throttle:20,1')->name('games.join');
        Route::get('/games/library', [V1\CommunityGameController::class, 'library'])->name('games.library');
        Route::get('/games/friends', [V1\CommunityGameController::class, 'friends'])->name('games.friends');
        Route::post('/games/start', [V1\CommunityGameController::class, 'start'])->middleware('throttle:20,1')->name('games.start');
        Route::post('/games/{quizSession}/invite', [V1\CommunityGameController::class, 'invite'])->middleware('throttle:30,1')->name('games.invite');
        Route::post('/games/{quizSession}/begin', [V1\CommunityGameController::class, 'begin'])->middleware('throttle:30,1')->name('games.begin');
        Route::post('/games/{quizSession}/cancel', [V1\CommunityGameController::class, 'cancel'])->middleware('throttle:30,1')->name('games.cancel');
        Route::get('/games/{quizSession}', [V1\GameController::class, 'state'])->middleware('throttle:300,1')->name('games.state');
        Route::post('/games/{quizSession}/answer', [V1\GameController::class, 'answer'])->middleware('throttle:60,1')->name('games.answer');

        Route::get('/groups', [V1\GroupController::class, 'index'])->name('groups.index');
        Route::get('/groups/{group}', [V1\GroupController::class, 'show'])->name('groups.show');
        Route::post('/groups/{group}/join', [V1\GroupController::class, 'join'])->middleware('throttle:engagement')->name('groups.join');
        Route::delete('/groups/{group}/leave', [V1\GroupController::class, 'leave'])->name('groups.leave');
        Route::get('/groups/{group}/posts', [V1\GroupController::class, 'posts'])->middleware('throttle:240,1')->name('groups.posts');
        Route::post('/groups/{group}/posts', [V1\GroupController::class, 'storePost'])->middleware('throttle:30,1')->name('groups.posts.store');
        Route::delete('/groups/{group}/posts/{post}', [V1\GroupController::class, 'destroyPost'])->name('groups.posts.destroy');
        Route::get('/groups/{group}/candidates', [V1\GroupController::class, 'candidates'])->name('groups.candidates');
        Route::post('/groups/{group}/members', [V1\GroupController::class, 'addMember'])->name('groups.members.add');
        Route::put('/groups/{group}/members/{membership}', [V1\GroupController::class, 'updateMember'])->name('groups.members.update');
        Route::post('/groups/{group}/meetings', [V1\GroupController::class, 'storeMeeting'])->name('groups.meetings.store');
        Route::post('/groups/{group}/announcements', [V1\GroupController::class, 'storeAnnouncement'])->name('groups.announcements.store');

        Route::get('/people', [V1\PeopleController::class, 'index'])->name('people.index');
        Route::get('/people/blocked', [V1\PeopleController::class, 'blocked'])->name('people.blocked');
        Route::get('/people/{user}', [V1\PeopleController::class, 'show'])->name('people.show');
        Route::post('/people/{user}/connect', [V1\PeopleController::class, 'connect'])->middleware('throttle:30,1')->name('people.connect');
        Route::delete('/people/{user}/connection', [V1\PeopleController::class, 'disconnect'])->name('people.disconnect');
        Route::post('/people/{user}/block', [V1\PeopleController::class, 'block'])->name('people.block');
        Route::delete('/people/{user}/block', [V1\PeopleController::class, 'unblock'])->name('people.unblock');
        Route::get('/connections', [V1\PeopleController::class, 'connections'])->name('connections.index');
        Route::post('/connections/{connection}/respond', [V1\PeopleController::class, 'respond'])->name('connections.respond');

        Route::get('/messages', [V1\MessageController::class, 'index'])->name('messages.index');
        Route::get('/messages/unread', [V1\MessageController::class, 'unread'])->name('messages.unread');
        Route::get('/messages/{user}', [V1\MessageController::class, 'thread'])->middleware('throttle:300,1')->name('messages.thread');
        Route::post('/messages/{user}', [V1\MessageController::class, 'send'])->middleware('throttle:60,1')->name('messages.send');

        // Church staff: running the church from the app (the same permissions as the website's admin area).
        Route::get('/admin/churches', [V1\Admin\DashboardController::class, 'churches'])->name('admin.churches');
        Route::prefix('admin/{church}')->name('admin.')->middleware('church.staff')->scopeBindings()->group(function () {
            Route::get('/dashboard', [V1\Admin\DashboardController::class, 'dashboard'])->name('dashboard');
            Route::get('/invite', [V1\Admin\InviteController::class, 'show'])->name('invite.show');

            Route::middleware('church.can:content.manage')->group(function () {
                Route::get('/announcements', [V1\Admin\ContentController::class, 'announcements'])->name('announcements.index');
                Route::post('/announcements', [V1\Admin\ContentController::class, 'storeAnnouncement'])->name('announcements.store');
                Route::delete('/announcements/{announcement}', [V1\Admin\ContentController::class, 'destroyAnnouncement'])->name('announcements.destroy');
                Route::get('/posts', [V1\Admin\PostController::class, 'index'])->name('posts.index');
                Route::post('/posts', [V1\Admin\PostController::class, 'store'])->middleware('throttle:20,1')->name('posts.store');
                Route::put('/posts/{post}', [V1\Admin\PostController::class, 'update'])->name('posts.update');
                Route::delete('/posts/{post}', [V1\Admin\PostController::class, 'destroy'])->name('posts.destroy');
                Route::get('/devotionals', [V1\Admin\ContentController::class, 'devotionals'])->name('devotionals.index');
                Route::post('/devotionals', [V1\Admin\ContentController::class, 'storeDevotional'])->name('devotionals.store');
                Route::delete('/devotionals/{devotional}', [V1\Admin\ContentController::class, 'destroyDevotional'])->name('devotionals.destroy');
                Route::get('/sermons', [V1\Admin\ContentController::class, 'sermons'])->name('sermons.index');
                Route::post('/sermons', [V1\Admin\ContentController::class, 'storeSermon'])->name('sermons.store');
                Route::delete('/sermons/{sermon}', [V1\Admin\ContentController::class, 'destroySermon'])->name('sermons.destroy');
            });

            Route::middleware('church.can:events.manage')->group(function () {
                Route::get('/events', [V1\Admin\EventController::class, 'index'])->name('events.index');
                Route::post('/events', [V1\Admin\EventController::class, 'store'])->name('events.store');
                Route::get('/events/{event}', [V1\Admin\EventController::class, 'show'])->name('events.show');
                Route::put('/events/{event}', [V1\Admin\EventController::class, 'update'])->name('events.update');
                Route::delete('/events/{event}', [V1\Admin\EventController::class, 'destroy'])->name('events.destroy');
                Route::post('/events/{event}/check-in', [V1\Admin\EventController::class, 'checkIn'])->middleware('throttle:120,1')->name('events.check-in');
            });

            Route::middleware('church.can:prayer.moderate')->group(function () {
                Route::get('/prayer', [V1\Admin\CommunityController::class, 'prayers'])->name('prayer.index');
                Route::post('/prayer/{prayerRequest}/approve', [V1\Admin\CommunityController::class, 'approvePrayer'])->name('prayer.approve');
                Route::post('/prayer/{prayerRequest}/decline', [V1\Admin\CommunityController::class, 'declinePrayer'])->name('prayer.decline');
                Route::post('/prayer/{prayerRequest}/respond', [V1\Admin\CommunityController::class, 'respondPrayer'])->name('prayer.respond');
                Route::post('/prayer/{prayerRequest}/hide', [V1\Admin\CommunityController::class, 'hidePrayer'])->name('prayer.hide');
            });

            Route::middleware('church.can:members.manage')->group(function () {
                Route::get('/members', [V1\Admin\CommunityController::class, 'members'])->name('members.index');
                Route::put('/members/{subscription}', [V1\Admin\CommunityController::class, 'updateMember'])->name('members.update');
            });

            Route::middleware('church.can:notifications.send')->group(function () {
                Route::get('/broadcasts', [V1\Admin\CommunityController::class, 'broadcasts'])->name('broadcasts.index');
                Route::get('/broadcasts/estimate', [V1\Admin\CommunityController::class, 'estimate'])->name('broadcasts.estimate');
                Route::post('/broadcasts', [V1\Admin\CommunityController::class, 'sendBroadcast'])->middleware('throttle:10,1')->name('broadcasts.store');
            });

            Route::middleware('church.can:community.moderate')->group(function () {
                Route::get('/reports', [V1\Admin\CommunityController::class, 'reports'])->name('reports.index');
                Route::post('/reports/{contentReport}/hide', [V1\Admin\CommunityController::class, 'hideReported'])->name('reports.hide');
                Route::post('/reports/{contentReport}/dismiss', [V1\Admin\CommunityController::class, 'dismissReport'])->name('reports.dismiss');
            });

            Route::middleware('church.can:quizzes.manage')->group(function () {
                Route::get('/quizzes', [V1\Admin\GamesController::class, 'index'])->name('quizzes.index');
                Route::post('/quizzes', [V1\Admin\GamesController::class, 'store'])->name('quizzes.store');
                Route::get('/quizzes/{quiz}', [V1\Admin\GamesController::class, 'show'])->name('quizzes.show');
                Route::put('/quizzes/{quiz}', [V1\Admin\GamesController::class, 'update'])->name('quizzes.update');
                Route::delete('/quizzes/{quiz}', [V1\Admin\GamesController::class, 'destroy'])->name('quizzes.destroy');
                Route::post('/quizzes/{quiz}/host', [V1\Admin\GamesController::class, 'host'])->name('quizzes.host');
                Route::get('/quiz-sessions/{quizSession}', [V1\Admin\GamesController::class, 'state'])->middleware('throttle:300,1')->name('quiz-sessions.state');
                Route::post('/quiz-sessions/{quizSession}/{action}', [V1\Admin\GamesController::class, 'action'])->name('quiz-sessions.action');
            });

            Route::middleware('church.can:live.manage')->group(function () {
                Route::get('/live', [V1\Admin\LiveController::class, 'index'])->name('live.index');
                Route::post('/live', [V1\Admin\LiveController::class, 'store'])->name('live.store');
                Route::post('/live/{liveStream}/start', [V1\Admin\LiveController::class, 'start'])->name('live.start');
                Route::post('/live/{liveStream}/stop', [V1\Admin\LiveController::class, 'stop'])->name('live.stop');
            });

            Route::middleware('church.can:giving.view')->group(function () {
                Route::get('/giving/summary', [V1\Admin\GivingController::class, 'summary'])->name('giving.summary');
                Route::get('/giving', [V1\Admin\GivingController::class, 'index'])->name('giving.index');
                Route::get('/giving/reasons', [V1\Admin\GivingReasonController::class, 'index'])->name('giving.reasons.index');
            });
            Route::middleware('church.can:giving.manage')->group(function () {
                Route::post('/giving/reasons', [V1\Admin\GivingReasonController::class, 'store'])->name('giving.reasons.store');
                Route::put('/giving/reasons/{givingReason}', [V1\Admin\GivingReasonController::class, 'update'])->name('giving.reasons.update');
                Route::delete('/giving/reasons/{givingReason}', [V1\Admin\GivingReasonController::class, 'destroy'])->name('giving.reasons.destroy');
            });
        });
        Route::get('/notifications', [V1\CommunityController::class, 'notifications'])->name('notifications.index');
        Route::post('/notifications/read', [V1\CommunityController::class, 'readNotifications'])->name('notifications.read');
        Route::get('/notification-settings', [V1\NotificationSettingsController::class, 'show'])->name('notification-settings.show');
        Route::put('/notification-settings', [V1\NotificationSettingsController::class, 'update'])->name('notification-settings.update');

        // Push notifications to the phone app (Expo push tokens).
        Route::post('/devices', [V1\DeviceController::class, 'store'])->middleware('throttle:30,1')->name('devices.store');
        Route::delete('/devices', [V1\DeviceController::class, 'destroy'])->name('devices.destroy');
        Route::post('/devices/test', [V1\DeviceController::class, 'test'])->middleware('throttle:5,1')->name('devices.test');
    });
});
