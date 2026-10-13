<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Notifications\PreferenceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What the member wants to hear about, from the app: push and email per kind of news, quiet hours, the daily
 * digest, and muting a whole church. It is the same data as the website's preferences page (global rows).
 */
class NotificationSettingsController extends Controller
{
    public function __construct(protected PreferenceResolver $preferences) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payload($request->user())]);
    }

    public function update(Request $request): JsonResponse
    {
        $keys = array_keys(config('faithfellows.notification_categories'));
        $data = $request->validate([
            'categories' => ['nullable', 'array'],
            'categories.*.push' => ['boolean'],
            'categories.*.email' => ['boolean'],
            'quiet_hours' => ['nullable', 'array'],
            'quiet_hours.start' => ['required_with:quiet_hours', 'date_format:H:i'],
            'quiet_hours.end' => ['required_with:quiet_hours', 'date_format:H:i'],
            'digest_mode' => ['sometimes', 'boolean'],
            'muted_churches' => ['sometimes', 'array'],
            'muted_churches.*' => ['integer'],
        ]);
        $user = $request->user();

        $matrix = [];
        foreach ($data['categories'] ?? [] as $key => $choice) {
            if (! in_array($key, $keys, true)) {
                continue;
            }
            $current = $this->preferences->channelsFor($user, null, $key);
            $channels = ['database'];
            foreach (['push' => 'webpush', 'email' => 'mail'] as $field => $channel) {
                if (($choice[$field] ?? in_array($channel, $current, true)) === true) {
                    $channels[] = $channel;
                }
            }
            // Channels this screen does not edit (SMS, WhatsApp) are left as they were.
            $matrix[$key] = [...$channels, ...array_values(array_intersect($current, ['sms', 'whatsapp']))];
        }
        if ($matrix !== []) {
            $this->preferences->saveMatrix($user, ['global' => $matrix + $this->unchanged($request, $keys)]);
        }

        if ($request->has('quiet_hours')) {
            $user->forceFill([
                'quiet_hours_start' => $data['quiet_hours']['start'] ?? null,
                'quiet_hours_end' => $data['quiet_hours']['end'] ?? null,
            ]);
        }
        if (array_key_exists('digest_mode', $data)) {
            $user->forceFill(['digest_mode' => (bool) $data['digest_mode']]);
        }
        $user->save();

        if (array_key_exists('muted_churches', $data)) {
            foreach ($user->subscribedChurches()->pluck('churches.id') as $churchId) {
                $this->preferences->setChurchMuted($user, (int) $churchId, in_array((int) $churchId, $data['muted_churches'], true));
            }
        }

        return response()->json(['data' => $this->payload($user->fresh())]);
    }

    /**
     * saveMatrix rewrites every category it is given, so the ones not being changed are passed through as they are.
     *
     * @param  array<int, string>  $keys
     * @return array<string, array<int, string>>
     */
    protected function unchanged(Request $request, array $keys): array
    {
        $given = array_keys((array) $request->input('categories', []));

        return collect($keys)->reject(fn ($k) => in_array($k, $given, true))
            ->mapWithKeys(fn ($k) => [$k => $this->preferences->channelsFor($request->user(), null, $k)])->all();
    }

    /** @return array<string, mixed> */
    protected function payload(User $user): array
    {
        return [
            'categories' => collect(config('faithfellows.notification_categories'))->map(function ($label, $key) use ($user) {
                $channels = $this->preferences->channelsFor($user, null, $key);

                return ['key' => $key, 'label' => __($label), 'push' => in_array('webpush', $channels, true), 'email' => in_array('mail', $channels, true)];
            })->values(),
            'quiet_hours' => $user->quiet_hours_start && $user->quiet_hours_end ? ['start' => substr($user->quiet_hours_start, 0, 5), 'end' => substr($user->quiet_hours_end, 0, 5)] : null,
            'digest_mode' => (bool) $user->digest_mode,
            'churches' => $user->subscribedChurches()->orderBy('name')->get()->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'muted' => NotificationPreference::where('user_id', $user->id)->where('church_id', $c->id)->where('enabled', false)->count() >= count(config('faithfellows.notification_categories')),
            ])->values(),
            'devices' => $user->deviceTokens()->count(),
        ];
    }
}
