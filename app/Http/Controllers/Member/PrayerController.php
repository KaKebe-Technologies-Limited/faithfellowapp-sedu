<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\PrayerInteraction;
use App\Models\PrayerRequest;
use App\Services\PrayerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PrayerController extends Controller
{
    public function __construct(protected PrayerService $prayers) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $tab = $request->query('tab', 'wall');
        $memberIds = $user->memberChurchIds();

        $query = PrayerRequest::with(['church.media', 'user.media'])
            ->visibleTo($user, $memberIds, $user->pastoralChurchIds())
            ->whereIn('church_id', $memberIds ?: [0])
            ->when($request->filled('church'), fn ($q) => $q->whereHas('church', fn ($c) => $c->where('slug', $request->query('church'))));

        $prayers = match ($tab) {
            'answered' => $query->where('status', 'answered')->latest('answered_at'),
            'mine' => PrayerRequest::with(['church.media'])->where('user_id', $user->id)->latest(),
            default => $query->where('status', 'open')->latest(),
        };

        $prayers = $prayers->paginate(18)->withQueryString();
        $myPrayed = PrayerInteraction::where('user_id', $user->id)->where('type', 'prayed')->whereIn('prayer_request_id', $prayers->pluck('id'))->pluck('prayer_request_id')->all();
        $chainAsks = PrayerInteraction::with('prayerRequest.church')->where('user_id', $user->id)->where('type', 'chain')->whereNull('completed_at')->get();

        return view('member.prayer.index', [
            'prayers' => $prayers,
            'myPrayed' => $myPrayed,
            'chainAsks' => $chainAsks,
            'tab' => $tab,
            'churches' => Church::whereIn('id', $memberIds)->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function create(Request $request): View
    {
        return view('member.prayer.create', [
            'churches' => Church::whereIn('id', $request->user()->memberChurchIds())->orderBy('name')->pluck('name', 'id'),
            'selected' => $request->query('church'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'church_id' => ['required', Rule::in($request->user()->memberChurchIds())],
            'title' => ['nullable', 'string', 'max:160'],
            'body' => ['required', 'string', 'min:5', 'max:2000'],
            'visibility' => ['required', Rule::in(['public', 'church', 'pastor'])],
            'is_anonymous' => ['boolean'],
            'chain_enabled' => ['boolean'],
        ]);

        $prayer = $this->prayers->submit($request->user(), Church::findOrFail($data['church_id']), $data);

        return redirect()->route('prayer.show', $prayer)->with('success', __('Your request has been shared. You are not alone — people are praying with you.'));
    }

    public function show(Request $request, PrayerRequest $prayer): View
    {
        $this->authorize('view', $prayer);
        $prayer->load(['church.media', 'user.media']);

        return view('member.prayer.show', [
            'prayer' => $prayer,
            'prayed' => $request->user() && PrayerInteraction::where('prayer_request_id', $prayer->id)->where('user_id', $request->user()->id)->where('type', 'prayed')->exists(),
            'prayers' => $prayer->interactions()->with('user.media')->where('type', 'prayed')->latest()->take(12)->get(),
            'chainLinks' => $prayer->chain_enabled ? $prayer->interactions()->where('type', 'chain')->count() : 0,
        ]);
    }

    /**
     * "I prayed for you" toggle (AJAX).
     */
    public function pray(Request $request, PrayerRequest $prayer): JsonResponse
    {
        $this->authorize('pray', $prayer);

        return response()->json($this->prayers->togglePrayed($prayer, $request->user()));
    }

    public function answered(Request $request, PrayerRequest $prayer): RedirectResponse
    {
        $this->authorize('update', $prayer);
        $data = $request->validate(['answer_note' => ['nullable', 'string', 'max:2000'], 'share_testimony' => ['boolean']]);
        $this->prayers->markAnswered($prayer, $data['answer_note'] ?? null);

        if ($request->boolean('share_testimony')) {
            return redirect()->route('testimonies.create', ['prayer' => $prayer->id, 'church' => $prayer->church_id]);
        }

        return back()->with('success', __('Praise God! Everyone who prayed will be told.'));
    }

    public function destroy(Request $request, PrayerRequest $prayer): RedirectResponse
    {
        $this->authorize('update', $prayer);
        $prayer->delete();

        return redirect()->route('prayer.index', ['tab' => 'mine'])->with('success', __('Prayer request removed.'));
    }
}
