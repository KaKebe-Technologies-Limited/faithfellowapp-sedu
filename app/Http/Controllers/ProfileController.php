<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfilePreferencesRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Services\AccountDeletion;
use App\Services\UserDataExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $sessions = DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($s) => (object) [
                'id' => $s->id,
                'ip' => $s->ip_address,
                'agent' => $this->describeAgent((string) $s->user_agent),
                'is_mobile' => (bool) preg_match('/Mobile|Android|iPhone/i', (string) $s->user_agent),
                'last_active' => Carbon::createFromTimestamp($s->last_activity),
                'is_current' => $s->id === $request->session()->getId(),
            ]);

        return view('profile.edit', [
            'user' => $request->user(),
            'sessions' => $sessions,
            'timezones' => collect(\DateTimeZone::listIdentifiers(\DateTimeZone::AFRICA | \DateTimeZone::EUROPE | \DateTimeZone::AMERICA))
                ->mapWithKeys(fn ($tz) => [$tz => $tz]),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->safe()->except('avatar'));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($request->hasFile('avatar')) {
            $user->addMediaFromRequest('avatar')->toMediaCollection('avatar');
        }

        if ($user->wasChanged('email')) {
            $user->sendEmailVerificationNotification();
        }

        return redirect()->route('profile.edit')->with('success', __('Profile updated.'));
    }

    public function updatePreferences(ProfilePreferencesRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return redirect()->route('profile.edit', ['tab' => 'preferences'])->with('success', __('Preferences saved.'));
    }

    /**
     * Log out of all other devices (session management).
     */
    public function destroySessions(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        Auth::logoutOtherDevices($request->input('password'));
        DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return redirect()->route('profile.edit', ['tab' => 'security'])->with('success', __('Logged out of all other devices.'));
    }

    /**
     * GDPR-style export of everything we hold about the user (JSON).
     */
    public function export(Request $request, UserDataExporter $exporter): StreamedResponse
    {
        $data = $exporter->export($request->user());
        $filename = 'faithfellows-data-'.Str::slug($request->user()->name).'-'.now()->format('Ymd').'.json';

        return response()->streamDownload(
            fn () => print (json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }

    public function destroy(Request $request, AccountDeletion $deletion): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if ($reason = $deletion->blocker($user)) {
            return back()->withErrors(['password' => $reason], 'userDeletion');
        }

        Auth::logout();

        // Personal data goes; gifts and receipts stay without a name, for the churches' books (see AccountDeletion).
        $deletion->delete($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', __('Your account has been deleted.'));
    }

    private function describeAgent(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg') => 'Edge',
            str_contains($agent, 'OPR') || str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'Chrome') => 'Chrome',
            str_contains($agent, 'Firefox') => 'Firefox',
            str_contains($agent, 'Safari') => 'Safari',
            default => __('Unknown browser'),
        };
        $os = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => __('Unknown OS'),
        };

        return $browser.' · '.$os;
    }
}
