<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Giving\CampaignRequest;
use App\Models\Campaign;
use App\Models\Church;
use App\Models\Donation;
use App\Services\CampaignService;
use App\Support\CsvExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignController extends Controller
{
    public function __construct(protected CampaignService $campaigns) {}

    public function index(Church $church): View
    {
        return view('church-admin.campaigns.index', [
            'church' => $church,
            'campaigns' => $church->campaigns()->with('media')->withCount('pledges')->latest()->paginate(20),
        ]);
    }

    public function create(Church $church): View
    {
        return $this->form($church, new Campaign(['type' => 'building', 'status' => 'active', 'visibility' => 'public', 'show_leaderboard' => true, 'allow_anonymous' => true, 'currency' => 'UGX']));
    }

    public function store(CampaignRequest $request, Church $church): RedirectResponse
    {
        $campaign = DB::transaction(function () use ($request, $church) {
            $campaign = $church->campaigns()->create([...$this->attributes($request), 'user_id' => $request->user()->id, 'currency' => config('faithfellows.currency')]);
            $this->campaigns->createDefaultMilestones($campaign);
            $this->syncTeams($campaign, $request->input('teams', []));

            return $campaign;
        });
        $this->attachMedia($request, $campaign);

        return redirect()->route('church-admin.campaigns.show', [$church, $campaign])->with('success', __('Campaign created. Share it with your members!'));
    }

    public function show(Church $church, Campaign $campaign): View
    {
        $campaign->load(['milestones', 'teams', 'media', 'updates']);

        return view('church-admin.campaigns.show', [
            'church' => $church,
            'campaign' => $campaign,
            'donations' => $campaign->donations()->with('user')->latest()->paginate(20),
            'pledges' => $campaign->pledges()->with('user')->latest()->get(),
            'leaderboard' => $this->campaigns->leaderboard($campaign),
            'byMethod' => $campaign->donations()->succeeded()->selectRaw('method, SUM(amount) total')->groupBy('method')->pluck('total', 'method'),
        ]);
    }

    public function edit(Church $church, Campaign $campaign): View
    {
        return $this->form($church, $campaign->load(['teams', 'media']));
    }

    public function update(CampaignRequest $request, Church $church, Campaign $campaign): RedirectResponse
    {
        DB::transaction(function () use ($request, $campaign) {
            $campaign->update($this->attributes($request));
            $this->syncTeams($campaign, $request->input('teams', []));
        });
        $this->attachMedia($request, $campaign);
        $this->campaigns->recalculate($campaign);

        return redirect()->route('church-admin.campaigns.show', [$church, $campaign])->with('success', __('Campaign updated.'));
    }

    public function destroy(Church $church, Campaign $campaign): RedirectResponse
    {
        abort_if($campaign->donations()->succeeded()->exists(), 422, __('Campaigns with gifts cannot be deleted — mark them completed or cancelled instead.'));
        $campaign->delete();

        return redirect()->route('church-admin.campaigns.index', $church)->with('success', __('Campaign deleted.'));
    }

    /** Post a progress update (photos, testimonies). */
    public function storeUpdate(Request $request, Church $church, Campaign $campaign): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:20000'],
            'is_testimony' => ['boolean'],
            'photos' => ['nullable', 'array', 'max:8'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $update = $campaign->updates()->create([
            'church_id' => $church->id, 'user_id' => $request->user()->id, 'title' => $data['title'],
            'body' => ff_safe_html($data['body'] ?? ''), 'is_testimony' => $request->boolean('is_testimony'), 'published_at' => now(),
        ]);
        foreach ($request->file('photos', []) as $photo) {
            $update->addMedia($photo)->toMediaCollection('photos');
        }

        return back()->with('success', __('Update posted.'));
    }

    /** Giver list export (CSV or PDF) for records. */
    public function export(Request $request, Church $church, Campaign $campaign): StreamedResponse|Response
    {
        $rows = $campaign->donations()->with('user')->succeeded()->orderBy('paid_at');

        if ($request->query('format') === 'pdf') {
            return Pdf::loadView('receipts.campaign-givers', ['church' => $church, 'campaign' => $campaign, 'rows' => $rows->get()])
                ->download('givers-'.$campaign->slug.'.pdf');
        }

        return CsvExport::download('givers-'.$campaign->slug.'.csv', ['Date', 'Giver', 'Phone', 'Amount', 'Method', 'Receipt', 'Anonymous'],
            $rows->cursor()->map(fn (Donation $d) => [
                $d->paid_at?->format('Y-m-d'), $d->giver_name ?: $d->user?->name, $d->phone, $d->amount, $d->method_label, $d->receipt_number, $d->is_anonymous ? 'yes' : 'no',
            ]));
    }

    /**
     * @return array<string, mixed>
     */
    protected function attributes(CampaignRequest $request): array
    {
        $data = $request->safe()->except(['cover', 'gallery', 'teams']);
        $data['story'] = isset($data['story']) ? ff_safe_html($data['story']) : null;

        return $data;
    }

    /**
     * @param  array<int, array<string, mixed>>  $teams
     */
    protected function syncTeams(Campaign $campaign, array $teams): void
    {
        $keep = [];
        foreach ($teams as $team) {
            if (empty($team['name'])) {
                continue;
            }
            $keep[] = $campaign->teams()->updateOrCreate(['id' => $team['id'] ?? null], [
                'church_id' => $campaign->church_id, 'name' => $team['name'],
                'goal_amount' => $team['goal_amount'] ?: null, 'color' => $team['color'] ?? '#BC2649',
            ])->id;
        }
        $campaign->teams()->whereNotIn('id', $keep)->where('raised_amount', 0)->delete();
    }

    protected function attachMedia(CampaignRequest $request, Campaign $campaign): void
    {
        if ($request->hasFile('cover')) {
            $campaign->addMediaFromRequest('cover')->toMediaCollection('cover');
        }
        foreach ($request->file('gallery', []) as $image) {
            $campaign->addMedia($image)->toMediaCollection('gallery');
        }
    }

    protected function form(Church $church, Campaign $campaign): View
    {
        return view('church-admin.campaigns.form', [
            'church' => $church,
            'campaign' => $campaign,
            'branches' => $church->branches()->orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
