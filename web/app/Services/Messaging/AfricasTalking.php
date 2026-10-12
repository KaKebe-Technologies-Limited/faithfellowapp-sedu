<?php

namespace App\Services\Messaging;

use App\Services\Payments\Drivers\GatewayDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * SMS and WhatsApp through Africa's Talking.
 *
 * When a channel is disabled (SMS_ENABLED / WHATSAPP_ENABLED=false, the
 * default in development) messages are written to the log instead, so the
 * whole notification pipeline can be exercised without sending real texts.
 */
class AfricasTalking
{
    /**
     * @return array{ok: bool, ref: ?string, cost: float, error: ?string}
     */
    public function sms(string $phone, string $message, ?string $senderId = null): array
    {
        $to = '+'.GatewayDriver::msisdn($phone);
        $config = config('services.africastalking');

        if (! ($config['sms_enabled'] ?? false) || empty($config['api_key'])) {
            return $this->logOnly('sms', $to, $message);
        }

        $response = Http::asForm()->acceptJson()->timeout(20)
            ->withHeaders(['apiKey' => $config['api_key']])
            ->post($this->baseUrl().'/version1/messaging', array_filter([
                'username' => $config['username'],
                'to' => $to,
                'message' => $message,
                'from' => $senderId ?: ($config['sender_id'] ?? null),
            ]));

        $recipient = $response->json('SMSMessageData.Recipients.0');
        $ok = $response->successful() && in_array($recipient['statusCode'] ?? null, [100, 101, 102], true);

        return [
            'ok' => $ok,
            'ref' => $recipient['messageId'] ?? null,
            'cost' => $this->parseCost($recipient['cost'] ?? null),
            'error' => $ok ? null : Str::limit($recipient['status'] ?? $response->body(), 250),
        ];
    }

    /**
     * @return array{ok: bool, ref: ?string, cost: float, error: ?string}
     */
    public function whatsapp(string $phone, string $message): array
    {
        $to = '+'.GatewayDriver::msisdn($phone);
        $config = config('services.africastalking');

        if (! ($config['whatsapp_enabled'] ?? false) || empty($config['api_key']) || empty($config['whatsapp_number'])) {
            return $this->logOnly('whatsapp', $to, $message);
        }

        $response = Http::acceptJson()->timeout(20)
            ->withHeaders(['apiKey' => $config['api_key']])
            ->post('https://chat.africastalking.com/whatsapp/message/send', [
                'username' => $config['username'],
                'waNumber' => $config['whatsapp_number'],
                'phoneNumber' => $to,
                'body' => ['message' => $message],
            ]);

        return [
            'ok' => $response->successful(),
            'ref' => $response->json('messageId'),
            'cost' => 0.0,
            'error' => $response->successful() ? null : Str::limit($response->body(), 250),
        ];
    }

    public function smsEnabled(): bool
    {
        return (bool) config('services.africastalking.sms_enabled') && filled(config('services.africastalking.api_key'));
    }

    protected function baseUrl(): string
    {
        return config('services.africastalking.username') === 'sandbox'
            ? 'https://api.sandbox.africastalking.com'
            : 'https://api.africastalking.com';
    }

    /** "UGX 35.0000" → 35.0 */
    protected function parseCost(?string $cost): float
    {
        return (float) preg_replace('/[^\d.]/', '', (string) $cost);
    }

    /**
     * @return array{ok: bool, ref: ?string, cost: float, error: ?string}
     */
    protected function logOnly(string $channel, string $to, string $message): array
    {
        Log::info("[{$channel}:log] to {$to}: {$message}");

        return ['ok' => true, 'ref' => 'log-'.Str::random(10), 'cost' => 0.0, 'error' => null];
    }
}
