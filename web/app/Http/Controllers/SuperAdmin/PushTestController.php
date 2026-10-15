<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Messaging\ExpoPushSender;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Does push work on this phone?": sends a test notification to the phones one member has signed in on and shows exactly what Expo, and then
 * Google or Apple, answered. For Android it tells apart a phone that is not registered, a missing Firebase key and a project mismatch.
 */
class PushTestController extends Controller
{
    /** What the push services' error codes mean, in words the super admin can act on. */
    public const HINTS = [
        'InvalidCredentials' => 'Expo does not have a working Firebase (FCM V1) key for this app. In Expo, Project, Credentials, Android, upload the Firebase service-account key.',
        'MismatchSenderId' => 'The Firebase project in the app (google-services.json) is not the one whose key Expo holds. Upload the key of the same Firebase project.',
        'DeviceNotRegistered' => 'This phone cannot be reached: the app was uninstalled, or notifications are off. Open the app, allow notifications and sign in again.',
        'MessageTooBig' => 'The message was too big.',
        'MessageRateExceeded' => 'Too many messages were sent to this phone. Try again in a little while.',
    ];

    public function index(): View
    {
        return view('super-admin.push.index', [
            'devices' => DeviceToken::query()->selectRaw('platform, COUNT(*) as n, COUNT(DISTINCT user_id) as people')->groupBy('platform')->get(),
            'recent' => DeviceToken::with('user:id,name,email')->latest('last_used_at')->take(8)->get(),
            'results' => session('push_results'),
            'receipts' => session('push_receipts'),
            'hints' => self::HINTS,
        ]);
    }

    public function send(Request $request, ExpoPushSender $sender): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', mb_strtolower(trim($data['email'])))->first();
        $devices = $user?->deviceTokens()->get() ?? collect();

        if ($devices->isEmpty()) {
            return back()->withInput()->withErrors(['email' => $user
                ? __(':name has no phone registered for notifications yet. Open the app on the phone, allow notifications and sign in.', ['name' => $user->name])
                : __('There is no member with that email.')]);
        }

        $rows = $sender->diagnose($devices, [
            'title' => __('FaithFellows test'),
            'body' => __('If you can read this, notifications work on this phone. Sent :time.', ['time' => now()->format('H:i')]),
            'data' => ['kind' => 'test'],
        ]);
        Audit::log('platform.push_test', $user, $user->email, [], ['devices' => count($rows)]);

        return back()->withInput()->with('push_results', collect($rows)->map(fn ($r) => [
            'platform' => $r['device']->platform ?: 'unknown', 'name' => $r['device']->device_name, 'app_version' => $r['device']->app_version,
            'token' => substr($r['device']->token, 0, 22).'…', 'status' => $r['status'], 'error' => $r['error'], 'message' => $r['message'], 'ticket' => $r['ticket'],
        ])->all());
    }

    /** A few seconds after a test: what Google (Android) or Apple finally said. */
    public function receipts(Request $request, ExpoPushSender $sender): RedirectResponse
    {
        $ids = array_filter((array) $request->input('tickets', []));

        return back()->withInput()->with('push_results', session('push_results'))->with('push_receipts', $sender->receipts($ids));
    }
}
