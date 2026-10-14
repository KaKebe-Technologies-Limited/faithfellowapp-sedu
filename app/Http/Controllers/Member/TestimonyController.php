<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\PrayerRequest;
use App\Models\Testimony;
use App\Models\User;
use App\Notifications\Care\CommunityUpdate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TestimonyController extends Controller
{
    public function index(Request $request): View
    {
        return view('member.testimonies.index', [
            'testimonies' => Testimony::with(['church.media', 'user.media', 'reactions', 'comments.user.media'])
                ->whereIn('church_id', $request->user()->memberChurchIds() ?: [0])
                ->where('status', 'approved')->latest('reviewed_at')->paginate(12),
            'mine' => Testimony::with('church')->where('user_id', $request->user()->id)->whereIn('status', ['pending', 'rejected'])->latest()->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $prayer = $request->filled('prayer')
            ? PrayerRequest::where('user_id', $request->user()->id)->find($request->query('prayer'))
            : null;

        return view('member.testimonies.create', [
            'churches' => Church::whereIn('id', $request->user()->memberChurchIds())->orderBy('name')->pluck('name', 'id'),
            'prayer' => $prayer,
            'selected' => $prayer?->church_id ?? $request->query('church'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'church_id' => ['required', Rule::in($request->user()->memberChurchIds())],
            'prayer_request_id' => ['nullable', Rule::exists('prayer_requests', 'id')->where('user_id', $request->user()->id)],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'min:20', 'max:5000'],
            'is_anonymous' => ['boolean'],
        ]);

        $testimony = Testimony::create([...$data, 'body' => strip_tags($data['body']), 'user_id' => $request->user()->id, 'status' => 'pending']);

        $church = Church::find($data['church_id']);
        $moderators = User::whereIn('id', $church->staffMembers()->with('role')->where('status', 'active')->get()
            ->filter(fn ($s) => $s->is_admin || in_array('community.moderate', $s->role?->permissions ?? [], true))->pluck('user_id'))->get();
        Notification::send($moderators, new CommunityUpdate($church, __('New testimony to review: :t', ['t' => $testimony->title]), route('church-admin.testimonies.index', $church), null, 'bi-stars'));

        return redirect()->route('testimonies.index')->with('success', __('Thank you for sharing! Your testimony will appear once the church approves it.'));
    }
}
