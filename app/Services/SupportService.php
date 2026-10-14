<?php

namespace App\Services;

use App\Models\Church;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\Support\SupportTicketUpdated;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Support tickets between churches and Kakebe Technologies.
 */
class SupportService
{
    /**
     * @param  array<string, mixed>  $data  subject, category, priority, body
     */
    public function open(User $user, ?Church $church, array $data): SupportTicket
    {
        $ticket = DB::transaction(function () use ($user, $church, $data) {
            $ticket = SupportTicket::create([
                'church_id' => $church?->id, 'user_id' => $user->id, 'subject' => $data['subject'],
                'category' => $data['category'], 'priority' => $data['priority'] ?? 'normal', 'status' => 'open', 'last_reply_at' => now(),
            ]);
            $ticket->replies()->create(['user_id' => $user->id, 'body' => $data['body']]);

            return $ticket;
        });

        Notification::send($this->supportTeam(), new SupportTicketUpdated($ticket->refresh(), 'created', true, $data['body']));

        return $ticket;
    }

    /**
     * A reply from the church side (reopens a resolved ticket) or from support.
     */
    public function reply(SupportTicket $ticket, User $user, string $body, bool $fromStaff, bool $internal = false): void
    {
        $ticket->replies()->create(['user_id' => $user->id, 'body' => $body, 'is_staff' => $fromStaff, 'is_internal' => $fromStaff && $internal]);

        if ($internal) {
            return;
        }

        $ticket->update([
            'last_reply_at' => now(),
            // Support replied → waiting on the church; church replied → back to support.
            'status' => $fromStaff ? ($ticket->status === 'closed' ? 'closed' : 'pending') : 'open',
            'resolved_at' => $fromStaff ? $ticket->resolved_at : null,
        ]);

        if ($fromStaff) {
            $this->churchSide($ticket)->each->notify(new SupportTicketUpdated($ticket, 'reply', false, $body));
        } else {
            Notification::send($ticket->assignee ? collect([$ticket->assignee]) : $this->supportTeam(), new SupportTicketUpdated($ticket, 'reply', true, $body));
        }
    }

    public function setStatus(SupportTicket $ticket, string $status): void
    {
        if ($ticket->status === $status) {
            return;
        }

        $ticket->update(['status' => $status, 'resolved_at' => in_array($status, ['resolved', 'closed'], true) ? now() : null]);
        $this->churchSide($ticket)->each->notify(new SupportTicketUpdated($ticket, 'status', false));
    }

    /**
     * @return Collection<int, User>
     */
    public function supportTeam()
    {
        return User::role('super_admin')->where('status', 'active')->get();
    }

    /**
     * The person who opened the ticket (other church admins can follow it in the support page).
     *
     * @return Collection<int, User>
     */
    protected function churchSide(SupportTicket $ticket)
    {
        return collect([$ticket->user])->filter();
    }
}
