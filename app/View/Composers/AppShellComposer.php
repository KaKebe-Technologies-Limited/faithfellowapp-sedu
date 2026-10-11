<?php

namespace App\View\Composers;

use App\Models\Church;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Data every authenticated shell (member / church admin / super admin) needs:
 * unread notification count, staff churches for the switcher, admin badges.
 * Computed once per request.
 */
class AppShellComposer
{
    protected ?array $data = null;

    public function compose(View $view): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $this->data ??= [
            'unreadNotifications' => $user->unreadNotifications()->count(),
            'staffChurches' => $user->staffChurches()->wherePivot('status', 'active')
                ->where('churches.status', '!=', 'archived')->with('media')->orderBy('name')->get(),
            'pendingChurches' => $user->isSuperAdmin() ? Church::where('status', 'pending')->count() : 0,
            'openTickets' => $user->isSuperAdmin() && Schema::hasTable('support_tickets')
                ? \DB::table('support_tickets')->whereIn('status', ['open', 'waiting'])->count() : 0,
        ];

        $view->with($this->data);
    }
}
