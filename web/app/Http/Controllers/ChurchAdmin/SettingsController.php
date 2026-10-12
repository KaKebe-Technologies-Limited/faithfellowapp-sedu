<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Church\UpdateChurchProfileRequest;
use App\Models\Church;
use App\Models\ChurchSocialLink;
use App\Services\ChurchProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(protected ChurchProfileService $profiles) {}

    public function profile(Church $church): View
    {
        $this->authorize('update', $church);
        $church->load(['socialLinks', 'branches']);

        return view('church-admin.settings.profile', [
            'church' => $church,
            'platforms' => array_keys(ChurchSocialLink::PLATFORMS),
        ]);
    }

    public function updateProfile(UpdateChurchProfileRequest $request, Church $church): RedirectResponse
    {
        $this->profiles->updateContact($church, $request->validated());

        // A changed slug changes every URL, so redirect to the new one.
        return redirect()->route('church-admin.settings.profile', $church->fresh())->with('success', __('Church profile saved.'));
    }

    public function branding(Church $church): View
    {
        $this->authorize('update', $church);
        $church->load('media');

        return view('church-admin.settings.branding', ['church' => $church]);
    }

    public function updateBranding(Request $request, Church $church): RedirectResponse
    {
        $this->authorize('update', $church);
        $this->profiles->updateBranding($church, $request);

        return back()->with('success', __('Branding updated.'));
    }

    /**
     * Website builder: drag-and-drop block order with per-block toggles.
     */
    public function website(Church $church): View
    {
        $this->authorize('update', $church);

        return view('church-admin.settings.website', [
            'church' => $church,
            'blocks' => $church->blocks(),
            'blockLabels' => self::blockLabels(),
        ]);
    }

    public function updateWebsite(Request $request, Church $church): RedirectResponse
    {
        $this->authorize('update', $church);

        $types = array_keys(self::blockLabels());
        $blocks = json_decode((string) $request->input('blocks'), true);
        $request->merge(['blocks_array' => is_array($blocks) ? $blocks : []]);
        $data = $request->validate([
            'blocks_array' => ['required', 'array', 'max:20'],
            'blocks_array.*.type' => ['required', Rule::in($types)],
            'blocks_array.*.enabled' => ['required', 'boolean'],
            'hero_title' => ['nullable', 'string', 'max:120'],
            'hero_subtitle' => ['nullable', 'string', 'max:255'],
        ]);

        $church->update([
            'website_blocks' => collect($data['blocks_array'])->unique('type')
                ->map(fn ($b) => ['type' => $b['type'], 'enabled' => (bool) $b['enabled']])->values()->all(),
            'settings' => array_merge($church->settings ?? [], [
                'hero_title' => $data['hero_title'] ?? null,
                'hero_subtitle' => $data['hero_subtitle'] ?? null,
            ]),
        ]);

        return back()->with('success', __('Website updated.'));
    }

    public function integrations(Church $church): View
    {
        $this->authorize('update', $church);

        return view('church-admin.settings.integrations', ['church' => $church]);
    }

    public function updateIntegrations(Request $request, Church $church): RedirectResponse
    {
        $this->authorize('update', $church);

        $data = $request->validate([
            'youtube_channel' => ['nullable', 'url', 'max:255'],
            'facebook_page' => ['nullable', 'url', 'max:255'],
            'whatsapp_group' => ['nullable', 'url', 'max:255'],
            'sms_sender_id' => ['nullable', 'alpha_num', 'max:11'],
            'auto_share' => ['boolean'],
            'notify_sms_default' => ['boolean'],
            'notify_whatsapp_default' => ['boolean'],
        ]);

        $church->update(['settings' => array_merge($church->settings ?? [], ['integrations' => $data])]);

        return back()->with('success', __('Integrations saved.'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function blockLabels(): array
    {
        return [
            'hero' => [__('Hero banner'), 'bi-image'],
            'live' => [__('Live stream'), 'bi-broadcast'],
            'about' => [__('About us'), 'bi-info-circle'],
            'service_times' => [__('Service times'), 'bi-clock'],
            'sermons' => [__('Latest sermons'), 'bi-mic'],
            'events' => [__('Upcoming events'), 'bi-calendar-event'],
            'campaigns' => [__('Campaigns'), 'bi-flag'],
            'giving' => [__('Give online'), 'bi-heart'],
            'prayer' => [__('Prayer wall'), 'bi-chat-heart'],
            'contact' => [__('Contact & map'), 'bi-geo-alt'],
        ];
    }
}
