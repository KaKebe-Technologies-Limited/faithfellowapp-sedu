<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MemberActivity;
use App\Services\MemberStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A member's own activity and how it adds up: the app reports minutes of Bible reading and listening and what was shared,
 * and the profile shows the progress. Private to the member.
 */
class MemberActivityController extends Controller
{
    public function __construct(protected MemberStats $stats) {}

    /** Body: { events: [{ kind, seconds | count, day? }] }. The app sends a small batch every minute or so. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:40'],
            'events.*.kind' => ['required', Rule::in(array_keys(MemberActivity::KINDS))],
            'events.*.seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'events.*.count' => ['nullable', 'integer', 'min:0', 'max:50'],
            'events.*.day' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return response()->json(['recorded' => $this->stats->record($request->user(), $data['events'])]);
    }

    /** `period` is week (default), month or all. */
    public function show(Request $request): JsonResponse
    {
        $data = $request->validate(['period' => ['nullable', Rule::in(['week', 'month', 'all'])]]);

        return response()->json(['data' => $this->stats->summary($request->user(), $data['period'] ?? 'week')]);
    }
}
