<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast channels (used when BROADCAST_CONNECTION=reverb/pusher)
|--------------------------------------------------------------------------
*/

// Real-time in-app notifications for one user.
Broadcast::channel('App.Models.User.{id}', fn (User $user, int $id) => $user->id === $id);

// Live giving feed for a church's finance team.
Broadcast::channel('church.{churchId}.finance', fn (User $user, int $churchId) => $user->canInChurch($churchId, 'giving.view'));
