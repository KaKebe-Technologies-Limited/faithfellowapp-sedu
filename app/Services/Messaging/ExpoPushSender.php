<?php

namespace App\Services\Messaging;

use App\Jobs\CheckExpoReceipts;
use App\Models\DeviceToken;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends push notifications to the phones that run the app, through Expo's push service
 * (which hands them to Apple and Google). Phones that have uninstalled the app or turned
 * notifications off are reported as "DeviceNotRegistered" and their token is deleted.
 */
class ExpoPushSender
{
    public const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    public const RECEIPTS = 'https://exp.host/--/api/v2/push/getReceipts';

    /**
     * @param  iterable<DeviceToken>  $tokens
     * @param  array<string, mixed>  $message  title, body, subtitle?, data?, channelId?, badge?, priority?, ttl?
     * @return int phones the push service accepted the message for
     */
    public function send(iterable $tokens, array $message): int
    {
        $byToken = [];
        foreach ($tokens as $token) {
            $byToken[$token->token] = $token;
        }
        if ($byToken === []) {
            return 0;
        }

        $messages = array_map(fn (string $to) => array_filter([
            'to' => $to,
            'title' => $message['title'] ?? null,
            'body' => $message['body'] ?? null,
            'subtitle' => $message['subtitle'] ?? null,
            'data' => $message['data'] ?? null,
            'channelId' => $message['channelId'] ?? 'default',
            'badge' => $message['badge'] ?? null,
            'sound' => 'default',
            'priority' => $message['priority'] ?? 'high',
            'ttl' => $message['ttl'] ?? 43200,
        ], fn ($v) => $v !== null), array_keys($byToken));

        $delivered = 0;
        $tickets = [];
        foreach (array_chunk($messages, 100) as $chunk) {
            try {
                $response = $this->http()->post(self::ENDPOINT, $chunk);
            } catch (\Throwable $e) {
                Log::warning('Expo push unreachable: '.$e->getMessage());

                continue;
            }
            if (! $response->successful()) {
                Log::warning('Expo push responded '.$response->status());

                continue;
            }

            foreach ((array) $response->json('data', []) as $i => $ticket) {
                $to = $chunk[$i]['to'] ?? null;
                if (($ticket['status'] ?? null) === 'ok') {
                    $delivered++;
                    if ($to && ! empty($ticket['id'])) {
                        $tickets[$ticket['id']] = $to;
                    }
                    ($byToken[$to] ?? null)?->forceFill(['last_used_at' => now()])->saveQuietly();
                } elseif (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered') {
                    ($byToken[$to] ?? null)?->delete();
                } else {
                    Log::warning('Expo push refused: '.($ticket['message'] ?? 'unknown'));
                }
            }
        }

        // Google and Apple answer later: look at the receipts a while afterwards to clear dead phones.
        if ($tickets !== [] && config('queue.default') !== 'sync') {
            CheckExpoReceipts::dispatch($tickets)->delay(now()->addMinutes(20));
        }

        return $delivered;
    }

    /**
     * Sends one message to each device and reports exactly what the push service answered, so a problem (for Android: Expo has no
     * Firebase key, or the phone's token is dead) can be seen instead of guessed. Used by the super admin's push test.
     *
     * @param  iterable<DeviceToken>  $devices
     * @param  array<string, mixed>  $message
     * @return array<int, array{device: DeviceToken, status: string, error: ?string, message: ?string, ticket: ?string}>
     */
    public function diagnose(iterable $devices, array $message): array
    {
        $rows = [];
        foreach ($devices as $device) {
            $row = ['device' => $device, 'status' => 'error', 'error' => null, 'message' => null, 'ticket' => null];
            try {
                $response = $this->http()->post(self::ENDPOINT, [array_filter([
                    'to' => $device->token,
                    'title' => $message['title'] ?? null,
                    'body' => $message['body'] ?? null,
                    'data' => $message['data'] ?? null,
                    'channelId' => $message['channelId'] ?? 'default',
                    'sound' => 'default',
                    'priority' => 'high',
                ], fn ($v) => $v !== null)]);
                $ticket = (array) ($response->json('data.0') ?? []);
                if (! $response->successful() && $ticket === []) {
                    $row['message'] = 'The push service answered '.$response->status().'.';
                } else {
                    $row['status'] = (string) ($ticket['status'] ?? 'error');
                    $row['ticket'] = $ticket['id'] ?? null;
                    $row['error'] = $ticket['details']['error'] ?? null;
                    $row['message'] = $ticket['message'] ?? null;
                }
            } catch (\Throwable $e) {
                $row['message'] = 'The push service could not be reached: '.$e->getMessage();
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * What Google and Apple finally said about earlier tickets (ticket id => status, error and message).
     *
     * @param  array<int, string>  $ids
     * @return array<string, array{status: string, error: ?string, message: ?string}>
     */
    public function receipts(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        try {
            $data = (array) $this->http()->post(self::RECEIPTS, ['ids' => array_values($ids)])->json('data', []);
        } catch (\Throwable) {
            return [];
        }

        return collect($data)->map(fn ($r) => ['status' => (string) ($r['status'] ?? 'unknown'), 'error' => $r['details']['error'] ?? null, 'message' => $r['message'] ?? null])->all();
    }

    /**
     * Receipts for earlier tickets; tokens whose phone is gone are deleted.
     *
     * @param  array<string, string>  $tickets  ticket id => token
     */
    public function checkReceipts(array $tickets): void
    {
        foreach (array_chunk(array_keys($tickets), 300) as $ids) {
            try {
                $response = $this->http()->post(self::RECEIPTS, ['ids' => $ids]);
            } catch (\Throwable $e) {
                Log::warning('Expo receipts unreachable: '.$e->getMessage());

                continue;
            }

            foreach ((array) $response->json('data', []) as $id => $receipt) {
                if (($receipt['status'] ?? null) === 'error' && ($receipt['details']['error'] ?? null) === 'DeviceNotRegistered' && isset($tickets[$id])) {
                    DeviceToken::where('token', $tickets[$id])->delete();
                }
            }
        }
    }

    protected function http(): PendingRequest
    {
        $http = Http::acceptJson()->asJson()->timeout(15)->retry(2, 500, throw: false);

        return filled(config('services.expo.access_token')) ? $http->withToken(config('services.expo.access_token')) : $http;
    }
}
