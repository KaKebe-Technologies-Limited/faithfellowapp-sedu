<?php

namespace App\Notifications\Channels;

use App\Services\Messaging\AfricasTalking;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Business messages via Africa's Talking. Never throws.
 */
class WhatsAppChannel
{
    public function __construct(protected AfricasTalking $gateway) {}

    /**
     * @return array{ok: bool, ref: ?string, cost: float, error: ?string}|null
     */
    public function send(object $notifiable, Notification $notification): ?array
    {
        $phone = $notifiable->routeNotificationFor('whatsapp', $notification) ?? $notifiable->phone ?? null;
        if (! $phone || ! method_exists($notification, 'toWhatsapp')) {
            return null;
        }

        try {
            return $this->gateway->whatsapp($phone, $notification->toWhatsapp($notifiable));
        } catch (\Throwable $e) {
            Log::warning('WhatsApp failed: '.$e->getMessage());

            return ['ok' => false, 'ref' => null, 'cost' => 0.0, 'error' => substr($e->getMessage(), 0, 250)];
        }
    }
}
