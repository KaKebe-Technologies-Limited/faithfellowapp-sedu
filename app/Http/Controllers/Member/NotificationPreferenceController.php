<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\Notifications\PreferenceResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationPreferenceController extends Controller
{
    public function edit(Request $request, PreferenceResolver $prefs): View
    {
        $user = $request->user();
        $churches = $user->subscribedChurches()->with('media')->orderBy('name')->get();
        $categories = config('faithfellows.notification_categories');

        // Current matrix: churchKey => category => channels
        $matrix = ['global' => []];
        foreach ($categories as $key => $label) {
            $matrix['global'][$key] = $prefs->channelsFor($user, null, $key);
            foreach ($churches as $church) {
                $matrix[$church->id][$key] = $prefs->channelsFor($user, $church->id, $key);
            }
        }

        return view('member.notification-preferences', [
            'user' => $user,
            'churches' => $churches,
            'categories' => $categories,
            'channels' => config('faithfellows.notification_channels'),
            'matrix' => $matrix,
            'selected' => $request->query('church', 'global'),
        ]);
    }

    public function update(Request $request, PreferenceResolver $prefs): RedirectResponse
    {
        $request->validate([
            'prefs' => ['nullable', 'array'],
            'prefs.*' => ['array'],
            'prefs.*.*' => ['array'],
            'prefs.*.*.*' => ['string', 'in:'.implode(',', array_keys(config('faithfellows.notification_channels')))],
            'scope' => ['required', 'string'],
        ]);

        // Unchecked boxes are absent from the request, so build the full scope explicitly.
        $scope = $request->input('scope');
        $submitted = $request->input('prefs', []);
        $prefs->saveMatrix($request->user(), [$scope => $submitted[$scope] ?? []]);

        return redirect()->route('notification-preferences.edit', ['church' => $scope])->with('success', __('Notification preferences saved.'));
    }
}
