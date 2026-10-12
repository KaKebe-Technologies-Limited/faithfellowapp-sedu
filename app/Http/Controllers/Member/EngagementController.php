<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Campaign;
use App\Models\CampaignUpdate;
use App\Models\Church;
use App\Models\Comment;
use App\Models\Post;
use App\Models\PrayerRequest;
use App\Models\Reaction;
use App\Models\Sermon;
use App\Models\Testimony;
use App\Models\VolunteerRota;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Reactions & comments on any church content, service check-in, volunteer rota.
 */
class EngagementController extends Controller
{
    /** Content types members can react to / comment on. */
    protected const TYPES = [
        'post' => Post::class,
        'sermon' => Sermon::class,
        'testimony' => Testimony::class,
        'campaign' => Campaign::class,
        'campaign_update' => CampaignUpdate::class,
        'prayer' => PrayerRequest::class,
    ];

    protected function resolve(string $type, int $id): Model
    {
        $class = self::TYPES[$type] ?? abort(404);
        $model = $class::with('church')->findOrFail($id);
        $user = request()->user();

        $allowed = match (true) {
            $model instanceof PrayerRequest => $user->can('view', $model),
            $model instanceof Testimony => $model->status === 'approved' && $model->church?->isActive(),
            default => $model->church?->isActive() && ($user->isMemberOf($model->church_id) || ($model->visibility ?? null) === 'public'),
        };
        abort_unless($allowed, 403);

        return $model;
    }

    public function react(Request $request, string $type, int $id): JsonResponse
    {
        $model = $this->resolve($type, $id);
        $data = $request->validate(['type' => ['required', Rule::in(array_keys(Reaction::TYPES))]]);

        $existing = Reaction::where('reactable_type', $model->getMorphClass())->where('reactable_id', $model->getKey())->where('user_id', $request->user()->id)->first();

        if ($existing && $existing->type === $data['type']) {
            $existing->delete();
            $mine = null;
        } else {
            Reaction::updateOrCreate(
                ['reactable_type' => $model->getMorphClass(), 'reactable_id' => $model->getKey(), 'user_id' => $request->user()->id],
                ['church_id' => $model->church_id, 'type' => $data['type']],
            );
            $mine = $data['type'];
        }

        return response()->json([
            'counts' => Reaction::where('reactable_type', $model->getMorphClass())->where('reactable_id', $model->getKey())
                ->selectRaw('type, count(*) c')->groupBy('type')->pluck('c', 'type'),
            'mine' => $mine,
        ]);
    }

    public function comment(Request $request, string $type, int $id): RedirectResponse
    {
        $model = $this->resolve($type, $id);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
            'parent_id' => ['nullable', Rule::exists('comments', 'id')->where('commentable_type', $model->getMorphClass())->where('commentable_id', $model->getKey())],
        ]);

        Comment::create([
            'church_id' => $model->church_id, 'commentable_type' => $model->getMorphClass(), 'commentable_id' => $model->getKey(),
            'user_id' => $request->user()->id, 'body' => strip_tags($data['body']), 'parent_id' => $data['parent_id'] ?? null,
        ]);

        return back()->with('success', __('Comment posted.'));
    }

    public function deleteComment(Request $request, Comment $comment): RedirectResponse
    {
        abort_unless($comment->user_id === $request->user()->id || $request->user()->canInChurch($comment->church_id, 'community.moderate'), 403);
        $comment->delete();

        return back();
    }

    /**
     * Service check-in from the church's QR code (signed link) or geofence.
     */
    public function checkInPage(Request $request, Church $church): View
    {
        abort_unless($request->hasValidSignature() || $request->user()->isMemberOf($church), 403);

        return view('member.check-in', [
            'church' => $church,
            'already' => AttendanceRecord::where('church_id', $church->id)->where('user_id', $request->user()->id)->whereDate('service_date', today())->exists(),
            'signedUrl' => $request->hasValidSignature() ? $request->fullUrl() : null,
        ]);
    }

    public function checkIn(Request $request, Church $church): JsonResponse|RedirectResponse
    {
        $viaQr = $request->filled('signature') && URL::hasValidSignature($request);
        $method = $viaQr ? 'qr' : 'geofence';

        if (! $viaQr) {
            $data = $request->validate(['lat' => ['required', 'numeric'], 'lng' => ['required', 'numeric']]);
            $distance = $church->lat ? $this->distanceMeters($church->lat, $church->lng, (float) $data['lat'], (float) $data['lng']) : PHP_INT_MAX;
            abort_if($distance > (int) $church->setting('checkin_radius', 250), 422, __('You seem to be :m m from church. Check in when you arrive.', ['m' => $distance === PHP_INT_MAX ? '?' : round($distance)]));
        }

        AttendanceRecord::firstOrCreate(
            ['church_id' => $church->id, 'user_id' => $request->user()->id, 'service_date' => today()->toDateString()],
            ['method' => $method],
        );

        $message = __('Welcome to :church! You are checked in.', ['church' => $church->name]);

        return $request->expectsJson() ? response()->json(['message' => $message]) : back()->with('success', $message);
    }

    public function rota(Request $request): View
    {
        return view('member.volunteering', [
            'rotas' => VolunteerRota::with(['team', 'church'])->where('user_id', $request->user()->id)->whereDate('service_date', '>=', today())->orderBy('service_date')->get(),
        ]);
    }

    public function respondRota(Request $request, VolunteerRota $rota): RedirectResponse
    {
        abort_unless($rota->user_id === $request->user()->id, 403);
        $rota->update($request->validate(['status' => ['required', Rule::in(['confirmed', 'declined'])]]));

        return back()->with('success', $rota->status === 'confirmed' ? __('Thank you for serving!') : __('Your team leader has been notified.'));
    }

    protected function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }
}
