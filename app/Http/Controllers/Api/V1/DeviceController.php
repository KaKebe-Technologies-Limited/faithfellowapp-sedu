<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Services\DevicePushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The phones that get this member's push notifications. The app registers its Expo push token after sign-in
 * (and whenever the token changes) and removes it when the member signs out.
 */
class DeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:191', fn ($attr, $value, $fail) => DeviceToken::looksValid($value) ?: $fail(__('That is not a push token.'))],
            'platform' => ['nullable', Rule::in(['ios', 'android'])],
            'name' => ['nullable', 'string', 'max:100'],
            'app_version' => ['nullable', 'string', 'max:20'],
        ]);
        $user = $request->user();

        // A token belongs to one phone: signing in as someone else on it moves it to that member.
        $device = DeviceToken::updateOrCreate(['token' => $data['token']], [
            'user_id' => $user->id,
            'platform' => $data['platform'] ?? null,
            'device_name' => $data['name'] ?? null,
            'app_version' => $data['app_version'] ?? null,
            'last_used_at' => now(),
        ]);

        $extra = $user->deviceTokens()->orderByDesc('last_used_at')->orderByDesc('id')->pluck('id')->slice(DeviceToken::MAX_PER_USER);
        if ($extra->isNotEmpty()) {
            DeviceToken::whereIn('id', $extra)->delete();
        }

        return response()->json(['registered' => true], $device->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:191']]);
        $request->user()->deviceTokens()->where('token', $data['token'])->delete();

        return response()->json(['registered' => false]);
    }

    /** "Send me a test" in the app's notification settings: proves the whole chain works for this member. */
    public function test(Request $request, DevicePushService $push): JsonResponse
    {
        $user = $request->user();
        $devices = $user->deviceTokens()->count();
        abort_if($devices === 0, 422, __('This phone is not set up for notifications yet. Allow notifications and try again.'));

        $sent = $push->sendNow($user, [
            'title' => __('Notifications are working'),
            'body' => __('This is a test from FaithFellows. You will get news from your churches like this.'),
            'data' => ['kind' => 'test'],
        ]);

        return response()->json(['devices' => $devices, 'sent' => $sent]);
    }
}
