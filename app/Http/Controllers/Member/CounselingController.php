<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\CounselingAppointment;
use App\Notifications\Care\CommunityUpdate;
use App\Services\PrayerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Private, confidential counseling bookings.
 */
class CounselingController extends Controller
{
    public function index(Request $request): View
    {
        return view('member.counseling.index', [
            'appointments' => CounselingAppointment::with(['church.media', 'counselor'])->where('user_id', $request->user()->id)->latest()->get(),
            'churches' => Church::whereIn('id', $request->user()->subscribedChurchIds())->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request, PrayerService $prayers): RedirectResponse
    {
        $data = $request->validate([
            'church_id' => ['required', Rule::in($request->user()->subscribedChurchIds())],
            'topic' => ['required', 'string', 'min:5', 'max:2000'],
            'mode' => ['required', Rule::in(['in_person', 'phone', 'video'])],
            'preferred_at' => ['nullable', 'date', 'after:now'],
        ]);

        $appointment = CounselingAppointment::create([...$data, 'user_id' => $request->user()->id, 'status' => 'requested']);

        // Staff only see that a request exists — the topic stays in the booking.
        $church = Church::find($data['church_id']);
        Notification::send(
            $prayers->pastoralTeam($church)->filter(fn ($u) => $u->canInChurch($church, 'care.manage')),
            new CommunityUpdate($church, __('New counseling request'), route('church-admin.counseling.index', $church), __('Open the care dashboard to schedule it.'), 'bi-person-heart'),
        );

        return back()->with('success', __('Your request was sent privately to the pastoral team. They will contact you to confirm a time.'));
    }

    public function cancel(Request $request, CounselingAppointment $appointment): RedirectResponse
    {
        $this->authorize('cancel', $appointment);
        $appointment->update(['status' => 'cancelled']);

        return back()->with('success', __('Appointment cancelled.'));
    }
}
