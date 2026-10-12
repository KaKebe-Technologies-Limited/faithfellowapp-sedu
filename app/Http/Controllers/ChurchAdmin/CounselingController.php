<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\CounselingAppointment;
use App\Models\User;
use App\Notifications\Care\CommunityUpdate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Confidential counseling queue. Topics and notes are encrypted and only shown
 * to the assigned counselor and care staff (never platform super admins).
 */
class CounselingController extends Controller
{
    public function index(Request $request, Church $church): View
    {
        $status = $request->query('status');

        return view('church-admin.counseling.index', [
            'church' => $church,
            'status' => $status,
            'appointments' => $church->counselingAppointments()->with(['member.media', 'counselor'])
                ->when($status, fn ($q) => $q->where('status', $status), fn ($q) => $q->whereIn('status', ['requested', 'confirmed']))
                ->orderByRaw('scheduled_at IS NULL DESC, scheduled_at')->latest()->paginate(20)->withQueryString(),
            'counselors' => User::whereIn('id', $church->staffMembers()->where('status', 'active')->select('user_id'))->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function update(Request $request, Church $church, CounselingAppointment $counselingAppointment): RedirectResponse
    {
        $appointment = $counselingAppointment;
        $data = $request->validate([
            'counselor_id' => ['nullable', Rule::exists('church_staff', 'user_id')->where('church_id', $church->id)],
            'scheduled_at' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:240'],
            'status' => ['required', Rule::in(['requested', 'confirmed', 'completed', 'cancelled', 'declined'])],
            'counselor_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if (array_key_exists('counselor_notes', $data) && ! $request->user()->can('viewNotes', $appointment)) {
            unset($data['counselor_notes']);
        }

        $wasConfirmed = $appointment->status === 'confirmed';
        $appointment->update(array_filter($data, fn ($v) => $v !== null) + ['status' => $data['status']]);

        if (! $wasConfirmed && $appointment->status === 'confirmed' && $appointment->scheduled_at) {
            $appointment->member?->notify(new CommunityUpdate(
                $church,
                __('Your counseling appointment is confirmed for :when', ['when' => $appointment->scheduled_at->format('D j M, g:i A')]),
                route('counseling.index'),
                null,
                'bi-person-heart',
            ));
        }

        return back()->with('success', __('Appointment updated.'));
    }
}
