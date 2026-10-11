<?php

namespace App\View\Composers;

use App\Models\Church;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Data every authenticated shell (member / church admin / super admin) needs:
 * unread notification count, the user's churches for the switchers, and
 * admin badges. Computed once per request (the composer is a singleton).
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
            'subscribedChurches' => $user->subscribedChurches()->with('media')->orderBy('name')->get(),
            'pendingChurches' => $user->isSuperAdmin() ? Church::where('status', 'pending')->count() : 0,
            'openTickets' => $user->isSuperAdmin() && Schema::hasTable('support_tickets')
                ? DB::table('support_tickets')->whereIn('status', ['open', 'waiting'])->count() : 0,
            'liveNowCount' => Schema::hasTable('live_streams')
                ? DB::table('live_streams')->where('status', 'live')->whereIn('church_id', $user->memberChurchIds())->count() : 0,
        ];

        $view->with($this->data);
    }
}
