<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\ServiceTime;
use App\Services\ChurchPageService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChurchDirectoryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:80'],
            'denomination' => ['nullable', 'string', 'max:80'],
            'language' => ['nullable', 'string', 'max:8'],
            'day' => ['nullable', 'integer', 'between:0,6'],
            'view' => ['nullable', 'in:grid,map'],
        ]);

        $churches = Church::active()
            ->with(['media', 'serviceTimes'])
            ->search($filters['q'] ?? null)
            ->when($filters['city'] ?? null, fn ($q, $city) => $q->where('city', $city))
            ->when($filters['denomination'] ?? null, fn ($q, $d) => $q->where('denomination', $d))
            ->when($filters['language'] ?? null, fn ($q, $l) => $q->whereJsonContains('languages', $l))
            ->when(isset($filters['day']), fn ($q) => $q->whereHas('serviceTimes', fn ($s) => $s->where('day_of_week', $filters['day'])))
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->paginate(($filters['view'] ?? 'grid') === 'map' ? 200 : 12)
            ->withQueryString();

        $cities = Church::active()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city');
        $denominations = Church::active()->whereNotNull('denomination')->distinct()->orderBy('denomination')->pluck('denomination');

        return view('public.churches.index', [
            'churches' => $churches,
            'cities' => $cities,
            'denominations' => $denominations,
            'filters' => $filters,
            'days' => ServiceTime::DAYS,
            'subscribedIds' => $this->subscribedIds($request),
        ]);
    }

    public function show(Request $request, Church $church, ChurchPageService $pages): View
    {
        $this->authorize('view', $church);

        $church->load(['media', 'serviceTimes.branch', 'socialLinks', 'branches']);

        return view('public.churches.show', [
            'church' => $church,
            'sections' => $pages->sections($church, $request->user()),
            'tab' => $request->query('tab', 'home'),
        ]);
    }

    /**
     * @return array<int, int>
     */
    protected function subscribedIds(Request $request): array
    {
        $user = $request->user();

        return $user && method_exists($user, 'subscribedChurchIds') ? $user->subscribedChurchIds() : [];
    }
}
