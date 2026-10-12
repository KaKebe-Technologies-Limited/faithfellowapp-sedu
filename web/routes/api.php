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

    // Public directory
    Route::get('/churches', [V1\ChurchController::class, 'index'])->name('churches.index');
    Route::get('/churches/{church}', [V1\ChurchController::class, 'show'])->name('churches.show');

    Route::middleware(['auth:sanctum', 'api.active'])->group(function () {
        Route::delete('/auth/token', [V1\AuthController::class, 'revoke'])->name('auth.revoke');
        Route::get('/me', [V1\AuthController::class, 'me'])->name('me');

        Route::post('/churches/{church}/subscription', [V1\ChurchController::class, 'subscribe'])->name('churches.subscribe');
        Route::delete('/churches/{church}/subscription', [V1\ChurchController::class, 'unsubscribe'])->name('churches.unsubscribe');

        Route::get('/feed', [V1\ContentController::class, 'feed'])->name('feed');
        Route::get('/sermons', [V1\ContentController::class, 'sermons'])->name('sermons.index');
        Route::get('/sermons/{sermon}', [V1\ContentController::class, 'sermon'])->name('sermons.show');
        Route::get('/events', [V1\ContentController::class, 'events'])->name('events.index');
        Route::get('/events/{event}', [V1\ContentController::class, 'event'])->name('events.show');
        Route::get('/live', [V1\ContentController::class, 'live'])->name('live');

        Route::get('/giving', [V1\GivingController::class, 'index'])->name('giving.index');
        Route::post('/churches/{church}/give', [V1\GivingController::class, 'store'])->middleware('throttle:giving')->name('giving.store');
        Route::get('/giving/{reference}', [V1\GivingController::class, 'show'])->middleware('throttle:60,1')->name('giving.show');

        Route::get('/prayer', [V1\CommunityController::class, 'prayers'])->name('prayer.index');
        Route::post('/prayer', [V1\CommunityController::class, 'storePrayer'])->middleware('throttle:engagement')->name('prayer.store');
        Route::post('/prayer/{prayer}/pray', [V1\CommunityController::class, 'pray'])->middleware('throttle:engagement')->name('prayer.pray');

        Route::get('/notifications', [V1\CommunityController::class, 'notifications'])->name('notifications.index');
        Route::post('/notifications/read', [V1\CommunityController::class, 'readNotifications'])->name('notifications.read');
    });
});
