<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Inspiration;
use App\Services\InspirationService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The short inspiring messages the app's home screen shows in the morning, mid-morning and evening. The super admin adds, edits,
 * switches off and removes them; each part of the day cycles through its own messages, one a day.
 */
class InspirationController extends Controller
{
    public function index(Request $request, InspirationService $service): View
    {
        $slot = $request->query('slot');

        return view('super-admin.inspiration.index', [
            'messages' => Inspiration::query()->when(array_key_exists((string) $slot, Inspiration::SLOTS), fn ($q) => $q->where('slot', $slot))->orderBy('slot')->orderBy('id')->get(),
            'counts' => Inspiration::where('is_active', true)->selectRaw('slot, count(*) as n')->groupBy('slot')->pluck('n', 'slot'),
            'slot' => $slot,
            'now' => $service->current(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $message = Inspiration::create($this->validated($request) + ['is_active' => true]);
        Audit::log('inspiration.created', $message, __('Inspiration added'));

        return back()->with('status', __('Message added.'));
    }

    public function update(Request $request, Inspiration $inspiration): RedirectResponse
    {
        $inspiration->update($this->validated($request) + ['is_active' => $request->boolean('is_active')]);
        Audit::log('inspiration.updated', $inspiration, __('Inspiration changed'));

        return back()->with('status', __('Message saved.'));
    }

    public function destroy(Inspiration $inspiration): RedirectResponse
    {
        $inspiration->delete();
        Audit::log('inspiration.deleted', null, __('Inspiration removed'));

        return back()->with('status', __('Message removed.'));
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'slot' => ['required', Rule::in(array_keys(Inspiration::SLOTS))],
            'body' => ['required', 'string', 'max:280'],
            'reference' => ['nullable', 'string', 'max:80'],
        ]);
    }
}
