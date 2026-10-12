<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Church\ServiceTimesRequest;
use App\Models\Church;
use App\Models\ChurchSocialLink;
use App\Models\ServiceTime;
use App\Services\ChurchProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Church profile setup wizard: basics → branding → location → contact →
 * services → giving → team.
 */
class SetupController extends Controller
{
    public function __construct(protected ChurchProfileService $profiles) {}

    /**
     * @return array<string, string>
     */
    public static function steps(): array
    {
        $steps = [
            'basics' => __('Basics'),
            'branding' => __('Branding'),
            'location' => __('Location'),
            'contact' => __('Contact'),
            'services' => __('Service times'),
        ];
        if (Route::has('church-admin.settings.giving')) {
            $steps['giving'] = __('Giving');
        }
        $steps['team'] = __('Team');

        return $steps;
    }

    public function show(Request $request, Church $church): View|RedirectResponse
    {
        $this->authorize('update', $church);

        $steps = self::steps();
        $step = $request->query('step', 'basics');
        abort_unless(array_key_exists($step, $steps), 404);

        if ($step === 'giving') {
            return redirect()->route('church-admin.settings.giving', [$church, 'wizard' => 1]);
        }
        if ($step === 'team') {
            return redirect()->route('church-admin.roles.index', [$church, 'wizard' => 1]);
        }

        $church->load(['media', 'socialLinks', 'serviceTimes', 'branches']);

        return view('church-admin.setup.wizard', [
            'church' => $church,
            'steps' => $steps,
            'step' => $step,
            'stepIndex' => array_search($step, array_keys($steps), true),
            'days' => ServiceTime::DAYS,
            'platforms' => array_keys(ChurchSocialLink::PLATFORMS),
        ]);
    }

    public function update(Request $request, Church $church): RedirectResponse
    {
        $this->authorize('update', $church);

        $step = $request->input('step');
        $steps = array_keys(self::steps());
        abort_unless(in_array($step, $steps, true), 404);

        match ($step) {
            'basics' => $this->profiles->updateProfile($church, $request->validate([
                'name' => ['required', 'string', 'max:160'],
                'denomination' => ['nullable', 'string', 'max:80'],
                'tagline' => ['nullable', 'string', 'max:160'],
                'description' => ['nullable', 'string', 'max:1000'],
                'pastor_name' => ['nullable', 'string', 'max:120'],
                'founded_year' => ['nullable', 'integer', 'between:1800,'.date('Y')],
                'languages' => ['nullable', 'array'],
                'languages.*' => [Rule::in(array_keys(config('faithfellows.languages')))],
            ])),
            'branding' => $this->profiles->updateBranding($church, $request),
            'location' => $this->profiles->updateProfile($church, $request->validate([
                'country' => ['required', 'string', 'size:2'],
                'district' => ['nullable', 'string', 'max:80'],
                'city' => ['required', 'string', 'max:80'],
                'address' => ['nullable', 'string', 'max:255'],
                'lat' => ['nullable', 'numeric', 'between:-90,90'],
                'lng' => ['nullable', 'numeric', 'between:-180,180'],
            ])),
            'contact' => $this->profiles->updateContact($church, $request->validate([
                'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\s()-]{7,32}$/'],
                'email' => ['nullable', 'email', 'max:190'],
                'website' => ['nullable', 'url', 'max:255'],
                'social' => ['nullable', 'array'],
                'social.*' => ['nullable', 'url', 'max:255'],
            ])),
            'services' => $this->profiles->syncServiceTimes($church, app(ServiceTimesRequest::class)->validated()['services'] ?? []),
        };

        $next = $steps[array_search($step, $steps, true) + 1] ?? null;

        if (! $next) {
            $church->update(['setup_completed_at' => now()]);

            return redirect()->route('church-admin.dashboard', $church)->with('success', __('Setup complete — your church is ready!'));
        }

        return redirect()->route('church-admin.setup', [$church, 'step' => $next])->with('success', __('Saved.'));
    }
}
