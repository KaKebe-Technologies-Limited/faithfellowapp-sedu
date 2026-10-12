<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\SupportTicket;
use App\Services\SupportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Church staff ↔ Kakebe support. Any active staff member can open and follow tickets.
 */
class SupportController extends Controller
{
    public function __construct(protected SupportService $support) {}

    public function index(Church $church): View
    {
        return view('church-admin.support.index', [
            'church' => $church,
            'tickets' => $church->supportTickets()->with('user')->withCount(['replies' => fn ($q) => $q->where('is_internal', false)])
                ->orderByRaw("FIELD(status, 'pending', 'open', 'resolved', 'closed')")->latest('last_reply_at')->paginate(20),
        ]);
    }

    public function create(Church $church): View
    {
        return view('church-admin.support.create', ['church' => $church]);
    }

    public function store(Request $request, Church $church): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'category' => ['required', Rule::in(array_keys(SupportTicket::CATEGORIES))],
            'priority' => ['required', Rule::in(array_keys(SupportTicket::PRIORITIES))],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $ticket = $this->support->open($request->user(), $church, [...$data, 'body' => strip_tags($data['body'])]);

        return redirect()->route('church-admin.support.show', [$church, $ticket])->with('success', __('Ticket :ref opened. Our team usually replies within one working day.', ['ref' => $ticket->reference]));
    }

    public function show(Church $church, SupportTicket $supportTicket): View
    {
        return view('church-admin.support.show', [
            'church' => $church,
            'ticket' => $supportTicket->load(['user', 'replies' => fn ($q) => $q->where('is_internal', false)->with('user.media')]),
        ]);
    }

    public function reply(Request $request, Church $church, SupportTicket $supportTicket): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $this->support->reply($supportTicket, $request->user(), strip_tags($data['body']), false);

        return back()->with('success', __('Reply sent.'));
    }

    public function close(Church $church, SupportTicket $supportTicket): RedirectResponse
    {
        $this->support->setStatus($supportTicket, 'closed');

        return back()->with('success', __('Ticket closed. Reply any time to reopen it.'));
    }
}
