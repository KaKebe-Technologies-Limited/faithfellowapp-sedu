<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\ContentReport;
use App\Models\PrayerRequest;
use App\Services\ModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "Report" on posts, comments, prayer requests, testimonies and live chat.
 */
class ReportController extends Controller
{
    public function store(Request $request, string $type, int $id, ModerationService $moderation): JsonResponse|RedirectResponse
    {
        $class = ContentReport::TYPES[$type] ?? abort(404);
        $content = $class::with('church')->findOrFail($id);

        // You can only report what you can see: members of the church (or public content).
        abort_unless($content instanceof PrayerRequest
            ? $request->user()->can('view', $content)
            : $request->user()->isMemberOf($content->church_id) || ($content->visibility ?? null) === 'public', 403);
        abort_if(($content->user_id ?? null) === $request->user()->id, 422, __('You cannot report your own post.'));

        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(ContentReport::REASONS))],
            'details' => ['nullable', 'string', 'max:500'],
        ]);
        $moderation->report($request->user(), $content, $data);

        $message = __('Thank you. The church moderators will review this.');

        return $request->expectsJson() ? response()->json(['message' => $message]) : back()->with('success', $message);
    }
}
