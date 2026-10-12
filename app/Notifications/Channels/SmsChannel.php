<?php

namespace App\Notifications\Channels;

use App\Models\Church;
use App\Services\Messaging\AfricasTalking;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * SMS via Africa's Talking. Uses the notification's toSms() text and the
 * church's sender ID when it has one. Never throws: a failed SMS must not
 * stop other channels or recipients.
 */
class SmsChannel
{
    public function __construct(protected AfricasTalking $gateway) {}

    /**
     * @return array{ok: bool, ref: ?string, cost: float, error: ?string}|null
     */
    public function send(object $notifiable, Notification $notification): ?array
    {
        $phone = $notifiable->routeNotificationFor('sms', $notification) ?? $notifiable->phone ?? null;
        if (! $phone || ! method_exists($notification, 'toSms')) {
            return null;
        }

        try {
            return $this->gateway->sms($phone, $notification->toSms($notifiable), $this->senderId($notification));
        } catch (\Throwable $e) {
            Log::warning('SMS failed: '.$e->getMessage());

            return ['ok' => false, 'ref' => null, 'cost' => 0.0, 'error' => substr($e->getMessage(), 0, 250)];
        }
    }

    protected function senderId(Notification $notification): ?string
    {
        $churchId = property_exists($notification, 'churchId') ? $notification->churchId : null;

        return $churchId ? Church::find($churchId)?->setting('sms_sender_id') : null;
    }
}
