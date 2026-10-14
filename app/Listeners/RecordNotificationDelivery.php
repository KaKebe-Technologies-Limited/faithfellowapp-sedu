<?php

namespace App\Listeners;

use App\Models\NotificationDelivery;
use App\Notifications\ChurchNotification;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Logs every outbound (non in-app) church notification per channel for
 * delivery analytics and SMS cost reporting.
 */
class RecordNotificationDelivery
{
    /** In-app copies live in `notifications` already; broadcast is ephemeral. */
    protected const SKIP = ['database', 'broadcast'];

    public function handle(NotificationSent $event): void
    {
        if (! $event->notification instanceof ChurchNotification || in_array($event->channel, self::SKIP, true)) {
            return;
        }

        $response = $event->response;
        [$status, $cost, $ref, $error] = match (true) {
            is_array($response) => [($response['ok'] ?? false) ? 'sent' : 'failed', (float) ($response['cost'] ?? 0), $response['ref'] ?? null, $response['error'] ?? null],
            in_array($event->channel, ['webpush', 'expo'], true) => [$response > 0 ? 'sent' : 'failed', 0, null, $response > 0 ? null : 'No active device'],
            $response === null && in_array($event->channel, ['sms', 'whatsapp'], true) => ['failed', 0, null, 'No phone number'],
            default => ['sent', 0, null, null],
        };

        $this->record($event->notifiable, $event->notification, $event->channel, $status, $cost, $ref, $error);
    }

    public function handleFailed(NotificationFailed $event): void
    {
        if ($event->notification instanceof ChurchNotification && ! in_array($event->channel, self::SKIP, true)) {
            $this->record($event->notifiable, $event->notification, $event->channel, 'failed', 0, null, substr((string) ($event->data['exception'] ?? null)?->getMessage(), 0, 250));
        }
    }

    protected function record(object $notifiable, ChurchNotification $notification, string $channel, string $status, float $cost, ?string $ref, ?string $error): void
    {
        NotificationDelivery::create([
            'church_id' => $notification->churchId,
            'user_id' => $notifiable->id ?? null,
            'church_broadcast_id' => $notification->broadcastId,
            'notification' => class_basename($notification),
            'category' => $notification->category,
            'channel' => $channel,
            'status' => $status,
            'cost' => $cost,
            'provider_ref' => $ref,
            'error' => $error,
        ]);
    }
}
