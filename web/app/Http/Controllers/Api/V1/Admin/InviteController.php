<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Services\JoinLinks;
use Illuminate\Http\JsonResponse;

/**
 * The church's invite link in the app, for staff to share: the link, its code, a ready-made message and how many people came through it.
 */
class InviteController extends Controller
{
    public function show(Church $church, JoinLinks $links): JsonResponse
    {
        $url = $links->url($church);

        return response()->json(['data' => [
            'ready' => JoinLinks::ready(),
            'code' => $church->join_code,
            'url' => $url,
            'message' => $url ? __('Join :church on the FaithFellows app: sermons, events, prayer and giving in one place. Tap to join: :url', ['church' => $church->name, 'url' => $url]) : null,
            'stats' => $links->stats($church),
        ]]);
    }
}
