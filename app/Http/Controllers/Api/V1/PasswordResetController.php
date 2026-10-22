<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\Auth\PasswordResetCode;
use App\Support\Audit;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * "Forgot password" inside the app: a six-digit code is emailed (instead of a link that would open the website),
 * and the member enters it with the new password. Codes last 15 minutes and allow five wrong tries.
 */
class PasswordResetController extends Controller
{
    private const MINUTES = 15;

    private const TRIES = 5;

    public function sendCode(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $email = Str::lower(trim($data['email']));

        $user = User::where('email', $email)->first();
        // One code a minute for an address: the sign-in limit per phone address is generous (many phones share one), so this is what stops someone
        // filling a person's inbox with codes.
        if ($user && $user->status === 'active' && Cache::add('password-code-sent:'.sha1($email), 1, 60)) {
            $code = (string) random_int(100000, 999999);
            Cache::put($this->key($email), ['hash' => Hash::make($code), 'tries' => 0], now()->addMinutes(self::MINUTES));
            try {
                $user->notify(new PasswordResetCode($code));
            } catch (\Throwable $e) {
                // The mail server did not take it. The answer stays the same (it must not reveal who has an account), but the super admin sees why.
                report($e);
            }
        }

        // The same answer whether or not the address has an account, so this can not be used to look accounts up.
        return response()->json(['message' => __('If that email has an account, we have sent a 6-digit code to it.')], 202);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $email = Str::lower(trim($data['email']));
        $key = $this->key($email);

        $pending = Cache::get($key);
        $user = User::where('email', $email)->first();
        if (! $pending || ! $user || $pending['tries'] >= self::TRIES) {
            Cache::forget($key);
            throw ValidationException::withMessages(['code' => __('That code has expired. Ask for a new one.')]);
        }

        if (! Hash::check($data['code'], $pending['hash'])) {
            Cache::put($key, ['hash' => $pending['hash'], 'tries' => $pending['tries'] + 1], now()->addMinutes(self::MINUTES));
            throw ValidationException::withMessages(['code' => __('That code is not right.')]);
        }

        $user->forceFill(['password' => Hash::make($data['password']), 'remember_token' => Str::random(60)])->save();
        $user->tokens()->delete(); // every phone must sign in again with the new password
        Cache::forget($key);
        event(new PasswordReset($user));
        Audit::log('api.password_reset', $user, $user->email);

        return response()->json(['message' => __('Your password has been changed. Sign in with it now.')]);
    }

    protected function key(string $email): string
    {
        return 'api-password-reset:'.sha1($email);
    }
}
