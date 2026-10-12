<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use App\Support\CsvExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Member manager: subscribers, approvals, follow-up stage, export.
 */
class MemberController extends Controller
{
    public function __construct(protected SubscriptionService $subscriptions) {}

    public function index(Request $request, Church $church): View
    {
        $members = $this->query($request, $church)->with(['user.media', 'branch'])->latest('subscribed_at')->paginate(25)->withQueryString();

        $counts = $church->subscriptions()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $stageCounts = $church->subscriptions()->where('status', 'active')->selectRaw('stage, count(*) as total')->groupBy('stage')->pluck('total', 'stage');

        return view('church-admin.members.index', [
            'church' => $church,
            'members' => $members,
            'counts' => $counts,
            'stageCounts' => $stageCounts,
            'branches' => $church->branches()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function update(Request $request, Church $church, Subscription $subscription): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['nullable', Rule::in(['approve', 'block', 'unblock'])],
            'stage' => ['nullable', Rule::in(array_keys(Subscription::STAGES))],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('church_id', $church->id)],
        ]);

        match ($data['action'] ?? null) {
            'approve' => $this->subscriptions->approve($subscription),
            'block' => $this->subscriptions->block($subscription),
            'unblock' => $this->subscriptions->unblock($subscription),
            default => $subscription->update(array_filter([
                'stage' => $data['stage'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
            ], fn ($v) => $v !== null)),
        };

        return back()->with('success', __('Member updated.'));
    }

    public function export(Request $request, Church $church): StreamedResponse
    {
        $rows = $this->query($request, $church)->with(['user', 'branch'])->orderBy('subscribed_at')->cursor()
            ->map(fn (Subscription $s) => [
                $s->user?->name,
                // Emails are only exported for members who consented to sharing them.
                $s->share_email ? $s->user?->email : '',
                $s->user?->phone,
                $s->user?->city,
                Subscription::STAGES[$s->stage] ?? $s->stage,
                $s->branch?->name,
                $s->status,
                $s->subscribed_at?->toDateString(),
            ]);

        return CsvExport::download(
            'members-'.$church->slug.'-'.now()->format('Ymd').'.csv',
            ['Name', 'Email', 'Phone', 'City', 'Stage', 'Branch', 'Status', 'Subscribed'],
            $rows,
        );
    }

    protected function query(Request $request, Church $church): Builder
    {
        return $church->subscriptions()->getQuery()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')),
                fn ($q) => $q->whereIn('status', ['active', 'pending']))
            ->when($request->filled('stage'), fn ($q) => $q->where('stage', $request->query('stage')))
            ->when($request->filled('branch'), fn ($q) => $q->where('branch_id', $request->query('branch')))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('user', fn ($u) => $u
                ->where('name', 'like', '%'.$request->string('q').'%')
                ->orWhere('phone', 'like', '%'.$request->string('q').'%')));
    }
}
