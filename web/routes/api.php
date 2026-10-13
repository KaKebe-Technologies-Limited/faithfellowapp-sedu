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
    'time' => now()->toIso8601String(),
])->header('Cache-Control', 'no-store'))->name('api.ping');

Route::prefix('v1')->name('api.v1.')->middleware('throttle:api')->group(function () {
    Route::post('/auth/token', [V1\AuthController::class, 'token'])->middleware('throttle:10,1')->name('auth.token');
    Route::post('/auth/register', [V1\AuthController::class, 'register'])->middleware('throttle:register')->name('auth.register');
    Route::post('/auth/google', [V1\AuthController::class, 'google'])->middleware('throttle:10,1')->name('auth.google');
    Route::post('/auth/google/exchange', [V1\AuthController::class, 'exchangeGoogleCode'])->middleware('throttle:10,1')->name('auth.google.exchange');

    Route::post('/auth/password/code', [V1\PasswordResetController::class, 'sendCode'])->middleware('throttle:5,1')->name('auth.password.code');
    Route::post('/auth/password/reset', [V1\PasswordResetController::class, 'reset'])->middleware('throttle:10,1')->name('auth.password.reset');
    Route::get('/pages/{page}', [V1\PageController::class, 'show'])->name('pages.show');

    // Public directory
    Route::get('/churches', [V1\ChurchController::class, 'index'])->name('churches.index');
    Route::get('/churches/{church}', [V1\ChurchController::class, 'show'])->name('churches.show');

    Route::middleware(['auth:sanctum', 'api.active'])->group(function () {
        Route::delete('/auth/token', [V1\AuthController::class, 'revoke'])->name('auth.revoke');
        Route::get('/me', [V1\AuthController::class, 'me'])->name('me');

        Route::post('/churches/{church}/subscription', [V1\ChurchController::class, 'subscribe'])->name('churches.subscribe');
        Route::delete('/churches/{church}/subscription', [V1\ChurchController::class, 'unsubscribe'])->name('churches.unsubscribe');

        Route::get('/home', V1\HomeController::class)->name('home');
        Route::get('/feed', [V1\ContentController::class, 'feed'])->name('feed');
        Route::get('/sermons', [V1\ContentController::class, 'sermons'])->name('sermons.index');
        Route::get('/sermons/{sermon}', [V1\ContentController::class, 'sermon'])->name('sermons.show');
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
        Route::post('/me/avatar', [V1\ProfileController::class, 'avatar'])->middleware('throttle:10,1')->name('me.avatar');
        Route::delete('/me/avatar', [V1\ProfileController::class, 'removeAvatar'])->name('me.avatar.remove');
        Route::put('/me/password', [V1\ProfileController::class, 'password'])->middleware('throttle:6,1')->name('me.password');

        Route::get('/games', [V1\GameController::class, 'index'])->name('games.index');
        Route::post('/games/join', [V1\GameController::class, 'join'])->middleware('throttle:20,1')->name('games.join');
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

        Route::get('/notifications', [V1\CommunityController::class, 'notifications'])->name('notifications.index');
        Route::post('/notifications/read', [V1\CommunityController::class, 'readNotifications'])->name('notifications.read');
    });
});
