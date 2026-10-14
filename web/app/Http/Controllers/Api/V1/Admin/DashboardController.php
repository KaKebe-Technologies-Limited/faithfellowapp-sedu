<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\ChurchRole;
use App\Models\Donation;
use App\Models\PrayerRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Church staff in the app: which churches the member helps run, and each church's dashboard — what needs
 * attention today, filtered to what the staff member is allowed to see.
 */
class DashboardController extends Controller
{
    /** The churches this member is staff of, with their permissions (empty for ordinary members). */
    public function churches(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return response()->json(['data' => $this->allChurches($request)]);
        }

        return response()->json(['data' => $user->staffMemberships()->where('status', 'active')->with('church.media')->get()
            ->filter(fn ($s) => $s->church && $s->church->status !== 'archived')
            ->map(fn ($s) => [
                'church' => ['id' => $s->church->id, 'slug' => $s->church->slug, 'name' => $s->church->name, 'logo_url' => $s->church->logo_url],
                'title' => $s->title ?: ($s->is_admin ? __('Church admin') : ($s->role?->name ?? __('Staff'))),
                'is_admin' => (bool) $s->is_admin,
                'permissions' => $user->churchPermissions($s->church) ?? [],
            ])->values()]);
    }

    /** A super admin can manage any church: the active ones, found by name or town, 40 at a time. */
    protected function allChurches(Request $request): array
    {
        $q = trim((string) $request->query('q'));

        return Church::query()->where('status', 'active')->with('media')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('city', 'like', "%{$q}%")->orWhere('district', 'like', "%{$q}%")))
            ->orderBy('name')->limit(40)->get()
            ->map(fn (Church $church) => [
                'church' => ['id' => $church->id, 'slug' => $church->slug, 'name' => $church->name, 'logo_url' => $church->logo_url],
                'title' => __('Super admin'),
                'is_admin' => true,
                'permissions' => ChurchRole::catalog(),
            ])->values()->all();
    }

    public function dashboard(Request $request, Church $church): JsonResponse
    {
        $user = $request->user();
        $can = fn (string $permission) => $user->canInChurch($church, $permission);

        $members = $church->subscriptions()->where('status', 'active');
        $month = Donation::where('church_id', $church->id)->succeeded()->where('paid_at', '>=', now()->startOfMonth());
        $live = $church->liveStreams()->where('status', 'live')->first();

        return response()->json(['data' => [
            'church' => ['id' => $church->id, 'slug' => $church->slug, 'name' => $church->name, 'logo_url' => $church->logo_url],
            'is_admin' => $user->isChurchAdmin($church),
            'permissions' => $user->churchPermissions($church) ?? [],
            'stats' => [
                'members' => $members->count(),
                'new_this_month' => (clone $members)->where('subscribed_at', '>=', now()->subDays(30))->count(),
                'pending_members' => $can('members.manage') ? $church->subscriptions()->where('status', 'pending')->count() : null,
                'pending_prayers' => $can('prayer.moderate') ? $church->prayerRequests()->where('status', 'pending')->count() : null,
                'private_prayers' => $can('prayer.moderate') ? $church->prayerRequests()->where('visibility', 'pastor')->where('status', 'open')->whereNull('responded_at')->count() : null,
                'open_reports' => $can('community.moderate') ? $church->contentReports()->where('status', 'open')->count() : null,
                'upcoming_events' => $can('events.manage') ? $church->events()->published()->upcoming()->count() : null,
                'giving_month' => $can('giving.view') ? (float) $month->sum('amount') : null,
                'gifts_month' => $can('giving.view') ? (int) $month->count() : null,
                'currency' => config('faithfellows.currency'),
            ],
            'live' => $live && $can('live.manage') ? ['id' => $live->id, 'title' => $live->title] : null,
            'next_event' => $can('events.manage') && ($e = $church->events()->published()->upcoming()->orderBy('starts_at')->first())
                ? ['id' => $e->id, 'title' => $e->title, 'starts_at' => $e->starts_at?->toIso8601String(), 'going' => (int) $e->rsvps()->where('status', 'going')->sum('quantity')]
                : null,
            'recent_prayer' => $can('prayer.moderate') ? PrayerRequest::where('church_id', $church->id)->where('status', 'pending')->latest()->take(3)->get(['id', 'title', 'body'])->map(fn ($p) => ['id' => $p->id, 'title' => $p->title ?: str($p->body)->limit(60)->toString()])->values() : [],
        ]]);
    }
}
