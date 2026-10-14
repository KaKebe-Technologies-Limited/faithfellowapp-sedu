<?php

namespace App\Services\Messaging;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Sends Web Push messages (VAPID) to every browser a user has subscribed.
 * Expired subscriptions (404/410 from the push service) are removed.
 */
class WebPushSender
{
    public function configured(): bool
    {
        return filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key'));
    }

    /**
     * @param  iterable<PushSubscription>  $subscriptions
     * @param  array<string, mixed>  $payload  title, body, url, tag, icon
     * @return int number of browsers that accepted the message
     */
    public function send(iterable $subscriptions, array $payload): int
    {
        if (! $this->configured()) {
            return 0;
        }

        $webPush = new WebPush(['VAPID' => [
            'subject' => config('services.webpush.subject'),
            'publicKey' => config('services.webpush.public_key'),
            'privateKey' => config('services.webpush.private_key'),
        ]], ['TTL' => 3600 * 12, 'urgency' => $payload['urgency'] ?? 'normal'], 20);
        $webPush->setReuseVAPIDHeaders(true);

        $byEndpoint = [];
        foreach ($subscriptions as $sub) {
            $byEndpoint[$sub->endpoint] = $sub;
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $sub->endpoint,
                'publicKey' => $sub->public_key,
                'authToken' => $sub->auth_token,
                'contentEncoding' => $sub->content_encoding,
            ]), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $delivered = 0;
        try {
            foreach ($webPush->flush() as $report) {
                $sub = $byEndpoint[$report->getEndpoint()] ?? null;
                if ($report->isSuccess()) {
                    $delivered++;
                    $sub?->forceFill(['last_used_at' => now()])->saveQuietly();
                } elseif ($report->isSubscriptionExpired()) {
                    $sub?->delete();
                } else {
                    Log::warning('Web push failed: '.$report->getReason());
                }
            }
        } catch (\Throwable $e) {
            // e.g. OpenSSL misconfiguration on a dev machine — never break the request.
            Log::warning('Web push unavailable: '.$e->getMessage());
        }

        return $delivered;
    }
}
