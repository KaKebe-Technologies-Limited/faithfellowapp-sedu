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

        Route::get('/notifications', [V1\CommunityController::class, 'notifications'])->name('notifications.index');
        Route::post('/notifications/read', [V1\CommunityController::class, 'readNotifications'])->name('notifications.read');
    });
});
