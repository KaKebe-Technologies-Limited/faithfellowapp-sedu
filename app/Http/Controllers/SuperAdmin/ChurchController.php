<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreChurchRequest;
use App\Models\AuditLog;
use App\Models\Church;
use App\Services\AnalyticsService;
use App\Services\ChurchOnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChurchController extends Controller
{
    public function __construct(protected ChurchOnboardingService $onboarding) {}

    public function index(Request $request): View
    {
        $status = $request->query('status');

        $churches = Church::query()
            ->with(['media', 'owner'])
            ->withCount(['staffMembers'])
            ->search($request->query('q'))
            ->when(in_array($status, Church::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counts = Church::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('super-admin.churches.index', compact('churches', 'counts', 'status'));
    }

    public function create(): View
    {
        return view('super-admin.churches.create');
    }

    public function store(StoreChurchRequest $request): RedirectResponse
    {
        $church = $this->onboarding->invite(
            $request->safe()->except(['admin_name', 'admin_email', 'activate']),
            $request->input('admin_name'),
            $request->input('admin_email'),
            $request->boolean('activate', true),
        );

        return redirect()->route('super-admin.churches.show', $church)
            ->with('success', __(':church was created and the admin was invited.', ['church' => $church->name]));
    }

    public function show(Church $church): View
    {
        $church->load(['media', 'owner', 'approver', 'branches', 'serviceTimes', 'staffMembers.user', 'staffMembers.role']);

        return view('super-admin.churches.show', [
            'church' => $church,
            'activity' => AuditLog::forChurch($church->id)->with('user')->latest('created_at')->take(15)->get(),
            'stats' => app(AnalyticsService::class)->churchSummary($church),
        ]);
    }

    public function update(Request $request, Church $church): RedirectResponse
    {
        $data = $request->validate([
            'is_featured' => ['boolean'],
            'custom_domain' => ['nullable', 'string', 'max:190', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i', Rule::unique('churches')->ignore($church->id)],
            'custom_domain_verified' => ['boolean'],
        ]);

        $church->update($data);

        return back()->with('success', __('Church updated.'));
    }

    public function approve(Church $church): RedirectResponse
    {
        $this->onboarding->approve($church);

        return back()->with('success', __(':church approved and notified.', ['church' => $church->name]));
    }

    public function suspend(Request $request, Church $church): RedirectResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $this->onboarding->suspend($church, $request->input('reason'));

        return back()->with('warning', __(':church has been suspended.', ['church' => $church->name]));
    }

    public function reactivate(Church $church): RedirectResponse
    {
        $this->onboarding->reactivate($church);

        return back()->with('success', __(':church is active again.', ['church' => $church->name]));
    }

    public function archive(Request $request, Church $church): RedirectResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $this->onboarding->archive($church, $request->input('reason'));

        return back()->with('info', __(':church has been archived.', ['church' => $church->name]));
    }
}
