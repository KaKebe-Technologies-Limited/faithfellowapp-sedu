<?php

namespace App\Policies;

use App\Models\CounselingAppointment;
use App\Models\User;

/**
 * Counseling is confidential: only the member, the assigned counselor and
 * staff with care.manage in that church. Super admins are NOT exempted
 * from reading notes — Gate::before is bypassed with a false here.
 */
class CounselingAppointmentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        // Platform staff support churches but must not read counseling content.
        if ($user->isSuperAdmin() && $ability === 'viewNotes') {
            return false;
        }

        return null;
    }

    public function view(User $user, CounselingAppointment $appointment): bool
    {
        return $appointment->user_id === $user->id || $this->viewNotes($user, $appointment);
    }

    public function viewNotes(User $user, CounselingAppointment $appointment): bool
    {
        return $appointment->counselor_id === $user->id || $user->canInChurch($appointment->church_id, 'care.manage');
    }

    public function cancel(User $user, CounselingAppointment $appointment): bool
    {
        return $appointment->user_id === $user->id && in_array($appointment->status, ['requested', 'confirmed'], true);
    }
}
