<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\GoogleSettings;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The Google OAuth client behind "Continue with Google" in the app and on the website. The secret is saved encrypted. */
class GoogleSettingsController extends Controller
{
    public function edit(): View
    {
        return view('super-admin.google.edit', ['v' => GoogleSettings::values()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'string', 'max:200', 'regex:/^[0-9]+-[A-Za-z0-9_]+\.apps\.googleusercontent\.com$/'],
            'client_secret' => ['nullable', 'string', 'max:200'],
        ], ['client_id.regex' => __('A Google client ID looks like 1234567890-abc….apps.googleusercontent.com.')]);

        GoogleSettings::save($data);
        Audit::log('platform.google_settings_updated', null, $data['client_id'], [], ['client_id' => $data['client_id'], 'secret_changed' => filled($data['client_secret'] ?? null)]);

        return back()->with('success', __('Saved. “Continue with Google” now uses this client.'));
    }
}
