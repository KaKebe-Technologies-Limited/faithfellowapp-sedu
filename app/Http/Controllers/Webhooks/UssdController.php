<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\User;
use App\Services\DonationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Throwable;

/**
 * USSD giving for feature phones (Africa's Talking USSD gateway).
 *
 * Africa's Talking POSTs sessionId, phoneNumber, serviceCode and the
 * accumulated "text" (answers joined by *). Replies start with
 * "CON " (continue) or "END " (finish).
 *
 *   1. Give  → church code → category → amount → confirm → MoMo prompt
 *   2. Find my church code
 */
class UssdController extends Controller
{
    protected const CATEGORIES = ['1' => 'tithe', '2' => 'offering', '3' => 'building_fund', '4' => 'missions', '5' => 'welfare'];

    public function __invoke(Request $request, DonationService $donations): Response
    {
        $parts = array_values(array_filter(explode('*', (string) $request->input('text')), fn ($p) => $p !== ''));
        $phone = (string) $request->input('phoneNumber');

        $reply = match (count($parts)) {
            0 => "CON Welcome to FaithFellows\n1. Give to my church\n2. Find a church code",
            default => $parts[0] === '2'
                ? $this->findChurch($parts)
                : $this->give($parts, $phone, $donations),
        };

        return response($reply, 200, ['Content-Type' => 'text/plain']);
    }

    protected function findChurch(array $parts): string
    {
        if (count($parts) === 1) {
            return 'CON Enter your town (e.g. Lira):';
        }
        $churches = Church::active()->where('city', 'like', $parts[1].'%')->orderBy('name')->take(5)->get();

        return $churches->isEmpty()
            ? 'END No churches found in '.$parts[1].'.'
            : 'END Church codes:'."\n".$churches->map(fn ($c) => $c->id.' - '.Str::limit($c->name, 24))->implode("\n");
    }

    protected function give(array $parts, string $phone, DonationService $donations): string
    {
        $church = isset($parts[1]) ? Church::active()->find((int) $parts[1]) : null;

        return match (true) {
            count($parts) === 1 => 'CON Enter church code:',
            ! $church => 'END Church code not found. Dial again and choose 2 to find your church code.',
            count($parts) === 2 => 'CON '.Str::limit($church->name, 30)."\n1. Tithe\n2. Offering\n3. Building fund\n4. Missions\n5. Welfare",
            ! isset(self::CATEGORIES[$parts[2]]) => 'END Invalid choice.',
            count($parts) === 3 => 'CON Enter amount (UGX):',
            ! ctype_digit($parts[3]) || (int) $parts[3] < 500 => 'END Minimum gift is UGX 500.',
            count($parts) === 4 => 'CON Give UGX '.number_format((int) $parts[3]).' '.str_replace('_', ' ', self::CATEGORIES[$parts[2]]).' to '.Str::limit($church->name, 22)."?\n1. Confirm\n2. Cancel",
            $parts[4] !== '1' => 'END Cancelled. God bless you.',
            default => $this->charge($church, $parts, $phone, $donations),
        };
    }

    protected function charge(Church $church, array $parts, string $phone, DonationService $donations): string
    {
        $user = User::where('phone', $phone)->orWhere('phone', '0'.substr(preg_replace('/\D/', '', $phone), -9))->first();
        $method = $church->givingChannels()->where('is_active', true)->whereIn('type', ['iotec', 'mtn_momo', 'airtel_money'])->value('type');

        if (! $method) {
            return 'END This church does not accept mobile money yet.';
        }

        try {
            $donations->initiate($user, $church, [
                'amount' => (int) $parts[3], 'category' => self::CATEGORIES[$parts[2]], 'method' => $method, 'phone' => $phone, 'note' => 'USSD',
            ]);
        } catch (Throwable $e) {
            report($e);

            return 'END Sorry, we could not start the payment. Please try again.';
        }

        return 'END You will receive a mobile money prompt. Enter your PIN to complete. Thank you!';
    }
}
