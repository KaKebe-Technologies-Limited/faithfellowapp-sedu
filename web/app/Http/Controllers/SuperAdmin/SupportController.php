<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Services\SupportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupportController extends Controller
{
    public function __construct(protected SupportService $support) {}

    public function index(Request $request): View
    {
        $status = $request->query('status', 'active');

        return view('super-admin.support.index', [
            'status' => $status,
            'tickets' => SupportTicket::with(['church', 'user', 'assignee'])
                ->when($status === 'active', fn ($q) => $q->open(), fn ($q) => $q->when($status !== 'all', fn ($q) => $q->where('status', $status)))
                ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->query('priority')))
                ->when($request->query('mine'), fn ($q) => $q->where('assigned_to', $request->user()->id))
                ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('subject', 'like', '%'.$request->query('q').'%')->orWhere('reference', $request->query('q'))))
                ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")->latest('last_reply_at')
                ->paginate(25)->withQueryString(),
            'counts' => SupportTicket::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function show(SupportTicket $supportTicket): View
    {
        return view('super-admin.support.show', [
            'ticket' => $supportTicket->load(['church.media', 'user', 'assignee', 'replies.user.media']),
            'agents' => $this->support->supportTeam()->pluck('name', 'id'),
            'history' => SupportTicket::where('church_id', $supportTicket->church_id)->whereKeyNot($supportTicket->id)->latest()->take(5)->get(),
        ]);
    }

    public function reply(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000'], 'is_internal' => ['boolean']]);
        $this->support->reply($supportTicket, $request->user(), strip_tags($data['body']), true, $request->boolean('is_internal'));

        return back()->with('success', $request->boolean('is_internal') ? __('Internal note added.') : __('Reply sent to the church.'));
    }

    public function update(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'pending', 'resolved', 'closed'])],
            'priority' => ['required', Rule::in(array_keys(SupportTicket::PRIORITIES))],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $supportTicket->update(['priority' => $data['priority'], 'assigned_to' => $data['assigned_to'] ?? null]);
        $this->support->setStatus($supportTicket, $data['status']);

        return back()->with('success', __('Ticket updated.'));
    }
}
